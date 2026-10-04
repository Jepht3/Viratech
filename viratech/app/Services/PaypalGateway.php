<?php

namespace App\Services;

use App\Models\Order;

/**
 * Passerelle PayPal Business (réception des dépôts). L'implémentation réelle (Invoicing API + webhooks)
 * viendra en phase 2 ; en local hors ligne, LocalPaypalGateway simule la facture.
 */
interface PaypalGateway
{
    /** Crée la facture PayPal de l'ordre et renvoie son identifiant et son lien de paiement. */
    public function createInvoice(Order $order): array; // ['id' => string, 'url' => string]
}
