<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TransfertStock extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'transferts_stock';

    protected $fillable = [
        'tenant_id',
        'produit_id',
        'lot_id',
        'boutique_source_id',
        'boutique_destination_id',
        'quantite',
        'cree_par_id',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TransfertStock $transfert): void {
            $transfert->cree_par_id ??= Auth::id();
        });
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function boutiqueSource(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_source_id');
    }

    public function boutiqueDestination(): BelongsTo
    {
        return $this->belongsTo(Boutique::class, 'boutique_destination_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }
}
