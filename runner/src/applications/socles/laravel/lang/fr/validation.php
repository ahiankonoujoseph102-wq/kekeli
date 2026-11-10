<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Messages de validation des formulaires
    |--------------------------------------------------------------------------
    |
    | Les règles absentes de ce fichier gardent leur message anglais :
    | ajoutez ici la traduction de chaque nouvelle règle utilisée.
    |
    */

    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'lowercase' => 'Le champ :attribute doit être écrit en minuscules.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas dépasser :max kilooctets.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit faire au moins :min kilooctets.',
        'numeric' => 'Le champ :attribute doit être au moins égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être un texte.',
    'unique' => 'Ce :attribute est déjà utilisé.',

    /*
    |--------------------------------------------------------------------------
    | Noms des champs
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'current_password' => 'mot de passe actuel',
        'description' => 'description',
        'email' => 'adresse e-mail',
        'name' => 'nom',
        'newName' => 'nom',
        'password' => 'mot de passe',
    ],

];
