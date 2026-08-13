<?php

namespace App\Http\Requests;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Produit::class);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'type' => ['required', Rule::enum(TypeProduit::class)],
            'unite_mesure' => ['required', Rule::enum(UniteMesure::class)],
            'code_barres' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('produits', 'code_barres')->where('tenant_id', $this->user()->tenant_id),
            ],
            'prix_achat' => ['required', 'integer', 'min:0'],
            'prix_vente' => ['required', 'integer', 'min:0'],

            // Les deux bornes sont posées dès la création : c'est sur elles que
            // reposent l'alerte de seuil bas et le repérage des surstocks.
            'stock_min' => ['required', 'integer', 'min:0'],
            'stock_max' => ['required', 'integer', 'min:1', 'gte:stock_min'],

            // Stock initial : un produit entre au catalogue parce qu'il est
            // physiquement en boutique. Le lot et sa date de péremption sont
            // donc renseignés dans le même geste — indispensable pour des
            // produits phytosanitaires.
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'quantite_initiale' => ['required', 'integer', 'min:1'],
            'numero_lot' => ['required', 'string', 'max:64'],
            'date_fabrication' => ['nullable', 'date', 'before_or_equal:today'],
            'date_peremption' => ['required', 'date', 'after:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $boutiqueUtilisateur = $this->user()->boutique_id;

            // Un gérant n'approvisionne que sa propre boutique.
            if ($boutiqueUtilisateur !== null && (int) $this->input('boutique_id') !== $boutiqueUtilisateur) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez approvisionner que votre boutique.'));
            }

            $fabrication = $this->input('date_fabrication');
            $peremption = $this->input('date_peremption');

            if ($fabrication && $peremption && $fabrication >= $peremption) {
                $validator->errors()->add('date_peremption', __('La date de péremption doit suivre la date de fabrication.'));
            }
        });
    }
}
