<?php

namespace App\Models;

use App\Enums\TypeClient;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nom',
        'type',
        'telephone',
        'email',
        'adresse',
        'plafond_credit',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeClient::class,
            'plafond_credit' => 'integer',
        ];
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    /**
     * Solde de dette en temps réel (§4.9) : somme des montants restant dus
     * sur toutes les ventes de ce client, calculée à partir des lignes de
     * vente et des règlements déjà enregistrés (pas de champ dupliqué à
     * maintenir en cohérence).
     */
    public function soldeDette(): int
    {
        $totalVentes = LigneVente::whereHas('vente', fn ($query) => $query->where('client_id', $this->id))
            ->selectRaw('SUM(quantite * prix_unitaire) as total')
            ->value('total') ?? 0;

        $totalRegle = Paiement::whereHas('vente', fn ($query) => $query->where('client_id', $this->id))
            ->sum('montant');

        return max(0, $totalVentes - $totalRegle);
    }

    public function creditDisponible(): ?int
    {
        if ($this->plafond_credit === null) {
            return null;
        }

        return max(0, $this->plafond_credit - $this->soldeDette());
    }
}
