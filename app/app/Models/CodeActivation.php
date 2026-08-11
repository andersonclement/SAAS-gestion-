<?php

namespace App\Models;

use App\Enums\Plan;
use App\Enums\StatutCodeActivation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Code d'abonnement généré par le superadmin et communiqué au patron
 * (hors application) pour activer ou renouveler l'accès de son tenant à
 * la plateforme, sur un plan et une durée donnés.
 */
class CodeActivation extends Model
{
    use HasFactory;

    protected $table = 'codes_activation';

    protected $fillable = [
        'code',
        'plan',
        'duree_mois',
        'statut',
        'tenant_id',
        'genere_par_admin_id',
        'utilise_par_user_id',
        'utilise_le',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'plan' => Plan::class,
            'statut' => StatutCodeActivation::class,
            'duree_mois' => 'integer',
            'utilise_le' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CodeActivation $codeActivation): void {
            $codeActivation->code ??= static::genererCode();
        });
    }

    /**
     * Alphabet sans les caractères ambigus à la lecture (0/O, 1/I/L) :
     * les codes sont recopiés à la main par le patron depuis un message.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function genererCode(): string
    {
        do {
            // Str::random() puis Str::upper() ferait perdre de l'entropie en
            // repliant les minuscules sur les majuscules : on tire donc
            // directement dans l'alphabet cible, avec une source aléatoire
            // cryptographique (random_int).
            $segments = collect(range(1, 8))
                ->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])
                ->join('');

            $code = 'AGRO-'.substr($segments, 0, 4).'-'.substr($segments, 4, 4);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function generePar(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'genere_par_admin_id');
    }

    public function utiliseParUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilise_par_user_id');
    }

    public function estUtilisable(): bool
    {
        return $this->statut === StatutCodeActivation::EnAttente;
    }

    /**
     * Consomme le code pour activer/renouveler l'abonnement d'un tenant :
     * applique le plan du code et prolonge la date d'expiration à partir
     * d'aujourd'hui ou de l'expiration en cours si elle n'est pas encore
     * dépassée (un renouvellement anticipé ne fait pas perdre de temps payé).
     */
    public function activerPour(Tenant $tenant, User $user): void
    {
        $depart = $tenant->abonnementActif() ? Carbon::parse($tenant->abonnement_expire_le) : Carbon::today();

        $tenant->update([
            'plan' => $this->plan,
            'abonnement_expire_le' => $depart->addMonths($this->duree_mois),
        ]);

        $this->update([
            'statut' => StatutCodeActivation::Utilise,
            'tenant_id' => $tenant->id,
            'utilise_par_user_id' => $user->id,
            'utilise_le' => now(),
        ]);
    }
}
