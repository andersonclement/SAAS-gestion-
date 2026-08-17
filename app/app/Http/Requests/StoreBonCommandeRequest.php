<?php

namespace App\Http\Requests;

use App\Models\BonCommande;
use App\Models\Conditionnement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBonCommandeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BonCommande::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'fournisseur_id' => [
                'required',
                Rule::exists('fournisseurs', 'id')->where('tenant_id', $tenantId),
            ],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('tenant_id', $tenantId),
            ],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
            'lignes.*.prix_unitaire' => ['required', 'integer', 'min:0'],
            // Format acheté. Absent = achat à l'unité de base, comme avant.
            'lignes.*.conditionnement_id' => [
                'nullable',
                Rule::exists('conditionnements', 'id')->where('tenant_id', $tenantId)->where('actif', true),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user->boutique_id !== null && (int) $this->input('boutique_id') !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez commander que pour votre propre boutique.'));
            }

            foreach ($this->input('lignes', []) as $index => $ligne) {
                if (empty($ligne['conditionnement_id'])) {
                    continue;
                }

                $conditionnement = Conditionnement::find($ligne['conditionnement_id']);

                if (! $conditionnement) {
                    continue;
                }

                if ((int) $conditionnement->produit_id !== (int) ($ligne['produit_id'] ?? 0)) {
                    $validator->errors()->add(
                        "lignes.{$index}.conditionnement_id",
                        __('Ce format ne correspond pas au produit choisi.')
                    );

                    continue;
                }

                // Le prix saisi est celui d'un format entier ; le stock et les
                // marges raisonnent à l'unité de base. Il doit donc se diviser
                // exactement, sinon chaque réception perdrait quelques francs.
                $prix = (int) ($ligne['prix_unitaire'] ?? 0);

                if (! Conditionnement::prixCoherent($prix, $conditionnement->facteur)) {
                    $validator->errors()->add(
                        "lignes.{$index}.prix_unitaire",
                        __("Le prix d'achat du format doit être un multiple de :facteur pour rester exact à l'unité.", [
                            'facteur' => $conditionnement->facteur,
                        ])
                    );
                }
            }
        });
    }
}
