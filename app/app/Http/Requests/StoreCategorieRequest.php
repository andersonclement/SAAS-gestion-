<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPatron() || $this->user()->isGerant();
    }

    public function rules(): array
    {
        return [
            'nom' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'nom')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
