<?php

namespace App\Enums;

enum ModeRemboursement: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case AvoirClient = 'avoir_client';

    public function label(): string
    {
        return match ($this) {
            self::Especes => __('Remboursement en espèces'),
            self::MobileMoney => __('Remboursement mobile money'),
            self::AvoirClient => __('Avoir client (à valoir sur un prochain achat)'),
        };
    }
}
