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
        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone, 'role' => $u->role,
            'kyc_level' => $u->kyc_level, 'monthly_limit' => $u->monthlyLimit(),
            'notify_email' => $u->notify_email, 'notify_push' => $u->notify_push,
        ];
    }

    public static function corridor(Corridor $c): array
    {
        return [
            'id' => $c->id, 'code' => $c->code, 'label' => $c->label, 'source_kind' => $c->source_kind, 'target_kind' => $c->target_kind,
            'is_withdrawal' => $c->isWithdrawal(), 'min_amount' => (float) $c->min_amount, 'fixed_fee' => (float) $c->fixed_fee,
            'eta_min_minutes' => $c->eta_min_minutes, 'eta_max_minutes' => $c->eta_max_minutes, 'eta' => $c->etaLabel(),
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
                'source_kind' => $o->source_kind, 'deposit_mode' => $o->deposit_mode, 'paypal_invoice_id' => $o->paypal_invoice_id,
                'eta' => $o->corridor->etaLabel(), 'fees_locked_until' => $o->fees_locked_until?->toIso8601String(), 'closed_reason' => $o->closed_reason,
                'steps' => $o->steps->map(fn ($s) => self::step($s, $current, $o->isActive()))->values(),
                'proofs' => $o->proofs()->get()->map(fn ($p) => ['id' => $p->id, 'kind' => $p->kind, 'reference' => $p->reference, 'has_file' => (bool) $p->path, 'created_at' => $p->created_at->toIso8601String()])->values(),
                'instructions' => self::instructions($o),
                'can_simulate_payment' => config('viratech.simulate_paypal') && $o->isActive() && $current?->key === 'payment_received',
            ];
        }

        return $data;
    }

    /** Où et comment payer, selon l'échange et l'étape en cours. */
    private static function instructions(Order $o): ?array
    {
        if (! $o->isActive()) {
            return null;
        }
        $cur = $o->currentStep();
        if ($o->corridor->isWithdrawal() && $cur?->key === 'payment_received') {
            return $o->deposit_mode === 'invoice'
                ? ['type' => 'paypal_invoice', 'amount' => (float) $o->amount, 'invoice_id' => $o->paypal_invoice_id]
                : ['type' => 'paypal_account', 'amount' => (float) $o->amount, 'account' => CompanyAccount::forKind('paypal')?->account_value, 'reference' => $o->reference];
        }
        if (! $o->corridor->isWithdrawal() && $cur?->key === 'deposit_proof') {
            return ['type' => 'deposit', 'amount' => (float) $o->amount, 'from' => $o->source_kind, 'account' => CompanyAccount::forKind($o->corridor->source_kind)?->account_value, 'reference' => $o->reference];
        }

        return null;
    }
}
