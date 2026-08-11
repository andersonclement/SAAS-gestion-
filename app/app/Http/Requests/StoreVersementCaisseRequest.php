<?php

namespace App\Http\Requests;

use App\Models\VersementCaisse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVersementCaisseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', VersementCaisse::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'boutique_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'remis_par_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('boutique_id', $this->input('boutique_id')),
            ],
            'montant' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
