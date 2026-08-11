<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Vente extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'client_id',
        'vendeur_id',
        'numero',
        'date_echeance',
    ];

    protected function casts(): array
    {
        return [
            'date_echeance' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vente $vente): void {
            $vente->vendeur_id ??= Auth::id();
            $vente->numero ??= static::prochainNumero($vente->tenant_id ?? Auth::user()?->tenant_id);
        });
    }

    protected static function prochainNumero(?int $tenantId): string
    {
        $dernier = static::withoutGlobalScopes()->where('tenant_id', $tenantId)->count();

        return sprintf('VT-%06d', $dernier + 1);
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LigneVente::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function montantTotal(): int
    {
        return $this->lignes->sum(fn (LigneVente $ligne) => $ligne->sousTotal());
    }

    public function montantPaye(): int
    {
        return $this->paiements->sum('montant');
    }

    public function montantDu(): int
    {
        return max(0, $this->montantTotal() - $this->montantPaye());
    }

    public function estACredit(): bool
    {
        return $this->date_echeance !== null;
    }

    public function estSoldee(): bool
    {
        return $this->montantDu() === 0;
    }

    public function estEnRetard(): bool
    {
        return $this->estACredit() && ! $this->estSoldee() && $this->date_echeance->isPast();
    }
}
