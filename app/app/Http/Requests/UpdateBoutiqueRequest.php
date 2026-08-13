<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBoutiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boutique'));
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            // Voir StoreBoutiqueRequest : la localisation est structurante.
            'adresse' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
