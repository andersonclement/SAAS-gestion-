<?php

namespace App\Http\Requests;

use App\Enums\CategorieDepense;
use App\Models\Depense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDepenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Depense::class);
    }

    public function rules(): array
    {
        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'categorie' => ['required', Rule::enum(CategorieDepense::class)],
            'montant' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user->boutique_id !== null && (int) $this->input('boutique_id') !== $user->boutique_id) {
                $validator->errors()->add('boutique_id', __('Vous ne pouvez enregistrer une dépense que pour votre propre boutique.'));
            }
        });
    }
}
