<?php

namespace App\Enums;

enum StatutCodeActivation: string
{
    case EnAttente = 'en_attente';
    case Utilise = 'utilise';
    case Revoque = 'revoque';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => __('En attente'),
            self::Utilise => __('Utilisé'),
            self::Revoque => __('Révoqué'),
        };
    }
}
