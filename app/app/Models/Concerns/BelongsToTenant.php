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
 *
 * Le guard "web" est ciblé explicitement (plutôt que le guard par défaut)
 * car le superadmin s'authentifie via un guard "admin" séparé : ce dernier
 * n'a pas de tenant_id, et l'espace admin doit justement pouvoir consulter
 * les données de n'importe quel tenant (en filtrant lui-même par tenant_id).
 * Dès qu'une session admin est active, le scope est désactivé entièrement :
 * l'espace admin est aveugle par défaut et ne voit un tenant que parce que
 * ses contrôleurs le demandent explicitement (paramètre de route), jamais
 * via une identité tenant qui traînerait par ailleurs dans la session.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::guard('admin')->check()) {
                return;
            }

            if (Auth::guard('web')->check()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', Auth::guard('web')->user()->tenant_id);
            }
        });

        static::creating(function ($model) {
            if (Auth::guard('admin')->check()) {
                return;
            }

            if (! $model->tenant_id && Auth::guard('web')->check()) {
                $model->tenant_id = Auth::guard('web')->user()->tenant_id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
