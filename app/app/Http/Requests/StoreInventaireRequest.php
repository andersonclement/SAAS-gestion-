<?php

namespace App\Http\Requests;

use App\Models\Inventaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInventaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Inventaire::class);
    }

    public function rules(): array
    {
        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*' => ['required', 'array'],
            'lignes.*.quantite_physique' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user->boutique_id !== null && (int) $this->input('boutique_id') !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez inventorier que votre propre boutique.'));
            }
        });
    }
}
