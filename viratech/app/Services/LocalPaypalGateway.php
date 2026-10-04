<?php

namespace App\Services;

use App\Models\Order;

/** Simulation locale (hors ligne) : aucune requête vers PayPal. */
class LocalPaypalGateway implements PaypalGateway
{
    public function createInvoice(Order $order): array
    {
        return [
            'id' => 'LOCAL-INV-'.$order->reference,
            'url' => url('/commandes/'.$order->reference),
        ];
    }
}
