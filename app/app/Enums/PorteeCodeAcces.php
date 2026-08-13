<?php

namespace App\Enums;

/**
 * Ce qu'un code d'accès autorise son porteur à faire.
 *
 * Le patron agit toujours de plein droit ; ces portées ne concernent que les
 * gérants, à qui le patron délègue ponctuellement une opération sensible.
 */
enum PorteeCodeAcces: string
{
    /** Créer et modifier des fiches produits, stock initial compris. */
    case Catalogue = 'catalogue';

    /** Ajouter du stock à un produit déjà au catalogue. */
    case Approvisionnement = 'approvisionnement';

    /** Les deux à la fois. */
    case Complet = 'complet';

    public function label(): string
    {
        return match ($this) {
            self::Catalogue => __('Ajout de produits'),
            self::Approvisionnement => __('Approvisionnement du stock'),
            self::Complet => __('Produits et approvisionnement'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Catalogue => __('Créer des fiches produits et enregistrer leur stock initial.'),
            self::Approvisionnement => __('Ajouter du stock aux produits déjà présents au catalogue.'),
            self::Complet => __('Créer des produits et approvisionner le stock de la boutique.'),
        };
    }

    /**
     * Un code « complet » couvre les deux portées ciblées ; les autres ne
     * couvrent qu'elles-mêmes.
     */
    public function couvre(self $besoin): bool
    {
        return $this === $besoin || $this === self::Complet;
    }
}
