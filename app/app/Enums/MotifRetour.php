<?php

namespace App\Enums;

enum MotifRetour: string
{
    case Defectueux = 'defectueux';
    case Perime = 'perime';
    case ErreurCommande = 'erreur_commande';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Defectueux => __('Produit défectueux'),
            self::Perime => __('Produit périmé'),
            self::ErreurCommande => __('Erreur de commande'),
            self::Autre => __('Autre'),
        };
    }
}
