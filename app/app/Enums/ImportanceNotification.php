<?php

namespace App\Enums;

enum ImportanceNotification: string
{
    case Critique = 'critique';
    case Avertissement = 'avertissement';
    case Information = 'information';

    public function label(): string
    {
        return match ($this) {
            self::Critique => __('Critique'),
            self::Avertissement => __('À surveiller'),
            self::Information => __('Information'),
        };
    }

    /** Couleur de fond du badge. */
    public function couleur(): string
    {
        return match ($this) {
            self::Critique => '#c0392b',
            self::Avertissement => '#b45309',
            self::Information => '#075985',
        };
    }

    public function rang(): int
    {
        return match ($this) {
            self::Critique => 0,
            self::Avertissement => 1,
            self::Information => 2,
        };
    }
}
