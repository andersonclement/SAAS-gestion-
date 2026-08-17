<?php

namespace App\Http\Requests;

use App\Models\Conditionnement;
use App\Models\Produit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConditionnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Conditionnement::class, $this->route('produit')]);
    }

    public function rules(): array
    {
        /** @var Produit $produit */
        $produit = $this->route('produit');

        return [
            'libelle' => [
                'required', 'string', 'max:60',
                Rule::unique('conditionnements', 'libelle')->where('produit_id', $produit->id),
            ],
            // Facteur 1 accepté : « détail au kilo » est un conditionnement
            // comme un autre, avec son propre prix.
            'facteur' => ['required', 'integer', 'min:1', 'max:100000'],
            'prix_vente' => ['required', 'integer', 'min:0'],
            'par_defaut' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $facteur = (int) $this->input('facteur');
            $prix = (int) $this->input('prix_vente');

            if ($facteur < 1 || Conditionnement::prixCoherent($prix, $facteur)) {
                return;
            }

            // Le prix unitaire doit tomber juste en francs entiers : c'est lui
            // que retiennent les lignes de vente, et donc tous les calculs de
            // chiffre d'affaires et de marge.
            $inferieur = intdiv($prix, $facteur) * $facteur;

            $validator->errors()->add('prix_vente', __(
                'Le prix doit être un multiple de :facteur pour rester exact à l\'unité (:inferieur ou :superieur, par exemple).',
                [
                    'facteur' => $facteur,
                    'inferieur' => number_format($inferieur, 0, ',', ' '),
                    'superieur' => number_format($inferieur + $facteur, 0, ',', ' '),
                ]
            ));
        });
    }
}
