<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lot extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'produit_id',
        'numero_lot',
        'date_fabrication',
        'date_peremption',
    ];

    protected function casts(): array
    {
        return [
            'date_fabrication' => 'date',
            'date_peremption' => 'date',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function estPerime(): bool
    {
        return $this->date_peremption !== null && $this->date_peremption->isPast();
    }

    public function peremptionProche(int $jours = 30): bool
    {
        return $this->date_peremption !== null
            && ! $this->estPerime()
            && $this->date_peremption->isBefore(now()->addDays($jours));
    }
}
