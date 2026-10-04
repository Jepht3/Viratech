<?php

namespace App\Services;

/**
 * Gabarits des étapes réelles d'un ordre.
 * actor : qui déclenche l'étape (system = événement PayPal / automatique, client, operator).
 * needs : donnée exigée pour valider l'étape (reference = référence ou identifiant de transaction).
 */
class OrderSteps
{
    /** PayPal vers Equity / mobile money. */
    public const WITHDRAWAL = [
        ['key' => 'created', 'label' => 'Commande créée', 'pending' => 'Création de la commande', 'actor' => 'system'],
        ['key' => 'payment_received', 'label' => 'Paiement PayPal reçu', 'pending' => 'En attente de votre paiement PayPal', 'actor' => 'system'],
        ['key' => 'security_check', 'label' => 'Contrôle de sécurité effectué', 'pending' => 'Contrôle de sécurité en cours', 'actor' => 'operator'],
        ['key' => 'payout_in_progress', 'label' => 'Versement lancé', 'pending' => 'Versement en préparation', 'actor' => 'operator'],
        ['key' => 'payout_done', 'label' => 'Versement effectué', 'pending' => 'Versement en cours vers votre compte', 'actor' => 'operator', 'needs' => 'reference'],
        ['key' => 'completed', 'label' => 'Terminé', 'pending' => 'Finalisation', 'actor' => 'system'],
    ];

    /** Mobile money / Equity vers PayPal. */
    public const DEPOSIT = [
        ['key' => 'created', 'label' => 'Commande créée', 'pending' => 'Création de la commande', 'actor' => 'system'],
        ['key' => 'deposit_proof', 'label' => 'Preuve de dépôt envoyée', 'pending' => 'En attente de votre dépôt et de sa preuve', 'actor' => 'client', 'needs' => 'reference'],
        ['key' => 'deposit_verified', 'label' => 'Dépôt vérifié', 'pending' => 'Vérification de votre dépôt', 'actor' => 'operator'],
        ['key' => 'paypal_sending', 'label' => 'Envoi PayPal lancé', 'pending' => 'Envoi PayPal en préparation', 'actor' => 'operator'],
        ['key' => 'paypal_sent', 'label' => 'Paiement PayPal envoyé', 'pending' => 'Envoi PayPal en cours', 'actor' => 'operator', 'needs' => 'reference'],
        ['key' => 'completed', 'label' => 'Terminé', 'pending' => 'Finalisation', 'actor' => 'system'],
    ];

    public static function forWithdrawal(bool $withdrawal): array
    {
        return $withdrawal ? self::WITHDRAWAL : self::DEPOSIT;
    }

    public static function definition(bool $withdrawal, string $key): ?array
    {
        foreach (self::forWithdrawal($withdrawal) as $d) {
            if ($d['key'] === $key) {
                return $d;
            }
        }

        return null;
    }
}
