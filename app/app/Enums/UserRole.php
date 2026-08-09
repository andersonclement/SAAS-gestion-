<?php

namespace App\Enums;

enum UserRole: string
{
    case Patron = 'patron';
    case Gerant = 'gerant';
    case Vendeur = 'vendeur';
    case Comptable = 'comptable';

    public function label(): string
    {
        return match ($this) {
            self::Patron => 'Patron',
            self::Gerant => 'Gérant de boutique',
            self::Vendeur => 'Vendeur / Caissier',
            self::Comptable => 'Comptable',
        };
    }

    /**
     * Rôles dont le périmètre est limité à une boutique unique.
     */
    public static function scopedToBoutique(): array
    {
        return [self::Gerant, self::Vendeur];
    }
}
