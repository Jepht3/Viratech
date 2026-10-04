<?php

namespace App\Services;

use App\Models\User;

/**
 * Plafond mensuel d'un client.
 *
 *  - email non vérifié : aucun échange ;
 *  - email vérifié, identité non vérifiée : 150 $ seulement ;
 *  - identité vérifiée (pièce + photo avec la pièce en main) : 3 000 $, qui monte avec les commandes terminées.
 *
 * Un plafond fixé à la main par l'administrateur (custom_monthly_limit) prime sur tout le reste.
 */
class LimitPolicy
{
    /** Niveau réellement appliqué : sans email vérifié, aucun échange n'est possible. */
    public function effectiveLevel(User $u): int
    {
        return $u->email_verified_at ? max(1, (int) $u->kyc_level) : 0;
    }

    public function completedOrders(User $u): int
    {
        return $u->orders()->where('status', 'completed')->count();
    }

    /** La montée automatique ne concerne que les clients dont l'identité est vérifiée (niveau 2). */
    public function multiplier(User $u): float
    {
        if ($this->effectiveLevel($u) !== 2) {
            return 1.0;
        }
        $done = $this->completedOrders($u);
        $m = 1.0;
        foreach (config('viratech.limit_growth') as $orders => $mult) {
            if ($done >= $orders) {
                $m = max($m, (float) $mult);
            }
        }

        return $m;
    }

    public function limit(User $u): float
    {
        if ($u->custom_monthly_limit !== null) {
            return (float) $u->custom_monthly_limit;
        }
        $base = (float) (config('viratech.limits')[$this->effectiveLevel($u)] ?? 0);

        return round($base * $this->multiplier($u), 2);
    }

    /** Détail pour l'affichage (« encore 2 commandes pour passer à 4 500 $ »). */
    public function describe(User $u): array
    {
        $level = $this->effectiveLevel($u);
        $base = (float) (config('viratech.limits')[$level] ?? 0);
        $done = $this->completedOrders($u);
        $next = null;
        if ($u->custom_monthly_limit === null && $level === 2) {
            foreach (config('viratech.limit_growth') as $orders => $mult) {
                if ($done < $orders) {
                    $next = ['orders_needed' => $orders - $done, 'limit' => round($base * $mult, 2)];
                    break;
                }
            }
        }

        return [
            'level' => $level, 'base' => $base, 'multiplier' => $this->multiplier($u), 'limit' => $this->limit($u),
            'custom' => $u->custom_monthly_limit !== null, 'completed_orders' => $done, 'next' => $next,
            'email_verified' => (bool) $u->email_verified_at,
            'identity_verified' => $level >= 2,
            'identity_limit' => (float) config('viratech.limits')[2],
        ];
    }
}