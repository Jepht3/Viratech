<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Corridor;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderProof;
use App\Models\OrderStep;
use App\Models\PayoutMethod;
use App\Models\User;
use App\Notifications\OrderUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cœur métier : création d'un ordre et avancement de ses étapes réelles.
 * Une étape ne passe à « faite » que sur un événement réel (action du client, de l'opérateur ou événement PayPal).
 */
class OrderWorkflow
{
    public const FEES_LOCK_MINUTES = 20;
    public const FIRST_ACTION_MINUTES = 30;

    public const PAYMENT_METHODS = ['paypal_invoice', 'paypal_account', 'transfer', 'flexpay_mobile', 'flexpay_card'];

    public function __construct(private FeeCalculator $fees, private PaypalGateway $paypal, private FlexpayGateway $flexpay, private HoldPolicy $hold) {}

    /**
     * Façons de payer autorisées selon l'échange.
     *  - PayPal en source : facture PayPal ou envoi à notre compte PayPal.
     *  - Autres sources avec FlexPay ACTIVÉ : uniquement FlexPay (mobile money ou carte Visa) : les numéros ne sont pas montrés,
     *    le client arrive directement sur l'écran de paiement automatique.
     *  - Autres sources sans FlexPay : virement direct vers le numéro du réseau choisi, avec capture.
     * En mode local (simulation), tout est proposé quand FlexPay n'est pas activé, pour pouvoir tout tester.
     */
    public static function allowedPayment(Corridor $c): array
    {
        if ($c->isWithdrawal()) {
            return ['paypal_invoice', 'paypal_account'];
        }
        if (\App\Models\Setting::bool('flexpay.enabled')) {
            return $c->source_kind === 'equity' ? ['flexpay_card', 'flexpay_mobile'] : ['flexpay_mobile', 'flexpay_card'];
        }

        return config('viratech.simulate_paypal') ? ['transfer', 'flexpay_mobile', 'flexpay_card'] : ['transfer'];
    }
    public function create(User $user, Corridor $corridor, string|float $amount, PayoutMethod $method, ?string $sourceKind = null, ?string $paymentMethod = null): Order
    {
        $paymentMethod = ['invoice' => 'paypal_invoice', 'account' => 'paypal_account'][$paymentMethod] ?? $paymentMethod;
        $allowedPayment = self::allowedPayment($corridor);
        $paymentMethod = $paymentMethod ?: $allowedPayment[0];
        if (! in_array($paymentMethod, $allowedPayment, true)) {
            throw new InvalidArgumentException('Cette façon de payer n\'est pas disponible pour cet échange.');
        }
        if (! $user->email_verified_at) {
            throw new InvalidArgumentException('Vérifiez votre adresse email avant de faire un échange.');
        }
        if (! $corridor->is_active || $corridor->coming_soon) {
            throw new InvalidArgumentException('Ce type d\'échange n\'est pas disponible pour le moment.');
        }
        if ($method->user_id !== $user->id || ! in_array($method->kind, PayoutMethod::kindsForTarget($corridor->target_kind), true)) {
            throw new InvalidArgumentException('Le moyen de réception choisi ne correspond pas à cet échange.');
        }
        if ($paymentMethod === 'transfer') {
            $allowed = $corridor->source_kind === 'equity' ? ['equity'] : PayoutMethod::MOBILE;
            if (! in_array($sourceKind, $allowed, true)) {
                throw new InvalidArgumentException('Indiquez le réseau (M-Pesa, Airtel Money…) depuis lequel vous allez envoyer l\'argent.');
            }
        }

        $corridor->loadMissing('tiers');
        $quote = $this->fees->quote($corridor, $amount);

        $order = DB::transaction(function () use ($user, $corridor, $quote, $method, $sourceKind, $paymentMethod) {
            $order = Order::create([
                'reference' => $this->newReference(),
                'user_id' => $user->id,
                'corridor_id' => $corridor->id,
                'status' => 'active',
                'amount' => $quote['amount'],
                'percent_applied' => $quote['percent'],
                'percent_fee' => $quote['percent_fee'],
                'fixed_fee' => $quote['fixed_fee'],
                'total_fee' => $quote['total_fee'],
                'net_amount' => $quote['net'],
                'payout_method_id' => $method->id,
                'payout_kind' => $method->kind,
                'payout_account' => $method->account_value,
                'payout_holder' => $method->holder_name,
                'source_kind' => $corridor->isWithdrawal() ? 'paypal' : $sourceKind,
                'deposit_mode' => $corridor->isWithdrawal() ? ($paymentMethod === 'paypal_invoice' ? 'invoice' : 'account') : null,
                'payment_method' => $paymentMethod,
                'fees_locked_until' => now()->addMinutes(self::FEES_LOCK_MINUTES),
                'expires_at' => now()->addMinutes(self::FIRST_ACTION_MINUTES),
            ]);

            foreach (OrderSteps::forCorridor($corridor->isWithdrawal()) as $i => $d) {
                $order->steps()->create([
                    'position' => $i + 1, 'key' => $d['key'], 'label' => $d['label'],
                    'pending_label' => $d['key'] === 'client_payment' ? OrderSteps::clientPaymentPending($paymentMethod) : $d['pending'], 'actor' => $d['actor'],
                ]);
            }

            if ($paymentMethod === 'paypal_invoice') {
                $order->update(['paypal_invoice_id' => $this->paypal->createInvoice($order)['id']]);
            }

            return $order;
        });

        $order->load('steps', 'corridor', 'user');
        $this->advance($order, 'created', 'system', null);

        $this->tell($order->user, $order, 'Commande créée', 'Votre commande de '.$order->amount.' $ est créée. Vous recevrez '.$order->net_amount.' $ sur votre compte '.$method->kindLabel().'.');
        $this->tellStaff($order, 'Nouvelle commande', $user->name.' · '.$corridor->label.' · '.$order->amount.' $.');
        AuditLog::record($user, 'order.created', $order, null, ['reference' => $order->reference, 'amount' => $order->amount]);

        return $order;
    }

    /**
     * Valide l'étape en cours. $actor : system | client | operator.
     * Les étapes « avec preuve » (paiement du client, versement de l'opérateur) exigent une capture, sauf quand l'événement
     * vient du système (facture PayPal payée, paiement FlexPay confirmé).
     */
    public function complete(Order $order, string $key, string $actor, ?User $by = null, ?string $reference = null, ?string $proofPath = null): Order
    {
        $order->loadMissing('steps', 'corridor', 'user');
        $step = $order->currentStep();

        if (! $order->isActive()) {
            throw new InvalidArgumentException('Cette commande est clôturée.');
        }
        if (! $step || $step->key !== $key) {
            throw new InvalidArgumentException('Cette étape n\'est pas l\'étape en cours.');
        }
        if ($step->status === 'blocked') {
            throw new InvalidArgumentException('L\'étape est bloquée : levez d\'abord le blocage.');
        }
        $allowed = match ($step->actor) {
            'operator' => ($key === 'payout_done' && $order->payout_via === 'flexpay') ? ['operator', 'system'] : ['operator'],
            'client' => ['client', 'system'],
            default => ['system', 'operator'],
        };
        if (! in_array($actor, $allowed, true)) {
            throw new InvalidArgumentException('Vous ne pouvez pas valider cette étape.');
        }
        if (in_array($key, ['security_check', 'payout_in_progress', 'payout_done'], true) && $order->payout_not_before?->isFuture()) {
            throw new InvalidArgumentException('Délai de sécurité en cours jusqu\'au '.$order->payout_not_before->format('d/m/Y H:i').' : on vérifie qu\'aucun litige ou rétrofacturation n\'est ouvert avant de verser.');
        }
        $def = OrderSteps::definition($order->corridor->isWithdrawal(), $key);
        if (($def['needs'] ?? null) === 'proof' && $actor !== 'system' && blank($proofPath)) {
            throw new InvalidArgumentException($key === 'client_payment'
                ? 'Joignez la capture de votre paiement : elle est obligatoire.'
                : 'Joignez la capture du versement : elle est obligatoire et sera envoyée au client.');
        }
        if ($key === 'client_payment' && $actor === 'client' && in_array($order->payment_method, ['paypal_invoice', 'flexpay_mobile', 'flexpay_card'], true)) {
            throw new InvalidArgumentException('Ce paiement est confirmé automatiquement : suivez les instructions de paiement.');
        }

        DB::transaction(function () use ($order, $step, $actor, $by, $reference, $proofPath) {
            if ($reference || $proofPath) {
                OrderProof::create([
                    'order_id' => $order->id,
                    'kind' => $step->key === 'payout_done' ? 'operator_payout' : 'client_payment',
                    'reference' => $reference, 'path' => $proofPath, 'uploaded_by' => $by?->id,
                ]);
            }
            $this->advance($order, $step->key, $actor, $by, $reference);

            if ($actor === 'operator' && $by && ! $order->operator_id) {
                $order->update(['operator_id' => $by->id]);
            }

            match ($step->key) {
                'payment_verified' => $this->afterPaymentVerified($order),
                'payout_done' => $this->ledgerPayout($order, $order->payout_kind),
                default => null,
            };

            // Dernière étape automatique : la commande se termine dès que le versement est fait.
            $next = $order->fresh('steps')->currentStep();
            if ($next && $next->key === 'completed') {
                $this->advance($order, 'completed', 'system', null);
                $order->update(['status' => 'completed', 'completed_at' => now(), 'expires_at' => null]);
            }
        });

        $order = $order->fresh(['steps', 'corridor', 'user']);
        $this->announce($order, $key, $actor);
        AuditLog::record($by, 'order.step.'.$key, $order, null, ['reference' => $reference]);

        return $order;
    }


    /** Fonds bien reçus : on les inscrit en comptabilité puis on démarre le délai de sécurité (paiements PayPal). */
    private function afterPaymentVerified(Order $order): void
    {
        $this->ledgerIntake($order, $this->intakeChannel($order));
        $minutes = $this->hold->minutes($order);
        if ($minutes > 0) {
            $until = now()->addMinutes($minutes);
            $order->update(['payout_not_before' => $until]);
            $order->steps()->where('key', 'security_check')->update(['note' => 'Délai de sécurité jusqu\'au '.$until->format('d/m/Y')]);
        }
    }

    /** Verse l'argent au mobile money du client via FlexPay (opération inverse), au lieu d'un versement manuel avec capture. */
    public function payoutViaFlexpay(Order $order, User $operator): Order
    {
        $order->loadMissing('steps', 'corridor', 'user');
        if (! $order->isActive() || $order->currentStep()?->key !== 'payout_done') {
            throw new InvalidArgumentException('Le versement n\'est pas encore à faire pour cette commande.');
        }
        if ($order->corridor->target_kind !== 'mobile_money') {
            throw new InvalidArgumentException('Le versement FlexPay est réservé aux comptes mobile money.');
        }
        if ($order->payout_not_before?->isFuture()) {
            throw new InvalidArgumentException('Délai de sécurité en cours jusqu\'au '.$order->payout_not_before->format('d/m/Y H:i').'.');
        }
        if ($order->flexpay_payout_reference) {
            throw new InvalidArgumentException('Un versement FlexPay est déjà lancé pour cette commande.');
        }
        try {
            $r = $this->flexpay->payout($order, $order->payout_account);
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
        $order->update(['payout_via' => 'flexpay', 'flexpay_payout_reference' => $r['reference'], 'operator_id' => $order->operator_id ?: $operator->id]);
        AuditLog::record($operator, 'order.flexpay_payout', $order, null, ['reference' => $r['reference'], 'amount' => $order->net_amount]);
        $this->tell($order->user, $order, 'Versement lancé', 'Votre versement de '.$order->net_amount.' $ est envoyé sur votre mobile money. Confirmation en cours.');

        return $order->fresh(['steps', 'corridor', 'user']);
    }

    /** Rappel FlexPay ou simulation : le versement est confirmé quand FlexPay le dit (vérification auprès de FlexPay). */
    public function confirmFlexpayPayout(Order $order, bool $trustSimulation = false): Order
    {
        $order->loadMissing('steps', 'corridor', 'user');
        if ($order->payout_via !== 'flexpay' || ! $order->flexpay_payout_reference || ! $order->isActive() || $order->currentStep()?->key !== 'payout_done') {
            return $order;
        }
        if (! $trustSimulation && $this->flexpay->status($order->flexpay_payout_reference) !== 'paid') {
            return $order;
        }

        return $this->complete($order, 'payout_done', 'system', null, $order->flexpay_payout_reference);
    }

    /** Un administrateur lève exceptionnellement le délai de sécurité (raison obligatoire, journalisée). */
    public function releaseHold(Order $order, User $admin, string $reason): void
    {
        if (! $admin->isAdmin()) {
            throw new InvalidArgumentException('Seul un administrateur peut lever le délai de sécurité.');
        }
        $old = $order->payout_not_before;
        $order->update(['payout_not_before' => null]);
        $order->steps()->where('key', 'security_check')->update(['note' => 'Délai de sécurité levé par un administrateur']);
        AuditLog::record($admin, 'order.hold_released', $order, ['until' => $old?->toIso8601String()], ['reason' => $reason]);
    }
    /** Lance le paiement FlexPay (mobile money : demande envoyée sur le téléphone ; carte : adresse de la page de paiement). */
    public function startFlexpay(Order $order, ?string $phone = null): Order
    {
        $order->loadMissing('steps', 'corridor', 'user');
        if (! in_array($order->payment_method, ['flexpay_mobile', 'flexpay_card'], true)) {
            throw new InvalidArgumentException('Cette commande ne se paie pas avec FlexPay.');
        }
        if (! $order->isActive() || $order->currentStep()?->key !== 'client_payment') {
            throw new InvalidArgumentException('Cette commande n\'attend pas de paiement.');
        }
        try {
            $r = $this->flexpay->charge($order, $order->payment_method, $phone);
        } catch (\RuntimeException $e) {
            throw new InvalidArgumentException($e->getMessage());
        }
        $order->update(['flexpay_reference' => $r['reference'], 'flexpay_url' => $r['url']]);

        return $order->fresh(['steps', 'corridor', 'user']);
    }

    /** Appelé par le rappel FlexPay ou la simulation : on interroge FlexPay avant de confirmer (jamais de confiance au corps du rappel). */
    public function confirmFlexpay(Order $order, bool $trustSimulation = false): Order
    {
        $order->loadMissing('steps', 'corridor', 'user');
        if (! $order->flexpay_reference || ! $order->isActive() || $order->currentStep()?->key !== 'client_payment') {
            return $order;
        }
        if (! $trustSimulation && $this->flexpay->status($order->flexpay_reference) !== 'paid') {
            return $order;
        }

        return $this->complete($order, 'client_payment', 'system', null, $order->flexpay_reference);
    }
    public function block(Order $order, User $by, string $reason): void
    {
        $step = $order->loadMissing('steps')->currentStep();
        if (! $step || ! $order->isActive()) {
            throw new InvalidArgumentException('Rien à bloquer.');
        }
        $step->update(['status' => 'blocked', 'note' => $reason]);
        $this->tell($order->user, $order, 'Action requise', $reason);
        AuditLog::record($by, 'order.blocked', $order, null, ['reason' => $reason]);
    }

    public function unblock(Order $order, User $by): void
    {
        $step = $order->loadMissing('steps')->currentStep();
        if ($step && $step->status === 'blocked') {
            $step->update(['status' => 'pending', 'note' => null]);
            AuditLog::record($by, 'order.unblocked', $order);
        }
    }

    public function reject(Order $order, User $by, string $reason): void
    {
        if (! $order->isActive()) {
            throw new InvalidArgumentException('Cette commande est déjà clôturée.');
        }
        $order->update(['status' => 'rejected', 'closed_reason' => $reason, 'expires_at' => null, 'operator_id' => $by->id]);
        $this->tell($order->user, $order, 'Commande refusée', $reason);
        AuditLog::record($by, 'order.rejected', $order, null, ['reason' => $reason]);
    }

    /** Expire les commandes dont le client n'a rien fait dans le délai. Renvoie le nombre d'ordres expirés. */
    public function expireStale(): int
    {
        $n = 0;
        Order::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<', now())->each(function (Order $o) use (&$n) {
            $o->update(['status' => 'expired', 'closed_reason' => 'Aucune action dans le délai imparti', 'expires_at' => null]);
            $this->tell($o->user, $o, 'Commande expirée', 'Nous n\'avons rien reçu dans le délai. Vous pouvez créer une nouvelle commande.');
            $n++;
        });

        return $n;
    }

    // ───────────────────────── internes ─────────────────────────

    /** Marque l'étape faite et démarre la suivante. */
    private function advance(Order $order, string $key, string $actor, ?User $by, ?string $note = null): void
    {
        $step = $order->steps()->where('key', $key)->firstOrFail();
        $step->update([
            'status' => 'done', 'done_at' => now(), 'done_by_type' => $actor,
            'done_by_id' => $by?->id, 'note' => $note ?: $step->note, 'started_at' => $step->started_at ?? now(),
        ]);
        $order->steps()->where('position', $step->position + 1)->whereNull('started_at')->update(['started_at' => now()]);
        $order->unsetRelation('steps');
    }

    private function announce(Order $order, string $key, string $actor): void
    {
        $label = $order->steps->firstWhere('key', $key)?->label ?? $key;
        $next = $order->currentStep();
        $toPaypal = ! $order->corridor->isWithdrawal();
        $msg = match ($key) {
            'client_payment' => $actor === 'system' ? 'Votre paiement de '.$order->amount.' $ est confirmé.' : 'Votre paiement et sa preuve sont enregistrés, un opérateur vérifie la réception.',
            'payment_verified' => 'Nous avons bien reçu votre paiement de '.$order->amount.' $.',
            'security_check' => 'Le contrôle de sécurité est terminé.',
            'payout_in_progress' => $toPaypal ? 'L\'envoi PayPal est en cours de préparation.' : 'Le versement est en cours de préparation.',
            'payout_done' => $toPaypal
                ? 'Paiement de '.$order->net_amount.' $ envoyé sur votre compte PayPal. La capture de l\'envoi est disponible dans la commande.'
                : 'Versement de '.$order->net_amount.' $ effectué sur votre compte. La capture du versement est disponible dans la commande.',
            default => $label,
        };
        if ($order->status === 'completed') {
            $msg .= ' Votre commande est terminée, merci !';
        } elseif ($next && $actor !== 'client') {
            $msg .= ' Prochaine étape : '.Str::lower($next->pending_label).'.';
        }

        $this->tell($order->user, $order, $label, $msg);
        if ($actor === 'client') {
            $this->tellStaff($order, 'Paiement à vérifier', $order->user->name.' a envoyé la preuve de son paiement de '.$order->amount.' $ ('.$order->reference.').');
        }
    }

    /** Canal par lequel l'argent du client est entré chez nous (pour la comptabilité). */
    private function intakeChannel(Order $order): string
    {
        return match (true) {
            str_starts_with((string) $order->payment_method, 'flexpay') => 'flexpay',
            in_array($order->payment_method, ['paypal_invoice', 'paypal_account'], true) => 'paypal',
            default => (string) $order->source_kind,
        };
    }
    private function ledgerIntake(Order $order, string $channel): void
    {
        $tx = (string) Str::uuid();
        $this->entry($order, $tx, 'company:'.$channel, $order->amount, 0, 'Fonds reçus ('.$order->reference.')');
        $this->entry($order, $tx, 'client:'.$order->user_id.':payable', 0, $order->amount, 'Fonds reçus ('.$order->reference.')');
    }

    private function ledgerPayout(Order $order, string $channel): void
    {
        $tx = (string) Str::uuid();
        $this->entry($order, $tx, 'client:'.$order->user_id.':payable', $order->amount, 0, 'Versement ('.$order->reference.')');
        $this->entry($order, $tx, 'company:'.$channel, 0, $order->net_amount, 'Versement ('.$order->reference.')');
        $this->entry($order, $tx, 'company:fees', 0, $order->total_fee, 'Frais ('.$order->reference.')');
    }

    private function entry(Order $order, string $tx, string $account, string|float $debit, string|float $credit, string $desc): void
    {
        LedgerEntry::create(['order_id' => $order->id, 'transaction' => $tx, 'account' => $account, 'debit' => $debit, 'credit' => $credit, 'description' => $desc, 'created_at' => now()]);
    }

    private function newReference(): string
    {
        do {
            $ref = 'VT-'.strtoupper(Str::random(6));
        } while (Order::where('reference', $ref)->exists());

        return $ref;
    }

    /** Une panne d'email ne doit jamais bloquer une commande : la notification interne est déjà enregistrée. */
    private function send(User $user, OrderUpdated $notification): void
    {
        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function tell(User $user, Order $order, string $title, string $body): void
    {
        $this->send($user, new OrderUpdated($title, $body, url('/commandes/'.$order->reference), $order->reference));
    }

    private function tellStaff(Order $order, string $title, string $body): void
    {
        User::whereIn('role', ['operator', 'admin'])->where('is_active', true)->get()
            ->each(fn (User $u) => $this->send($u, new OrderUpdated($title, $body, url('/admin/commandes/'.$order->reference), $order->reference)));
    }
}
