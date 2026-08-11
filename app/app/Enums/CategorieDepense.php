<?php

namespace App\Enums;

enum CategorieDepense: string
{
    case Loyer = 'loyer';
    case Salaires = 'salaires';
    case Transport = 'transport';
    case Fournitures = 'fournitures';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Loyer => __('Loyer'),
            self::Salaires => __('Salaires'),
            self::Transport => __('Transport'),
            self::Fournitures => __('Fournitures'),
            self::Autre => __('Autre'),
        };
    }
}
