<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un format de vente d'un produit : « sac de 50 kg », « bidon de 5 L »,
 * « détail au kilo ».
 *
 * Le facteur est le nombre d'unités de base contenues dans le conditionnement.
 * Le stock, lui, reste toujours compté en unités de base — un conditionnement
 * ne crée pas de stock séparé, il ne fait que changer la façon de compter
 * à la caisse.
 */
class Conditionnement extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'produit_id',
        'libelle',
        'facteur',
        'prix_vente',
        'par_defaut',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'facteur' => 'integer',
            'prix_vente' => 'integer',
            'par_defaut' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    /**
     * Prix ramené à l'unité de base. Exact par construction : le prix du
     * conditionnement est contraint à être un multiple du facteur, précisément
     * pour que cette division tombe juste. Tous les calculs de chiffre
     * d'affaires et de marge de l'application reposent sur
     * `quantite * prix_unitaire` — cette exactitude est ce qui permet
     * d'introduire les conditionnements sans toucher à aucun d'eux.
     */
    public function prixUnitaire(): int
    {
        return intdiv($this->prix_vente, $this->facteur);
    }

    /**
     * Le prix du conditionnement est-il représentable au prix unitaire entier ?
     */
    public static function prixCoherent(int $prixVente, int $facteur): bool
    {
        return $facteur > 0 && $prixVente % $facteur === 0;
    }

    /**
     * Nombre de conditionnements que représente une quantité en unité de base,
     * quand elle tombe juste. Une vente répartie sur plusieurs lots peut
     * produire des lignes qui ne correspondent pas à un compte entier : on
     * n'affiche alors que la quantité brute.
     */
    public function nombrePour(int $quantiteBase): ?int
    {
        return $quantiteBase % $this->facteur === 0
            ? intdiv($quantiteBase, $this->facteur)
            : null;
    }

    public function libelleComplet(): string
    {
        return "{$this->libelle} ({$this->facteur} {$this->produit->unite_mesure->value})";
    }
}
