<?php

namespace App\Enums;

enum PorteePromotion: string
{
    case Globale = 'globale';
    case Produit = 'produit';
    case Categorie = 'categorie';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Globale => __('Toute la boutique'),
            self::Produit => __('Un produit précis'),
            self::Categorie => __('Une catégorie de produits'),
            self::Client => __('Un client fidèle'),
        };
    }
}
