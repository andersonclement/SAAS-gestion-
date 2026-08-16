<?php

namespace App\Support;

use App\Models\TentativeConnexion;
use App\Models\User;
use Illuminate\Support\Facades\Request;

/**
 * Trace chaque tentative de connexion (§ surveillance du trafic) pour le
 * superadmin : contrairement au Journal d'activité (qui suppose un
 * utilisateur déjà authentifié), ceci capture aussi bien les connexions
 * réussies que les échecs, avec l'adresse IP et l'appareil utilisés.
 */
class SuiviConnexions
{
    public static function enregistrer(string $email, bool $reussie, string $role, ?User $user = null): void
    {
        TentativeConnexion::create([
            'email' => $email,
            'reussie' => $reussie,
            'role' => $role,
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'adresse_ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
