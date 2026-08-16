<?php

namespace App\Enums;

enum TypePromotion: string
{
    case Pourcentage = 'pourcentage';
    case MontantFixe = 'montant_fixe';

    public function label(): string
    {
        return match ($this) {
            self::Pourcentage => __('Pourcentage'),
            self::MontantFixe => __('Montant fixe'),
        };
    }
}
