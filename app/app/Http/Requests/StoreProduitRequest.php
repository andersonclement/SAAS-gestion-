<?php

namespace App\Http\Requests;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'seuil_alerte' => ['required', 'integer', 'min:0'],
        ];
    }
}
