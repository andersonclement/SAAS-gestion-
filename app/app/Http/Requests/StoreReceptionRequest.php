<?php

namespace App\Http\Requests;

use App\Models\BonCommande;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receptionner', $this->route('bon_commande'));
    }

    public function rules(): array
    {
        return [
            'lignes' => ['required', 'array'],
            'lignes.*.numero_lot' => ['required', 'string', 'max:100'],
            'lignes.*.date_fabrication' => ['nullable', 'date'],
            'lignes.*.date_peremption' => ['nullable', 'date', 'after:lignes.*.date_fabrication'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var BonCommande $bonCommande */
            $bonCommande = $this->route('bon_commande');
            $lignesPayload = $this->input('lignes', []);

            foreach ($bonCommande->lignes as $ligne) {
                if (! $ligne->produit->type->tracabiliteObligatoire()) {
                    continue;
                }

                if (empty($lignesPayload[$ligne->id]['date_peremption'] ?? null)) {
                    $validator->errors()->add(
                        "lignes.{$ligne->id}.date_peremption",
                        __('La date de péremption est obligatoire pour un produit phytosanitaire.')
                    );
                }
            }
        });
    }
}
