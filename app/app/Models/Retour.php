<?php

namespace App\Models;

use App\Enums\ModeRemboursement;
use App\Enums\MotifRetour;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Retour extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'ligne_vente_id',
        'produit_id',
        'lot_id',
        'quantite',
        'motif',
        'remis_en_stock',
        'montant_avoir',
        'mode_remboursement',
        'cree_par_id',
    ];

    protected function casts(): array
    {
        return [
            'motif' => MotifRetour::class,
            'mode_remboursement' => ModeRemboursement::class,
            'remis_en_stock' => 'boolean',
            'quantite' => 'integer',
            'montant_avoir' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Retour $retour): void {
            $retour->cree_par_id ??= Auth::id();
        });
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function ligneVente(): BelongsTo
    {
        return $this->belongsTo(LigneVente::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }
}
