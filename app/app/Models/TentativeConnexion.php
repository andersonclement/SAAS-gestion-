<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace brute de chaque tentative de connexion (tenant ou superadmin),
 * réussie ou non. Visible uniquement côté superadmin (voir
 * Admin\ConnexionController) : ce n'est pas un modèle multi-tenant, donc
 * volontairement sans trait BelongsToTenant.
 */
class TentativeConnexion extends Model
{
    const UPDATED_AT = null;

    protected $table = 'tentatives_connexion';

    protected $fillable = [
        'email',
        'reussie',
        'role',
        'user_id',
        'tenant_id',
        'adresse_ip',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'reussie' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
