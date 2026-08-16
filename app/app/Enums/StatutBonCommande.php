<?php

namespace App\Enums;

enum StatutBonCommande: string
{
    case Brouillon = 'brouillon';
    case Recu = 'recu';

    public function label(): string
    {
        return match ($this) {
            self::Brouillon => __('En attente de réception'),
            self::Recu => __('Reçu'),
        };
    }
}
