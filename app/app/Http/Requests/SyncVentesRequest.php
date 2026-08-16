<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use App\Models\Vente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Lot de ventes encaissées hors-ligne remontées par le navigateur (§5).
 *
 * La validation reste volontairement structurelle : forme du message,
 * existence des identifiants, appartenance au tenant. Les règles métier qui
 * peuvent avoir changé depuis l'encaissement — disponibilité du stock,
 * plafond de crédit — ne sont pas des motifs de rejet ici : la vente a déjà
 * eu lieu. Elles sont arbitrées à l'enregistrement (voir
 * SynchronisationController).
 */
class SyncVentesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Vente::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            // Un lot borné : un vendeur qui revient après plusieurs jours
            // remonte sa file par tranches plutôt qu'en une requête géante.
            'ventes' => ['required', 'array', 'min:1', 'max:100'],
            'ventes.*.uuid_client' => ['required', 'uuid'],
            'ventes.*.encaissee_le' => ['required', 'date', 'before_or_equal:now'],
            'ventes.*.boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'ventes.*.client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'ventes.*.mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'ventes.*.montant_paye' => ['required', 'integer', 'min:0'],
            'ventes.*.date_echeance' => ['nullable', 'date'],
            'ventes.*.lignes' => ['required', 'array', 'min:1'],
            'ventes.*.lignes.*.produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('tenant_id', $tenantId),
            ],
            'ventes.*.lignes.*.quantite' => ['required', 'integer', 'min:1'],
        ];
    }
}
