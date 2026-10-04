<?php

namespace App\Services;

/**
 * Étapes réelles d'une commande, identiques pour tous les échanges :
 *
 *  1. Le CLIENT paie sur un de NOS comptes (PayPal, mobile money, Equity…) et envoie la capture de son paiement.
 *     (Avec la facture PayPal ou FlexPay, c'est le système qui confirme automatiquement le paiement.)
 *  2. NOUS vérifions que l'argent est bien arrivé, puis le contrôle de sécurité.
 *  3. NOUS versons l'argent sur le compte choisi par le client et envoyons la capture de ce versement.
 *
 * actor : qui déclenche l'étape (system = événement automatique, client, operator).
 * needs : 'proof' = capture obligatoire pour valider l'étape.
 */
class OrderSteps
{
    /** @param bool $toPaypal vrai quand l'argent est envoyé SUR le PayPal du client (mobile money / Equity vers PayPal). */
    public static function flow(bool $toPaypal): array
    {
        return [
            ['key' => 'created', 'label' => 'Commande créée', 'pending' => 'Création de la commande', 'actor' => 'system'],
            ['key' => 'client_payment', 'label' => 'Paiement du client reçu', 'pending' => 'En attente de votre paiement et de sa preuve', 'actor' => 'client', 'needs' => 'proof'],
            ['key' => 'payment_verified', 'label' => 'Paiement vérifié par Viratech', 'pending' => 'Vérification de votre paiement', 'actor' => 'operator'],
            ['key' => 'security_check', 'label' => 'Contrôle de sécurité effectué', 'pending' => 'Contrôle de sécurité en cours', 'actor' => 'operator'],
            ['key' => 'payout_in_progress', 'label' => $toPaypal ? 'Envoi PayPal lancé' : 'Versement lancé', 'pending' => $toPaypal ? 'Envoi PayPal en préparation' : 'Versement en préparation', 'actor' => 'operator'],
            ['key' => 'payout_done', 'label' => $toPaypal ? 'Paiement PayPal envoyé' : 'Versement effectué', 'pending' => $toPaypal ? 'Envoi PayPal en cours' : 'Versement en cours vers votre compte', 'actor' => 'operator', 'needs' => 'proof'],
            ['key' => 'completed', 'label' => 'Terminé', 'pending' => 'Finalisation', 'actor' => 'system'],
        ];
    }

    public static function forCorridor(bool $isWithdrawal): array
    {
        return self::flow(! $isWithdrawal);
    }

    public static function definition(bool $isWithdrawal, string $key): ?array
    {
        foreach (self::forCorridor($isWithdrawal) as $d) {
            if ($d['key'] === $key) {
                return $d;
            }
        }

        return null;
    }

    /** Libellé de l'attente du paiement du client, selon la façon de payer. */
    public static function clientPaymentPending(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'paypal_invoice' => 'En attente du paiement de la facture PayPal',
            'flexpay_mobile' => 'En attente de votre confirmation sur le téléphone (FlexPay)',
            'flexpay_card' => 'En attente de votre paiement par carte (FlexPay)',
            default => 'En attente de votre paiement et de sa preuve',
        };
    }
}
