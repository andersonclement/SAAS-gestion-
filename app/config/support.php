<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Service client
    |--------------------------------------------------------------------------
    |
    | Coordonnées affichées aux utilisateurs en cas de problème (pied de page,
    | page d'accueil, écran d'abonnement). Les numéros sont surchargeables par
    | l'environnement pour qu'un changement de ligne ne demande pas de
    | redéploiement du code.
    |
    */

    'telephones' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPPORT_TELEPHONES', '675477077,697942141'))
    ))),

    'email' => env('SUPPORT_EMAIL'),

];
