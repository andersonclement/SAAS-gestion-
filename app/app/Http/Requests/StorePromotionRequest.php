<?php

namespace App\Http\Requests;

use App\Enums\PorteePromotion;
use App\Enums\TypePromotion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPatron();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TypePromotion::class)],
            'valeur' => [
                'required',
                'integer',
                'min:1',
                $this->input('type') === TypePromotion::Pourcentage->value ? 'max:100' : 'max:1000000',
            ],
            'portee' => ['required', Rule::enum(PorteePromotion::class)],
            'produit_id' => [
                Rule::requiredIf($this->input('portee') === PorteePromotion::Produit->value),
                'nullable',
                Rule::exists('produits', 'id')->where('tenant_id', $tenantId),
            ],
            'categorie_id' => [
                Rule::requiredIf($this->input('portee') === PorteePromotion::Categorie->value),
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'client_id' => [
                Rule::requiredIf($this->input('portee') === PorteePromotion::Client->value),
                'nullable',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
        ];
    }
}
