<?php

// Messages de validation en français (les règles utilisées par Viratech).
return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être une liste.',
    'between' => ['numeric' => 'La valeur de :attribute doit être comprise entre :min et :max.', 'string' => 'Le texte de :attribute doit contenir entre :min et :max caractères.', 'file' => 'Le fichier :attribute doit peser entre :min et :max Ko.', 'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.'],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation de :attribute ne correspond pas.',
    'email' => 'Le champ :attribute doit être une adresse email valide.',
    'exists' => 'La valeur choisie pour :attribute est invalide.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'gte' => ['numeric' => 'La valeur de :attribute doit être supérieure ou égale à :value.'],
    'in' => 'La valeur choisie pour :attribute est invalide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => ['numeric' => 'La valeur de :attribute ne peut pas dépasser :max.', 'string' => 'Le texte de :attribute ne peut pas dépasser :max caractères.', 'file' => 'Le fichier :attribute ne peut pas dépasser :max Ko.', 'array' => 'Le champ :attribute ne peut pas contenir plus de :max éléments.'],
    'mimes' => 'Le fichier :attribute doit être de type : :values.',
    'min' => ['numeric' => 'La valeur de :attribute doit être au moins :min.', 'string' => 'Le champ :attribute doit contenir au moins :min caractères.', 'file' => 'Le fichier :attribute doit peser au moins :min Ko.', 'array' => 'Le champ :attribute doit contenir au moins :min éléments.'],
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Cette valeur est déjà utilisée pour :attribute.',
    'password' => ['letters' => 'Le mot de passe doit contenir au moins une lettre.', 'mixed' => 'Le mot de passe doit contenir une majuscule et une minuscule.', 'numbers' => 'Le mot de passe doit contenir au moins un chiffre.', 'symbols' => 'Le mot de passe doit contenir au moins un symbole.', 'uncompromised' => 'Ce mot de passe est apparu dans une fuite de données, choisissez-en un autre.'],
    'attributes' => [
        'email' => 'email', 'password' => 'mot de passe', 'name' => 'nom', 'phone' => 'téléphone', 'amount' => 'montant',
        'corridor' => "type d'échange", 'payout_method_id' => 'moyen de réception', 'account_value' => 'numéro de compte', 'holder_name' => 'nom du titulaire',
        'kind' => 'type', 'reference_code' => 'référence', 'file' => 'fichier', 'reason' => 'raison', 'min_amount' => 'montant minimum', 'fixed_fee' => 'frais fixes',
        'eta_min_minutes' => 'délai minimum', 'eta_max_minutes' => 'délai maximum', 'tiers' => 'paliers', 'kyc_level' => 'niveau de vérification', 'key' => 'étape',
    ],
];