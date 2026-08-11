<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Versement de caisse (§4.10) : trace l'argent physiquement collecté par
 * le patron auprès d'un gérant, pour que le solde de caisse théorique de
 * la boutique reste cohérent avec l'argent réellement présent sur place.
 */
class VersementCaisse extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'versements_caisse';

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'remis_par_id',
        'remis_par_nom',
        'montant',
        'date',
        'description',
        'cree_par_id',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (VersementCaisse $versement): void {
            $versement->cree_par_id ??= Auth::id();
        });
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function remisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'remis_par_id');
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }
}
