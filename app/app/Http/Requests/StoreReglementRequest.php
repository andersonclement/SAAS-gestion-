<?php

namespace App\Http\Requests;

use App\Enums\ModePaiement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReglementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('vente')) && ! $this->user()->isComptable();
    }

    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(ModePaiement::class)],
            'montant' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $vente = $this->route('vente');

            if ((int) $this->input('montant') > $vente->montantDu()) {
                $validator->errors()->add('montant', __(
                    'Le règlement (:montant FCFA) dépasse le solde dû (:solde FCFA).',
                    ['montant' => $this->input('montant'), 'solde' => $vente->montantDu()]
                ));
            }
        });
    }
}
