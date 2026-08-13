<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Boutique;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
            // La localisation n'est pas facultative : c'est elle qui distingue
            // deux boutiques du même patron dans les alertes, les rapports et
            // sur les factures remises aux clients.
            'adresse' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],

            // Rattachement d'un gérant existant, encore sans boutique.
            'gerant_id' => [
                'nullable',
                Rule::exists('users', 'id')
                    ->where('tenant_id', $this->user()->tenant_id)
                    ->where('role', UserRole::Gerant->value),
            ],

            // …ou création du compte gérant dans la foulée.
            'gerant_nom' => ['nullable', 'string', 'max:255'],
            'gerant_email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'gerant_mot_de_passe' => ['nullable', 'confirmed', Password::defaults()],
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

            $creeUnGerant = $this->filled('gerant_nom') || $this->filled('gerant_email') || $this->filled('gerant_mot_de_passe');

            if ($creeUnGerant && $this->filled('gerant_id')) {
                $validator->errors()->add('gerant_id', __(
                    'Rattachez un gérant existant ou créez-en un, mais pas les deux.'
                ));

                return;
            }

            // Un compte incomplet ne pourrait pas se connecter : les trois
            // champs vont ensemble.
            if ($creeUnGerant) {
                foreach (['gerant_nom', 'gerant_email', 'gerant_mot_de_passe'] as $champ) {
                    if (! $this->filled($champ)) {
                        $validator->errors()->add($champ, __('Ce champ est requis pour créer le compte du gérant.'));
                    }
                }
            }
        });
    }
}
