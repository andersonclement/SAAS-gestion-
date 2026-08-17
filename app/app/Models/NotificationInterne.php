<?php

namespace App\Models;

use App\Enums\ImportanceNotification;
use App\Enums\TypeNotification;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une alerte adressée à quelqu'un, qui reste ouverte tant qu'elle n'est pas
 * traitée — par opposition à la page « Alertes », qui recalcule un état
 * instantané à chaque affichage.
 */
class NotificationInterne extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'notifications_internes';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'boutique_id',
        'type',
        'importance',
        'cle',
        'titre',
        'message',
        'lien',
        'lu_le',
        'resolue_le',
        'rappele_le',
        'nombre_rappels',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeNotification::class,
            'importance' => ImportanceNotification::class,
            'lu_le' => 'datetime',
            'resolue_le' => 'datetime',
            'rappele_le' => 'datetime',
            'nombre_rappels' => 'integer',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    /** Situations encore d'actualité. */
    public function scopeOuvertes(Builder $query): Builder
    {
        return $query->whereNull('resolue_le');
    }

    public function scopeNonLues(Builder $query): Builder
    {
        return $query->whereNull('lu_le');
    }

    public function scopePour(Builder $query, User $utilisateur): Builder
    {
        return $query->where('user_id', $utilisateur->id);
    }

    /**
     * Tri d'affichage : le plus grave d'abord, puis le plus récent. Les rappels
     * remontent naturellement, leur `updated_at` étant rafraîchi.
     */
    public function scopeParUrgence(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE importance WHEN 'critique' THEN 0 WHEN 'avertissement' THEN 1 ELSE 2 END")
            ->latest('updated_at');
    }

    public function estLue(): bool
    {
        return $this->lu_le !== null;
    }

    public function estRappel(): bool
    {
        return $this->nombre_rappels > 0;
    }

    public function marquerLue(): void
    {
        if (! $this->estLue()) {
            $this->forceFill(['lu_le' => now()])->save();
        }
    }
}
