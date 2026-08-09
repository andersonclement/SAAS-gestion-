<?php

namespace App\Http\Requests;

use App\Models\Boutique;
use Illuminate\Foundation\Http\FormRequest;

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
}
