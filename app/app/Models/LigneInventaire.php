<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneInventaire extends Model
{
    protected $fillable = [
        'inventaire_id',
        'produit_id',
        'lot_id',
        'quantite_theorique',
        'quantite_physique',
    ];

    protected function casts(): array
    {
        return [
            'quantite_theorique' => 'integer',
            'quantite_physique' => 'integer',
        ];
    }

    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function ecart(): int
    {
        return $this->quantite_physique - $this->quantite_theorique;
    }
}
