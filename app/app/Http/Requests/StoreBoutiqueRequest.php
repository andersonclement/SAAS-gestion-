<?php

namespace App\Http\Requests;

use App\Models\Boutique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBoutiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Boutique::class);
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tenant = $this->user()->tenant;

            if (! $tenant->peutAjouterBoutique()) {
                $validator->errors()->add('nom', __(
                    'Votre formule (:plan) est limitée à :max boutiques. Contactez le superadmin pour passer à une formule supérieure.',
                    ['plan' => $tenant->plan?->label(), 'max' => $tenant->boutiquesMax()]
                ));
            }
        });
    }
}
