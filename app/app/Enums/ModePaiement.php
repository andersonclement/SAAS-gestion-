<?php

namespace App\Enums;

enum ModePaiement: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Carte = 'carte';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Especes => __('Espèces'),
            self::MobileMoney => __('Mobile money'),
            self::Carte => __('Carte bancaire'),
            self::Autre => __('Autre'),
        };
    }
}
