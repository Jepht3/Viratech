<?php

namespace App\Services;

use App\Models\Order;

/**
 * FlexPay : encaissement (mobile money, carte Visa) ET versement vers le mobile money du client.
 * La confirmation n'est JAMAIS prise dans le corps d'un rappel (callback) : on interroge toujours FlexPay pour connaître l'état réel.
 */
interface FlexpayGateway
{
    /**
     * Encaisse le client. $method : flexpay_mobile (demande envoyée sur son téléphone) ou flexpay_card (page de paiement).
     *
     * @return array{reference: string, url: ?string}
     */
    public function charge(Order $order, string $method, ?string $phone = null): array;

    /**
     * Verse l'argent au client sur son mobile money (opération inverse). Nécessite que le service de versement soit activé par FlexPay.
     *
     * @return array{reference: string}
     */
    public function payout(Order $order, string $phone): array;

    /** État réel d'une opération chez FlexPay : paid | pending | failed. */
    public function status(string $reference): string;
}