<?php

return [
    // Hors ligne : permet de simuler le paiement d'une facture PayPal. À désactiver dès que l'API PayPal réelle est branchée.
    'simulate_paypal' => (bool) env('VIRATECH_SIMULATE_PAYPAL', false),
];
