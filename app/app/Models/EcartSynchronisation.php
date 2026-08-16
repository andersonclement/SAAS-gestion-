<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantité qu'une vente encaissée hors-ligne n'a pas pu prélever sur le stock
 * au moment de sa synchronisation (§5).
 *
 * Un écart non résolu signale un stock théorique supérieur au stock réel :
 * il apparaît dans les alertes jusqu'à ce qu'un gérant le traite.
 */
class EcartSynchronisation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'ecarts_synchronisation';

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'vente_id',
        'produit_id',
        'quantite_demandee',
        'quantite_allouee',
        'quantite_manquante',
        'resolu_le',
        'resolu_par',
    ];

    protected function casts(): array
    {
        return [
            'resolu_le' => 'datetime',
        ];
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function resoluPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolu_par');
    }

    public function scopeNonResolus(Builder $query): Builder
    {
        return $query->whereNull('resolu_le');
    }

    public function estResolu(): bool
    {
        return $this->resolu_le !== null;
    }
}
