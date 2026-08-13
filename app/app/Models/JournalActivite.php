<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalActivite extends Model
{
    use BelongsToTenant;

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'utilisateur_nom',
        'boutique_id',
        'code_acces_id',
        'action',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    /**
     * Code d'accès sous couvert duquel l'opération a été réalisée, le cas
     * échéant : renseigné quand un gérant agit grâce à une délégation du
     * patron, nul pour les opérations ordinaires.
     */
    public function codeAcces(): BelongsTo
    {
        return $this->belongsTo(CodeAcces::class);
    }
}
