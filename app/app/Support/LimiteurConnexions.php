<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Verrouillage progressif des tentatives de connexion (anti-force brute).
 *
 * La clé combine l'e-mail visé ET l'adresse IP : bloquer sur le seul
 * e-mail permettrait à un tiers de verrouiller volontairement le compte
 * d'un patron (déni de service), et bloquer sur la seule IP pénaliserait
 * toute une boutique partageant une connexion internet.
 */
class LimiteurConnexions
{
    private const MAX_TENTATIVES = 5;

    private const BLOCAGE_SECONDES = 60;

    public static function cle(string $email, string $garde): string
    {
        return 'connexion:'.$garde.'|'.Str::lower($email).'|'.Request::ip();
    }

    /**
     * À appeler AVANT de tenter l'authentification : lève une erreur de
     * validation lisible (avec le délai restant) si le seuil est atteint.
     */
    public static function verifier(string $email, string $garde): void
    {
        $cle = static::cle($email, $garde);

        if (! RateLimiter::tooManyAttempts($cle, self::MAX_TENTATIVES)) {
            return;
        }

        $secondes = RateLimiter::availableIn($cle);

        throw ValidationException::withMessages([
            'email' => __('Trop de tentatives de connexion. Réessayez dans :secondes secondes.', [
                'secondes' => $secondes,
            ]),
        ]);
    }

    public static function enregistrerEchec(string $email, string $garde): void
    {
        RateLimiter::hit(static::cle($email, $garde), self::BLOCAGE_SECONDES);
    }

    public static function reinitialiser(string $email, string $garde): void
    {
        RateLimiter::clear(static::cle($email, $garde));
    }
}
