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
            self::Kilogramme => 'Kilogramme (kg)',
            self::Litre => 'Litre (L)',
            self::Sac => 'Sac',
            self::Unite => 'Unité',
        };
    }
}
