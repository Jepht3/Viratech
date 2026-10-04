<?php

return [
    // Hors ligne : permet de simuler les paiements PayPal et FlexPay. À désactiver dès que les passerelles réelles sont branchées.
    'simulate_paypal' => (bool) env('VIRATECH_SIMULATE_PAYPAL', false),

    // Plafonds mensuels (USD) selon le niveau de vérification.
    // 0 = email non vérifié (aucun échange) · 1 = email vérifié, identité non vérifiée : 150 $ seulement
    // 2 = identité vérifiée (pièce + photo avec la pièce en main) · 3 = sur mesure.
    'limits' => [
        0 => 0,
        1 => 150,
        2 => 3000,
        3 => 10000,
    ],

    // Montée automatique du plafond selon le nombre de commandes TERMINÉES, réservée aux clients dont l'identité est vérifiée.
    // [commandes terminées minimum => multiplicateur]
    'limit_growth' => [
        3 => 1.5,
        10 => 2.0,
        25 => 3.0,
    ],

    'kyc' => [
        'challenge_minutes' => 30,       // durée de validité du code à écrire sur papier (selfie avec la pièce)
        'max_submissions_per_day' => 3,
        'max_file_kb' => 8192,
    ],

    // Code de vérification de l'email : durée et essais.
    'email_code' => [
        'minutes' => 15,
        'max_attempts' => 5,
        // En local uniquement : renvoie le code dans la réponse pour pouvoir tester sans lire l'email.
        'show_dev_code' => (bool) env('VIRATECH_SHOW_DEV_OTP', false),
    ],
    // FlexPay (paiement par mobile money ou carte Visa). Les identifiants et adresses viennent de votre contrat FlexPay.
    'flexpay' => [
        'enabled' => (bool) env('FLEXPAY_ENABLED', false),
        'merchant' => env('FLEXPAY_MERCHANT'),
        'token' => env('FLEXPAY_TOKEN'),
        'mobile_url' => env('FLEXPAY_MOBILE_URL'),
        'card_url' => env('FLEXPAY_CARD_URL'),
        'check_url' => env('FLEXPAY_CHECK_URL'),
        'callback_secret' => env('FLEXPAY_CALLBACK_SECRET'),
    ],
];
