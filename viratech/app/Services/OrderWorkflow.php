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

    public function __construct(private FeeCalculator $fees, private PaypalGateway $paypal) {}

    public function create(User $user, Corridor $corridor, string|float $amount, PayoutMethod $method, ?string $sourceKind = null, ?string $depositMode = null): Order
    {
        if (! $corridor->is_active || $corridor->coming_soon) {
            throw new InvalidArgumentException('Ce type d\'échange n\'est pas disponible pour le moment.');
        }
        if ($method->user_id !== $user->id || ! in_array($method->kind, PayoutMethod::kindsForTarget($corridor->target_kind), true)) {
            throw new InvalidArgumentException('Le moyen de réception choisi ne correspond pas à cet échange.');
        }
        if (! $corridor->isWithdrawal()) {
            $allowed = $corridor->source_kind === 'equity' ? ['equity'] : PayoutMethod::MOBILE;
            if (! in_array($sourceKind, $allowed, true)) {
                throw new InvalidArgumentException('Indiquez le compte depuis lequel vous allez envoyer l\'argent.');
            }
        }

        $corridor->loadMissing('tiers');
        $quote = $this->fees->quote($corridor, $amount);

        $order = DB::transaction(function () use ($user, $corridor, $quote, $method, $sourceKind, $depositMode) {
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
                'deposit_mode' => $corridor->isWithdrawal() ? ($depositMode ?: 'invoice') : null,
                'fees_locked_until' => now()->addMinutes(self::FEES_LOCK_MINUTES),
                'expires_at' => now()->addMinutes(self::FIRST_ACTION_MINUTES),
            ]);

            foreach (OrderSteps::forWithdrawal($corridor->isWithdrawal()) as $i => $d) {
                $order->steps()->create([
                    'position' => $i + 1, 'key' => $d['key'], 'label' => $d['label'],
                    'pending_label' => $d['pending'], 'actor' => $d['actor'],
                ]);
            }

            if ($order->deposit_mode === 'invoice') {
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

    /** Valide l'étape en cours. $actor : system | client | operator. */
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
            'operator' => ['operator'],
            'client' => ['client'],
            default => ['system', 'operator'],
        };
        if (! in_array($actor, $allowed, true)) {
            throw new InvalidArgumentException('Vous ne pouvez pas valider cette étape.');
        }
        $def = OrderSteps::definition($order->corridor->isWithdrawal(), $key);
        if (($def['needs'] ?? null) === 'reference' && blank($reference) && blank($proofPath)) {
            throw new InvalidArgumentException('Une référence de transaction ou une preuve est obligatoire pour cette étape.');
        }

        DB::transaction(function () use ($order, $step, $actor, $by, $reference, $proofPath) {
            if ($reference || $proofPath) {
                OrderProof::create([
                    'order_id' => $order->id,
                    'kind' => in_array($step->key, ['payout_done', 'paypal_sent'], true) ? 'operator_payout' : 'client_payment',
                    'reference' => $reference, 'path' => $proofPath, 'uploaded_by' => $by?->id,
                ]);
            }
            $this->advance($order, $step->key, $actor, $by, $reference);

            if ($actor === 'operator' && $by && ! $order->operator_id) {
                $order->update(['operator_id' => $by->id]);
            }

            match ($step->key) {
                'payment_received' => $this->ledgerIntake($order, $order->corridor->isWithdrawal() ? 'paypal' : $order->source_kind),
                'deposit_proof' => null,
                'deposit_verified' => $this->ledgerIntake($order, $order->source_kind),
                'payout_done' => $this->ledgerPayout($order, $order->payout_kind),
                'paypal_sent' => $this->ledgerPayout($order, 'paypal'),
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

    /** Le client signale avoir payé sur PayPal (mode « compte affiché ») : l'opérateur confirme ensuite. */
    public function claimPayment(Order $order, User $client, ?string $reference, ?string $proofPath): void
    {
        if (blank($reference) && blank($proofPath)) {
            throw new InvalidArgumentException('Indiquez l\'identifiant de transaction PayPal ou joignez une capture.');
        }
        OrderProof::create(['order_id' => $order->id, 'kind' => 'client_payment', 'reference' => $reference, 'path' => $proofPath, 'uploaded_by' => $client->id]);
        $order->steps()->where('key', 'payment_received')->where('status', 'pending')->update(['note' => 'Paiement signalé par le client'.($reference ? ' · '.$reference : '')]);
        $order->update(['paypal_transaction_id' => $reference ?: $order->paypal_transaction_id]);
        $this->tellStaff($order, 'Paiement signalé', $client->name.' indique avoir payé '.$order->amount.' $ sur PayPal. À vérifier.');
        $this->tell($client, $order, 'Paiement signalé', 'Merci, nous vérifions la réception de votre paiement PayPal.');
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
        $msg = match ($key) {
            'payment_received' => 'Nous avons bien reçu votre paiement PayPal de '.$order->amount.' $.',
            'deposit_proof' => 'Votre preuve de dépôt est enregistrée, un opérateur la vérifie.',
            'deposit_verified' => 'Votre dépôt est vérifié.',
            'security_check' => 'Le contrôle de sécurité est terminé.',
            'payout_in_progress', 'paypal_sending' => 'Le versement est en cours de préparation.',
            'payout_done' => 'Versement de '.$order->net_amount.' $ effectué sur votre compte. Vérifiez votre solde.',
            'paypal_sent' => 'Paiement de '.$order->net_amount.' $ envoyé sur votre compte PayPal.',
            default => $label,
        };
        if ($order->status === 'completed') {
            $msg .= ' Votre commande est terminée, merci !';
        } elseif ($next && $actor !== 'client') {
            $msg .= ' Prochaine étape : '.Str::lower($next->pending_label).'.';
        }

        $this->tell($order->user, $order, $label, $msg);
        if ($actor === 'client') {
            $this->tellStaff($order, 'Action du client', $order->user->name.' : '.Str::lower($label).' ('.$order->reference.').');
        }
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
