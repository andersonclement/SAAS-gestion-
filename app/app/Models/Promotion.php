<?php

namespace App\Models;

use App\Enums\PorteePromotion;
use App\Enums\TypePromotion;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Promotion extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nom',
        'type',
        'valeur',
        'portee',
        'produit_id',
        'categorie_id',
        'client_id',
        'date_debut',
        'date_fin',
        'actif',
        'cree_par_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypePromotion::class,
            'portee' => PorteePromotion::class,
            'valeur' => 'integer',
            'date_debut' => 'date',
            'date_fin' => 'date',
            'actif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Promotion $promotion): void {
            $promotion->cree_par_id ??= Auth::id();
        });
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function estActive(): bool
    {
        $aujourdhui = Carbon::today();

        return $this->actif
            && ! $this->date_debut->gt($aujourdhui)
            && ! $this->date_fin->lt($aujourdhui);
    }

    public function sApplique(Produit $produit, ?Client $client): bool
    {
        return match ($this->portee) {
            PorteePromotion::Globale => true,
            PorteePromotion::Produit => $this->produit_id === $produit->id,
            PorteePromotion::Categorie => $this->categorie_id !== null && $this->categorie_id === $produit->categorie_id,
            PorteePromotion::Client => $client !== null && $this->client_id === $client->id,
        };
    }

    /**
     * Montant de la remise, par unité, pour un prix donné.
     */
    public function remisePour(int $prixUnitaire): int
    {
        $remise = $this->type === TypePromotion::Pourcentage
            ? (int) round($prixUnitaire * $this->valeur / 100)
            : $this->valeur;

        return min($remise, $prixUnitaire);
    }
}
