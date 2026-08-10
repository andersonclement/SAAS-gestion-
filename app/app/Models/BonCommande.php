<?php

namespace App\Models;

use App\Enums\StatutBonCommande;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class BonCommande extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'fournisseur_id',
        'cree_par_id',
        'numero',
        'statut',
        'recu_le',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutBonCommande::class,
            'recu_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BonCommande $bonCommande): void {
            $bonCommande->cree_par_id ??= Auth::id();
            $bonCommande->numero ??= static::prochainNumero($bonCommande->tenant_id ?? Auth::user()?->tenant_id);
        });
    }

    protected static function prochainNumero(?int $tenantId): string
    {
        $dernier = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

        return sprintf('BC-%06d', $dernier + 1);
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneBonCommande::class);
    }

    public function estRecu(): bool
    {
        return $this->statut === StatutBonCommande::Recu;
    }

    public function montantTotal(): int
    {
        return $this->lignes->sum(fn (LigneBonCommande $ligne) => $ligne->quantite * $ligne->prix_unitaire);
    }
}
