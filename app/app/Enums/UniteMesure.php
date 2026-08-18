<?php

namespace App\Enums;

/**
 * Unité de base d'un produit : celle dans laquelle son stock est compté.
 *
 * À ne pas confondre avec les formats de vente (voir Conditionnement) : le
 * carton de 24 sachets se déclare en format, pas ici. On ne met dans cette
 * liste que ce qu'une boutique compte réellement en rayon — si elle achète et
 * revend des cartons sans jamais les ouvrir, le carton est bien son unité de
 * base ; si elle en sort des sachets à l'unité, alors le sachet est l'unité et
 * le carton devient un format.
 */
enum UniteMesure: string
{
    case Kilogramme = 'kg';
    case Litre = 'litre';
    case Sac = 'sac';
    case Carton = 'carton';
    case Sachet = 'sachet';
    case Unite = 'unite';

    public function label(): string
    {
        return match ($this) {
            self::Kilogramme => __('Kilogramme (kg)'),
            self::Litre => __('Litre (L)'),
            self::Sac => __('Sac'),
            self::Carton => __('Carton'),
            self::Sachet => __('Sachet'),
            self::Unite => __('Unité'),
        };
    }
}
