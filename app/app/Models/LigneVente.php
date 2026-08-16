<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LigneVente extends Model
{
    protected $fillable = [
        'vente_id',
        'produit_id',
        'lot_id',
        'quantite',
        'prix_unitaire',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'integer',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function retours(): HasMany
    {
        return $this->hasMany(Retour::class);
    }

    public function sousTotal(): int
    {
        return $this->quantite * $this->prix_unitaire;
    }

    public function quantiteRetournee(): int
    {
        return $this->retours()->sum('quantite');
    }

    public function quantiteRetournable(): int
    {
        return max(0, $this->quantite - $this->quantiteRetournee());
    }
}
