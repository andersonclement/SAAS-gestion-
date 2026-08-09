<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Isole automatiquement les requêtes sur le tenant de l'utilisateur connecté.
 *
 * Chaque patron dispose d'un espace SaaS indépendant (§4.1 du cahier des
 * charges) : ce trait garantit qu'aucune requête ne peut, par erreur, lire
 * ou écrire des données appartenant à un autre tenant.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', Auth::user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (! $model->tenant_id && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
