<?php

namespace App\Support;

/**
 * Génère des graphiques en SVG directement côté serveur.
 *
 * Aucune librairie JavaScript n'est utilisée : la politique de sécurité de
 * contenu interdit les scripts externes, et une page de tableau de bord
 * consultée depuis un téléphone en 4G n'a pas à télécharger plusieurs
 * centaines de kilo-octets pour afficher deux courbes.
 */
class Graphique
{
    /**
     * Courbe d'évolution. Le SVG utilise un viewBox et une largeur à 100 % :
     * il s'adapte donc à la largeur de son conteneur, téléphone compris.
     *
     * @param  array<string, int|float>  $valeurs  libellé => valeur
     */
    public static function courbe(array $valeurs, string $couleur = '#14532d'): string
    {
        if ($valeurs === []) {
            return '';
        }

        $largeur = 720;
        $hauteur = 250;
        // La marge latérale laisse la place aux libellés des extrémités, qui
        // seraient sinon rognés par le bord du cadre.
        $margeGauche = 26;
        $margeBas = 34;
        $margeHaut = 12;

        $points = array_values($valeurs);
        $libelles = array_keys($valeurs);
        $maximum = max($points) ?: 1;
        $nombre = count($points);

        $zoneHaute = $hauteur - $margeBas - $margeHaut;
        $pas = $nombre > 1 ? ($largeur - 2 * $margeGauche) / ($nombre - 1) : 0;

        $coordonnees = [];
        foreach ($points as $index => $valeur) {
            $x = $margeGauche + $index * $pas;
            $y = $margeHaut + $zoneHaute - ($valeur / $maximum) * $zoneHaute;
            $coordonnees[] = [round($x, 1), round($y, 1)];
        }

        $ligne = implode(' ', array_map(fn ($c) => $c[0].','.$c[1], $coordonnees));

        // Aire sous la courbe, refermée sur la ligne de base.
        $base = $margeHaut + $zoneHaute;
        $aire = $ligne.' '.end($coordonnees)[0].','.$base.' '.$coordonnees[0][0].','.$base;

        $svg = '<svg class="graphique" viewBox="0 0 '.$largeur.' '.$hauteur.'" role="img" preserveAspectRatio="none">';
        $svg .= '<polygon points="'.$aire.'" fill="'.e($couleur).'" opacity="0.12"/>';
        $svg .= '<polyline points="'.$ligne.'" fill="none" stroke="'.e($couleur).'" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>';

        foreach ($coordonnees as $index => $c) {
            $svg .= '<circle cx="'.$c[0].'" cy="'.$c[1].'" r="3" fill="'.e($couleur).'"/>';

            // Sur 12 points, un libellé sur deux garde l'axe lisible même sur un
            // écran de téléphone. On compte depuis la fin pour que le mois en
            // cours — le plus intéressant — soit toujours nommé.
            if ($nombre <= 6 || ($nombre - 1 - $index) % 2 === 0) {
                // Les libellés des extrémités s'alignent vers l'intérieur pour
                // ne pas dépasser du cadre.
                $ancre = match (true) {
                    $index === 0 => 'start',
                    $index === $nombre - 1 => 'end',
                    default => 'middle',
                };

                $svg .= '<text x="'.$c[0].'" y="'.($hauteur - 8).'" font-size="17" fill="#6b7280" text-anchor="'.$ancre.'">'
                    .e($libelles[$index]).'</text>';
            }
        }

        return $svg.'</svg>';
    }

    /**
     * Barres horizontales — plus lisibles que des barres verticales quand
     * les libellés sont des noms de boutiques, et sur écran étroit.
     *
     * @param  array<string, int|float>  $valeurs  libellé => valeur
     */
    public static function barres(array $valeurs, string $couleur = '#14532d'): string
    {
        if ($valeurs === []) {
            return '';
        }

        $hauteurBarre = 42;
        $espace = 12;
        $largeur = 720;
        $largeurLibelle = 230;
        $hauteur = count($valeurs) * ($hauteurBarre + $espace);
        $maximum = max($valeurs) ?: 1;

        $svg = '<svg class="graphique" viewBox="0 0 '.$largeur.' '.$hauteur.'" role="img">';

        $y = 0;
        foreach ($valeurs as $libelle => $valeur) {
            // La réserve de droite accueille le montant affiché en bout de barre.
            $longueur = ($valeur / $maximum) * ($largeur - $largeurLibelle - 160);

            $svg .= '<text x="0" y="'.($y + 27).'" font-size="18" fill="#1f2328">'.e(mb_strimwidth($libelle, 0, 24, '…')).'</text>';
            $svg .= '<rect x="'.$largeurLibelle.'" y="'.$y.'" width="'.max(round($longueur, 1), 2).'" height="'.$hauteurBarre.'" rx="4" fill="'.e($couleur).'"/>';
            $svg .= '<text x="'.($largeurLibelle + max($longueur, 2) + 10).'" y="'.($y + 27).'" font-size="17" fill="#6b7280">'
                .e(number_format((float) $valeur, 0, ',', ' ')).'</text>';

            $y += $hauteurBarre + $espace;
        }

        return $svg.'</svg>';
    }
}
