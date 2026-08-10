<?php

namespace App\Http\Requests;

use App\Models\StockBoutique;
use App\Models\TransfertStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTransfertStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TransfertStock::class);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'stock_boutique_id' => [
                'required',
                Rule::exists('stock_boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'boutique_destination_id' => [
                'required',
                Rule::exists('boutiques', 'id')->where('tenant_id', $tenantId),
            ],
            'quantite' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stock = StockBoutique::find($this->input('stock_boutique_id'));

            if (! $stock) {
                return;
            }

            $user = $this->user();

            if ($user->boutique_id !== null && $stock->boutique_id !== $user->boutique_id) {
                $validator->errors()->add('stock_boutique_id', __('Vous ne pouvez transférer que depuis votre propre boutique.'));

                return;
            }

            if ((int) $this->input('boutique_destination_id') === $stock->boutique_id) {
                $validator->errors()->add('boutique_destination_id', __('La boutique de destination doit être différente de la boutique source.'));
            }

            if ((int) $this->input('quantite') > $stock->quantite) {
                $validator->errors()->add('quantite', __('Stock insuffisant dans la boutique source (:disponible disponible).', ['disponible' => $stock->quantite]));
            }
        });
    }

    public function stockBoutique(): StockBoutique
    {
        return StockBoutique::findOrFail($this->validated('stock_boutique_id'));
    }
}
