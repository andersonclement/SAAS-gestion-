<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    public function rules(): array
    {
        $cible = $this->route('user');

        $regles = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($cible->id)],
            // Le mot de passe n'est renseigné que pour le réinitialiser ;
            // laissé vide, l'ancien reste valable.
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'alertes_email' => ['nullable', 'boolean'],
        ];

        // Le compte patron est le propriétaire de l'espace : le rétrograder ou
        // le rattacher à une boutique le priverait de la vue consolidée. Son
        // rôle n'est donc simplement pas modifiable.
        if ($cible->isPatron()) {
            return $regles;
        }

        return $regles + [
            'role' => ['required', Rule::enum(UserRole::class)->except(UserRole::Patron)],
            'boutique_id' => [
                Rule::requiredIf(fn () => in_array($this->input('role'), array_map(fn ($r) => $r->value, UserRole::scopedToBoutique()), true)),
                'nullable',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Une case à cocher absente du formulaire vaut « non cochée ».
        $this->merge(['alertes_email' => $this->boolean('alertes_email')]);
    }
}
