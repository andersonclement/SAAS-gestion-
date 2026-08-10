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
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeClient::class,
        ];
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }
}
