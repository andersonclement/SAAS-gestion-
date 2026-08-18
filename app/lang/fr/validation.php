<?php

/*
|--------------------------------------------------------------------------
| Messages de validation
|--------------------------------------------------------------------------
|
| Sans ce fichier, Laravel ne trouve aucune traduction pour la locale « fr »
| et affiche la clé brute : un commerçant qui oublie un champ lit
| « validation.required ». Le message doit dire quoi corriger, sinon la
| validation ne sert qu'à bloquer.
|
| Le tableau « attributes » en fin de fichier nomme les champs tels qu'ils
| apparaissent à l'écran : « la date de péremption », et non
| « date peremption ».
|
*/

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'accepted_if' => 'Le champ :attribute doit être accepté quand :other vaut :value.',
    'active_url' => "Le champ :attribute n'est pas une URL valide.",
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha' => 'Le champ :attribute ne peut contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne peut contenir que des lettres, des chiffres, des tirets et des tirets bas.',
    'alpha_num' => 'Le champ :attribute ne peut contenir que des lettres et des chiffres.',
    'any_of' => "Le champ :attribute n'est pas valide.",
    'array' => 'Le champ :attribute doit être une liste.',
    'ascii' => 'Le champ :attribute ne peut contenir que des caractères et des symboles non accentués.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max kilo-octets.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'can' => 'Le champ :attribute contient une valeur non autorisée.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'contains' => 'Il manque une valeur au champ :attribute.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => "Le champ :attribute n'est pas une date valide.",
    'date_equals' => 'Le champ :attribute doit être une date égale au :date.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'decimal' => 'Le champ :attribute doit comporter :decimal décimales.',
    'declined' => 'Le champ :attribute doit être refusé.',
    'declined_if' => 'Le champ :attribute doit être refusé quand :other vaut :value.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit comporter :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit comporter entre :min et :max chiffres.',
    'dimensions' => "Les dimensions de l'image :attribute ne sont pas valides.",
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'doesnt_contain' => 'Le champ :attribute ne doit contenir aucune des valeurs suivantes : :values.',
    'doesnt_end_with' => 'Le champ :attribute ne doit pas se terminer par : :values.',
    'doesnt_start_with' => 'Le champ :attribute ne doit pas commencer par : :values.',
    'email' => "Le champ :attribute n'est pas une adresse e-mail valide.",
    'encoding' => "Le champ :attribute n'utilise pas le bon encodage.",
    'ends_with' => 'Le champ :attribute doit se terminer par une des valeurs suivantes : :values.',
    'enum' => "La valeur choisie pour :attribute n'existe pas.",
    'exists' => "La valeur choisie pour :attribute n'existe pas.",
    'extensions' => 'Le fichier :attribute doit porter une des extensions suivantes : :values.',
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit être renseigné.',
    'gt' => [
        'array' => 'Le champ :attribute doit contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser plus de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'array' => 'Le champ :attribute doit contenir au moins :value éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],
    'hex_color' => "Le champ :attribute n'est pas une couleur hexadécimale valide.",
    'image' => 'Le champ :attribute doit être une image.',
    'in' => "La valeur choisie pour :attribute n'est pas autorisée.",
    'in_array' => 'Le champ :attribute doit exister dans :other.',
    'in_array_keys' => 'Le champ :attribute doit contenir au moins une des clés suivantes : :values.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'ip' => "Le champ :attribute n'est pas une adresse IP valide.",
    'ipv4' => "Le champ :attribute n'est pas une adresse IPv4 valide.",
    'ipv6' => "Le champ :attribute n'est pas une adresse IPv6 valide.",
    'json' => "Le champ :attribute n'est pas du JSON valide.",
    'list' => 'Le champ :attribute doit être une liste.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'lt' => [
        'array' => 'Le champ :attribute doit contenir moins de :value éléments.',
        'file' => 'Le fichier :attribute doit peser moins de :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :value éléments.',
        'file' => 'Le fichier :attribute doit peser au plus :value kilo-octets.',
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au plus :value caractères.',
    ],
    'mac_address' => "Le champ :attribute n'est pas une adresse MAC valide.",
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas peser plus de :max kilo-octets.',
        'numeric' => 'Le champ :attribute ne doit pas dépasser :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'max_digits' => 'Le champ :attribute ne doit pas comporter plus de :max chiffres.',
    'mimes' => 'Le fichier :attribute doit être de type : :values.',
    'mimetypes' => 'Le fichier :attribute doit être de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir au moins :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'min_digits' => 'Le champ :attribute doit comporter au moins :min chiffres.',
    'missing' => 'Le champ :attribute doit être absent.',
    'missing_if' => 'Le champ :attribute doit être absent quand :other vaut :value.',
    'missing_unless' => 'Le champ :attribute doit être absent sauf si :other vaut :value.',
    'missing_with' => 'Le champ :attribute doit être absent quand :values est renseigné.',
    'missing_with_all' => 'Le champ :attribute doit être absent quand :values sont renseignés.',
    'multiple_of' => 'Le champ :attribute doit être un multiple de :value.',
    'not_in' => "La valeur choisie pour :attribute n'est pas autorisée.",
    'not_regex' => "Le format du champ :attribute n'est pas valide.",
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'present' => 'Le champ :attribute doit être présent.',
    'present_if' => 'Le champ :attribute doit être présent quand :other vaut :value.',
    'present_unless' => 'Le champ :attribute doit être présent sauf si :other vaut :value.',
    'present_with' => 'Le champ :attribute doit être présent quand :values est renseigné.',
    'present_with_all' => 'Le champ :attribute doit être présent quand :values sont renseignés.',
    'prohibited' => 'Le champ :attribute est interdit.',
    'prohibited_if' => 'Le champ :attribute est interdit quand :other vaut :value.',
    'prohibited_if_accepted' => 'Le champ :attribute est interdit quand :other est accepté.',
    'prohibited_if_declined' => 'Le champ :attribute est interdit quand :other est refusé.',
    'prohibited_unless' => 'Le champ :attribute est interdit sauf si :other fait partie de :values.',
    'prohibits' => 'Le champ :attribute interdit la présence de :other.',
    'regex' => "Le format du champ :attribute n'est pas valide.",
    'required' => 'Le champ :attribute est obligatoire.',
    'required_array_keys' => 'Le champ :attribute doit contenir les entrées suivantes : :values.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_if_accepted' => 'Le champ :attribute est obligatoire quand :other est accepté.',
    'required_if_declined' => 'Le champ :attribute est obligatoire quand :other est refusé.',
    'required_unless' => 'Le champ :attribute est obligatoire sauf si :other fait partie de :values.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',
    'required_with_all' => 'Le champ :attribute est obligatoire quand :values sont renseignés.',
    'required_without' => "Le champ :attribute est obligatoire quand :values n'est pas renseigné.",
    'required_without_all' => "Le champ :attribute est obligatoire quand aucun de :values n'est renseigné.",
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size kilo-octets.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'starts_with' => 'Le champ :attribute doit commencer par une des valeurs suivantes : :values.',
    'string' => 'Le champ :attribute doit être du texte.',
    'timezone' => "Le champ :attribute n'est pas un fuseau horaire valide.",
    'unique' => 'Cette valeur de :attribute est déjà utilisée.',
    'uploaded' => "Le fichier :attribute n'a pas pu être envoyé.",
    'uppercase' => 'Le champ :attribute doit être en majuscules.',
    'url' => "Le champ :attribute n'est pas une URL valide.",
    'ulid' => "Le champ :attribute n'est pas un ULID valide.",
    'uuid' => "Le champ :attribute n'est pas un UUID valide.",

    /*
    |--------------------------------------------------------------------------
    | Messages propres à un champ
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'date_peremption' => [
            'after' => 'La date de péremption doit être postérieure à aujourd\'hui : un lot déjà périmé ne peut pas entrer en stock.',
        ],
        'stock_max' => [
            'gte' => 'Le stock maximum doit être supérieur ou égal au stock minimum.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Noms des champs
    |--------------------------------------------------------------------------
    |
    | Sans cette table, :attribute rend le nom technique du champ
    | (« date peremption », « boutique id »).
    |
    */

    'attributes' => [
        'boutique_id' => 'boutique',
        'categorie' => 'catégorie',
        'categorie_id' => 'catégorie',
        'client_id' => 'client',
        'code' => "code d'accès",
        'code_barres' => 'code-barres',
        'conditionnement_id' => 'format de vente',
        'date_echeance' => "date d'échéance",
        'date_fabrication' => 'date de fabrication',
        'date_peremption' => 'date de péremption',
        'destinataire_user_id' => 'gérant destinataire',
        'duree_heures' => 'durée de validité',
        'email' => 'adresse e-mail',
        'fichier' => 'fichier',
        'fournisseur_id' => 'fournisseur',
        'libelle' => 'libellé',
        'localisation' => 'localisation',
        'mode_paiement' => 'mode de paiement',
        'montant' => 'montant',
        'montant_paye' => 'montant payé',
        'motif' => 'motif',
        'name' => 'nom',
        'nom' => 'nom',
        'numero_lot' => 'numéro de lot',
        'password' => 'mot de passe',
        'plafond_credit' => 'plafond de crédit',
        'portee' => 'portée',
        'prix_achat' => "prix d'achat",
        'prix_vente' => 'prix de vente',
        'produit_id' => 'produit',
        'quantite' => 'quantité',
        'quantite_initiale' => 'quantité initiale',
        'role' => 'rôle',
        'stock_max' => 'stock maximum',
        'stock_min' => 'stock minimum',
        'telephone' => 'téléphone',
        'type' => 'type',
        'unite_mesure' => 'unité de mesure',
    ],
];
