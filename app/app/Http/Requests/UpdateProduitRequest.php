<?php

namespace App\Http\Requests;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProduitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('produit'));
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
                // Le produit modifié est exclu du contrôle d'unicité, sinon
                // il entrerait en conflit avec son propre code-barres.
                Rule::unique('produits', 'code_barres')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->ignore($this->route('produit')),
            ],
            'prix_achat' => ['required', 'integer', 'min:0'],
            // Prix au détail (à l'unité de mesure). Vide = produit non
            // détaillable : il ne se vendra qu'en formats entiers.
            'prix_vente' => ['nullable', 'integer', 'min:0'],
            'stock_min' => ['required', 'integer', 'min:0'],
            'stock_max' => ['required', 'integer', 'min:1', 'gte:stock_min'],
            'actif' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Une case décochée n'est pas envoyée par le navigateur : sans cela,
        // il serait impossible de désactiver un produit.
        $this->merge(['actif' => $this->boolean('actif')]);
    }
}
