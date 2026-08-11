<?php

namespace App\Enums;

/**
 * Offres d'abonnement de la plateforme (facturées au patron par le
 * superadmin via des codes d'activation) : deux formules standard au
 * nombre de boutiques, et une formule "hébergement à vie" à prix réduit
 * réservée aux clients que le superadmin choisit d'y faire basculer.
 */
enum Plan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Hebergement = 'hebergement';

    public function label(): string
    {
        return match ($this) {
            self::Starter => __('Starter'),
            self::Pro => __('Pro'),
            self::Hebergement => __('Hébergement à vie'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Starter => __('Jusqu\'à :max boutiques', ['max' => $this->boutiquesMax()]),
            self::Pro => __('Jusqu\'à :max boutiques', ['max' => $this->boutiquesMax()]),
            self::Hebergement => __('Jusqu\'à :max boutiques — frais d\'hébergement uniquement', ['max' => $this->boutiquesMax()]),
        };
    }

    public function prixMensuel(): int
    {
        return match ($this) {
            self::Starter => 50000,
            self::Pro => 80000,
            self::Hebergement => 10000,
        };
    }

    public function boutiquesMax(): int
    {
        return match ($this) {
            self::Starter => 5,
            self::Pro => 10,
            self::Hebergement => 10,
        };
    }
}
