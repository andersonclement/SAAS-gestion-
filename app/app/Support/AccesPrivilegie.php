<?php

namespace App\Support;

use App\Enums\PorteeCodeAcces;
use App\Models\CodeAcces;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Accès temporaire ouvert par un gérant grâce au code que lui a remis son
 * patron.
 *
 * L'identifiant du code est conservé en session, mais sa validité est
 * revérifiée à chaque appel — sans mise en cache : une révocation par le
 * patron ou l'échéance du code doit fermer l'accès immédiatement, sans
 * attendre une reconnexion.
 */
class AccesPrivilegie
{
    public const CLE_SESSION = 'code_acces_id';

    public static function ouvrir(CodeAcces $code): void
    {
        Session::put(self::CLE_SESSION, $code->id);
    }

    public static function fermer(): void
    {
        Session::forget(self::CLE_SESSION);
    }

    /**
     * Code d'accès actuellement ouvert, ou null. Un code devenu invalide est
     * retiré de la session au passage.
     */
    public static function actif(): ?CodeAcces
    {
        $id = Session::get(self::CLE_SESSION);
        $user = Auth::guard('web')->user();

        if ($id === null || $user === null) {
            return null;
        }

        // Sans portée de tenant : la vérification d'appartenance est faite
        // explicitement juste après, et une session ouverte doit pouvoir être
        // invalidée même dans un contexte sans utilisateur résolu.
        $code = CodeAcces::withoutGlobalScopes()->find($id);

        if ($code === null || ! $code->utilisablePar($user)) {
            Session::forget(self::CLE_SESSION);

            return null;
        }

        return $code;
    }

    public static function couvre(PorteeCodeAcces $besoin): bool
    {
        return self::actif()?->portee->couvre($besoin) ?? false;
    }
}
