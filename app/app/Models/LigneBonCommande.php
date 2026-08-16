<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LigneBonCommande extends Model
{
    protected $fillable = [
        'bon_commande_id',
        'produit_id',
        'conditionnement_id',
        'quantite',
        'prix_unitaire',
        'lot_id',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'prix_unitaire' => 'integer',
        ];
    }

    public function bonCommande(): BelongsTo
    {
        return $this->belongsTo(BonCommande::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function conditionnement(): BelongsTo
    {
        return $this->belongsTo(Conditionnement::class);
    }

    /**
     * Quantité telle qu'elle a été commandée : « 20 × Sac de 50 kg = 1000 kg ».
     */
    public function quantiteLisible(): string
    {
        $unite = $this->produit->unite_mesure->value;

        if (! $this->conditionnement) {
            return "{$this->quantite} {$unite}";
        }

        $nombre = $this->conditionnement->nombrePour($this->quantite);

        return $nombre === null
            ? "{$this->quantite} {$unite} ({$this->conditionnement->libelle})"
            : "{$nombre} × {$this->conditionnement->libelle} = {$this->quantite} {$unite}";
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function estRecue(): bool
    {
        return $this->lot_id !== null;
    }

    public function sousTotal(): int
    {
        return $this->quantite * $this->prix_unitaire;
    }
}
