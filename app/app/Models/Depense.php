<?php

namespace App\Models;

use App\Enums\CategorieDepense;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Depense extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'boutique_id',
        'cree_par_id',
        'categorie',
        'montant',
        'description',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieDepense::class,
            'montant' => 'integer',
            'date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Depense $depense): void {
            $depense->cree_par_id ??= Auth::id();
            $depense->date ??= now()->toDateString();
        });
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }
}
