<?php

namespace App\Support;

use App\Models\CompanyAccount;
use App\Models\Corridor;
use App\Models\Order;
use App\Models\OrderStep;
use App\Models\PayoutMethod;
use App\Models\User;

/** Mise en forme JSON commune aux applications mobiles (client et admin). */
class Present
{
    public static function user(User $u): array
    {
        $kyc = $u->kycSubmissions()->latest('id')->first();

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'role' => $u->role,
            'kyc_level' => $u->kyc_level, 'monthly_limit' => $u->monthlyLimit(),
            'phone_verified' => (bool) $u->phone_verified_at,
            'avatar_url' => $u->avatar_path ? url('/api/avatar/'.$u->id).'?v='.$u->updated_at?->timestamp : null,
            'limit' => $u->role === 'client' ? $u->limitInfo() : null,
            'kyc' => $kyc ? ['status' => $kyc->status, 'status_label' => $kyc->statusLabel(), 'rejection_reason' => $kyc->rejection_reason, 'submitted_at' => $kyc->created_at->toIso8601String()] : null,
            'notify_email' => $u->notify_email, 'notify_push' => $u->notify_push,
        ];
    }
    public static function corridor(Corridor $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'label' => $c->label, 'source_kind' => $c->source_kind, 'target_kind' => $c->target_kind,
            'is_withdrawal' => $c->isWithdrawal(), 'min_amount' => (float) $c->min_amount, 'fixed_fee' => (float) $c->fixed_fee,
            'eta_min_minutes' => $c->eta_min_minutes, 'eta_max_minutes' => $c->eta_max_minutes, 'hold_minutes' => $c->hold_minutes, 'eta' => $c->etaLabel(),
            'is_active' => $c->is_active, 'coming_soon' => $c->coming_soon,
            'tiers' => $c->tiers->map(fn ($t) => ['min_amount' => (float) $t->min_amount, 'percent' => (float) $t->percent])->values(),
        ];
    }

    public static function method(PayoutMethod $m): array
    {
        return [
            'id' => $m->id, 'kind' => $m->kind, 'kind_label' => $m->kindLabel(), 'label' => $m->label,
            'account_value' => $m->account_value, 'masked' => $m->masked(), 'holder_name' => $m->holder_name, 'is_verified' => $m->is_verified,
        ];
    }

    public static function step(OrderStep $s, ?OrderStep $current, bool $orderActive): array
    {
        $isCurrent = $orderActive && $current && $current->id === $s->id;

        return [
            'key' => $s->key, 'label' => $s->label, 'pending_label' => $s->pending_label, 'actor' => $s->actor,
            'status' => $s->status === 'done' ? 'done' : ($isCurrent ? ($s->status === 'blocked' ? 'blocked' : 'current') : 'todo'),
            'done_at' => $s->done_at?->toIso8601String(), 'done_by' => $s->done_at ? $s->actorLabel() : null,
            'started_at' => $s->started_at?->toIso8601String(), 'note' => $s->note,
        ];
    }

    /** @param bool $detail inclure étapes, preuves et instructions de paiement */
    public static function order(Order $o, bool $detail = false, bool $staff = false): array
    {
        $o->loadMissing('corridor', 'steps', 'user');
        $current = $o->currentStep();
        $total = $o->steps->count();
        $done = $o->steps->where('status', 'done')->count();

        $data = [
            'reference' => $o->reference, 'status' => $o->status, 'status_label' => $o->statusLabel(),
            'corridor' => ['code' => $o->corridor->code, 'label' => $o->corridor->label, 'source_kind' => $o->corridor->source_kind, 'target_kind' => $o->corridor->target_kind, 'is_withdrawal' => $o->corridor->isWithdrawal()],
            'amount' => (float) $o->amount, 'percent' => (float) $o->percent_applied, 'percent_fee' => (float) $o->percent_fee,
            'fixed_fee' => (float) $o->fixed_fee, 'total_fee' => (float) $o->total_fee, 'net_amount' => (float) $o->net_amount,
            'progress' => $total ? (int) round($done / $total * 100) : 0,
            'current_step' => $current ? ['key' => $current->key, 'label' => $current->pending_label, 'actor' => $current->actor, 'blocked' => $current->status === 'blocked', 'since' => $current->started_at?->toIso8601String()] : null,
            'created_at' => $o->created_at->toIso8601String(),
        ];
        if ($staff) {
            $data['client'] = ['id' => $o->user->id, 'name' => $o->user->name, 'phone' => $o->user->phone, 'kyc_level' => $o->user->kyc_level];
        }

        if ($detail) {
            $data += [
                'payout' => ['kind' => $o->payout_kind, 'account' => $o->payout_account, 'holder' => $o->payout_holder,
                    'name_matches' => strcasecmp(trim($o->payout_holder), trim($o->user->name)) === 0],
                'source_kind' => $o->source_kind, 'payment_method' => $o->payment_method, 'paypal_invoice_id' => $o->paypal_invoice_id,
                'eta' => $o->corridor->etaLabel(), 'fees_locked_until' => $o->fees_locked_until?->toIso8601String(), 'closed_reason' => $o->closed_reason,
                'steps' => $o->steps->map(fn ($s) => self::step($s, $current, $o->isActive()))->values(),
                'proofs' => $o->proofs()->get()->map(fn ($p) => ['id' => $p->id, 'kind' => $p->kind, 'reference' => $p->reference, 'has_file' => (bool) $p->path, 'created_at' => $p->created_at->toIso8601String()])->values(),
                'instructions' => self::instructions($o),
                'payout_not_before' => $o->payout_not_before?->toIso8601String(),
                'hold_active' => (bool) $o->payout_not_before?->isFuture(),
                'payout_via' => $o->payout_via, 'flexpay_payout_reference' => $o->flexpay_payout_reference,
                'can_flexpay_payout' => $staff && $o->isActive() && $current?->key === 'payout_done' && $o->corridor->target_kind === 'mobile_money' && ! $o->flexpay_payout_reference && ! $o->payout_not_before?->isFuture() && \App\Models\Setting::bool('flexpay.enabled') && \App\Models\Setting::bool('flexpay.payout_enabled'),
                'can_simulate_payout' => $staff && config('viratech.simulate_paypal') && $o->isActive() && $o->payout_via === 'flexpay' && $current?->key === 'payout_done',
                'can_simulate_payment' => config('viratech.simulate_paypal') && $o->isActive() && $current?->key === 'client_payment' && in_array($o->payment_method, ['paypal_invoice', 'flexpay_mobile', 'flexpay_card'], true),
            ];
        }

        return $data;
    }

    /** Où et comment payer (étape « paiement du client »). */
    private static function instructions(Order $o): ?array
    {
        if (! $o->isActive() || $o->currentStep()?->key !== 'client_payment') {
            return null;
        }
        $amount = (float) $o->amount;

        return match ($o->payment_method) {
            'paypal_invoice' => ['type' => 'paypal_invoice', 'amount' => $amount, 'invoice_id' => $o->paypal_invoice_id, 'proof_required' => false],
            'paypal_account' => ['type' => 'paypal_account', 'amount' => $amount, 'account' => CompanyAccount::forKind('paypal')?->account_value, 'reference' => $o->reference, 'proof_required' => true],
            'flexpay_mobile', 'flexpay_card' => ['type' => 'flexpay', 'method' => $o->payment_method, 'amount' => $amount, 'started' => (bool) $o->flexpay_reference, 'url' => $o->flexpay_url, 'proof_required' => false],
            default => ['type' => 'deposit', 'amount' => $amount, 'from' => $o->source_kind, 'account' => CompanyAccount::forKind($o->corridor->source_kind)?->account_value, 'reference' => $o->reference, 'proof_required' => true],
        };
    }
}