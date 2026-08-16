<?php

namespace App\Support;

/**
 * Expression SQL de la marge dégagée par des lignes de vente.
 *
 * Prix comme quantités sont stockés en entiers non signés. Or une vente à
 * perte — solde, geste commercial, erreur de saisie — donne un prix de vente
 * inférieur au prix d'achat : sur MySQL, dès qu'un opérande non signé entre
 * dans l'opération, le résultat négatif déclenche « BIGINT UNSIGNED value is
 * out of range » et l'écran tombe en erreur 500. Les trois colonnes sont donc
 * explicitement ramenées en entiers signés — y compris la quantité, qui
 * multiplie une différence pouvant être négative.
 *
 * CAST(... AS SIGNED) est compris par MySQL, MariaDB et SQLite : la même
 * expression sert en production et dans la suite de tests.
 */
class CalculMarge
{
    /**
     * Requiert que la requête joigne la table produits aux lignes de vente.
     */
    public static function expression(string $alias = 'marge'): string
    {
        return 'SUM(CAST(ligne_ventes.quantite AS SIGNED)'
            .' * (CAST(ligne_ventes.prix_unitaire AS SIGNED) - CAST(produits.prix_achat AS SIGNED)))'
            .' as '.$alias;
    }
}
