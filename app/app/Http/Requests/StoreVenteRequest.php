<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use App\Models\StockBoutique;
use App\Models\Vente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Vente::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'mode_paiement' => ['required', Rule::enum(ModePaiement::class)],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.produit_id' => [
                'required',
                Rule::exists('produits', 'id')->where('tenant_id', $tenantId),
            ],
            'lignes.*.quantite' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $boutiqueId = $this->input('boutique_id');

            if ($user->boutique_id !== null && (int) $boutiqueId !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez vendre que depuis votre propre boutique.'));

                return;
            }

            foreach ($this->input('lignes', []) as $index => $ligne) {
                $disponible = StockBoutique::where('boutique_id', $boutiqueId)
                    ->where('produit_id', $ligne['produit_id'] ?? null)
                    ->sum('quantite');

                if ((int) ($ligne['quantite'] ?? 0) > $disponible) {
                    $validator->errors()->add(
                        "lignes.{$index}.quantite",
                        __('Stock insuffisant dans la boutique source (:disponible disponible).', ['disponible' => $disponible])
                    );
                }
            }
        });
    }
}
