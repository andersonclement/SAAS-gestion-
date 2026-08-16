<?php

namespace App\Enums;

enum UniteMesure: string
{
    case Kilogramme = 'kg';
    case Litre = 'litre';
    case Sac = 'sac';
    case Unite = 'unite';

    public function label(): string
    {
        return match ($this) {
            self::Kilogramme => __('Kilogramme (kg)'),
            self::Litre => __('Litre (L)'),
            self::Sac => __('Sac'),
            self::Unite => __('Unité'),
        };
    }
}
