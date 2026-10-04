<?php

namespace App\Services;

use App\Models\Order;

/** Simulation locale (hors ligne) : aucune requête vers FlexPay. Le paiement est confirmé par le bouton de simulation. */
class LocalFlexpayGateway implements FlexpayGateway
{
    public function charge(Order $order, string $method, ?string $phone = null): array
    {
        return ['reference' => 'LOCAL-FP-'.$order->reference, 'url' => null];
    }

    public function payout(Order $order, string $phone): array
    {
        return ['reference' => 'LOCAL-PO-'.$order->reference];
    }

    public function status(string $reference): string
    {
        return 'pending';
    }
}