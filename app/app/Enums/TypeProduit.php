<?php

namespace App\Enums;

enum TypeProduit: string
{
    case IntrantAgricole = 'intrant_agricole';
    case ProduitPhytosanitaire = 'produit_phytosanitaire';

    public function label(): string
    {
        return match ($this) {
            self::IntrantAgricole => 'Intrant agricole',
            self::ProduitPhytosanitaire => 'Produit phytosanitaire',
        };
    }

    /**
     * Les phytosanitaires (pesticides, fongicides, herbicides) sont soumis à
     * une réglementation de traçabilité renforcée (§6 du cahier des charges) :
     * le suivi par lot et date de péremption y est obligatoire.
     */
    public function tracabiliteObligatoire(): bool
    {
        return $this === self::ProduitPhytosanitaire;
    }
}
