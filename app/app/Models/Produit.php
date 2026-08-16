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
        'stock_min',
        'stock_max',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeProduit::class,
            'unite_mesure' => UniteMesure::class,
            'prix_achat' => 'integer',
            'prix_vente' => 'integer',
            'stock_min' => 'integer',
            'stock_max' => 'integer',
            'actif' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }
}
