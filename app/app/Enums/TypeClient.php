<?php

namespace App\Enums;

enum TypeClient: string
{
    case Particulier = 'particulier';
    case Agriculteur = 'agriculteur';
    case Cooperative = 'cooperative';

    public function label(): string
    {
        return match ($this) {
            self::Particulier => __('Particulier'),
            self::Agriculteur => __('Agriculteur'),
            self::Cooperative => __('Coopérative'),
        };
    }
}
