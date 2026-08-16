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
        'conditionnement_id',
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

    public function conditionnement(): BelongsTo
    {
        return $this->belongsTo(Conditionnement::class);
    }

    /**
     * Quantité telle qu'elle parle au client : « 2 sac de 50 kg » plutôt que
     * « 100 kg ». Une vente répartie sur plusieurs lots peut donner une ligne
     * qui ne fait pas un compte entier de conditionnements — on s'en tient
     * alors à la quantité brute, qui reste exacte.
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
