<?php

namespace App\Http\Requests;

use App\Models\BonCommande;
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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user->boutique_id !== null && (int) $this->input('boutique_id') !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez commander que pour votre propre boutique.'));
            }
        });
    }
}
