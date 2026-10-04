<?php

namespace App\Services;

use App\Models\Order;

/**
 * Délai de sécurité avant de verser l'argent d'un paiement PayPal (protection contre les litiges et rétrofacturations).
 *
 * Repères PayPal : un acheteur peut ouvrir un litige jusqu'à 180 jours après le paiement ; une rétrofacturation par la banque de la carte
 * se fait en général dans les 120 jours ; un nouveau compte vendeur voit souvent ses fonds retenus jusqu'à 21 jours. Aucun délai court
 * ne supprime donc totalement le risque : le délai ci-dessous le réduit, et se combine avec les plafonds, la vérification d'identité
 * et la correspondance du nom du payeur.
 *
 * Délai standard = corridors.hold_minutes (réglable par l'administrateur). Client nouveau ou non vérifié : le double.
 * Client vérifié avec beaucoup d'échanges réussis : la moitié. Les paiements qui ne viennent pas de PayPal sont traités sans délai.
 */
class HoldPolicy
{
    public function __construct(private LimitPolicy $limits) {}

    public function minutes(Order $order): int
    {
        $order->loadMissing('corridor', 'user');
        $base = (int) $order->corridor->hold_minutes;
        if ($base <= 0 || ! $order->corridor->isWithdrawal()) {
            return 0;
        }
        $done = $order->user->orders()->where('status', 'completed')->count();
        $identity = $this->limits->effectiveLevel($order->user) >= 2;

        return match (true) {
            ! $identity || $done < 3 => $base * 2,
            $done >= 10 => (int) round($base / 2),
            default => $base,
        };
    }
}