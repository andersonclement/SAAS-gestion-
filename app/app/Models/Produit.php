<?php

namespace App\Models;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Produit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'categorie_id',
        'nom',
        'type',
        'unite_mesure',
        'code_barres',
        'prix_achat',
        'prix_vente',
        'seuil_alerte',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeProduit::class,
            'unite_mesure' => UniteMesure::class,
            'prix_achat' => 'integer',
            'prix_vente' => 'integer',
            'seuil_alerte' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }
}
