<?php

namespace App\Http\Requests;

use App\Enums\PorteeCodeAcces;
use App\Enums\UserRole;
use App\Models\CodeAcces;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCodeAccesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CodeAcces::class);
    }

    public function rules(): array
    {
        return [
            'destinataire_user_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('role', UserRole::Gerant->value)
                    ->where('actif', true),
            ],
            'portee' => ['required', Rule::enum(PorteeCodeAcces::class)],
            // Une délégation se compte en heures ou en jours, pas en mois : au
            // delà, elle cesse d'être une exception et devient un droit permanent.
            'duree_heures' => ['required', 'integer', 'min:1', 'max:720'],
            'motif' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('destinataire_user_id')) {
                return;
            }

            $gerant = User::where('tenant_id', $this->user()->tenant_id)
                ->find($this->input('destinataire_user_id'));

            // Le code porte sur la boutique du gérant : sans boutique, il n'y
            // a rien à approvisionner.
            if ($gerant && $gerant->boutique_id === null) {
                $validator->errors()->add('destinataire_user_id', __(
                    "Ce gérant n'est rattaché à aucune boutique. Rattachez-le d'abord depuis l'équipe."
                ));
            }
        });
    }
}
