<?php

namespace App\Http\Requests;

use App\Models\CodeActivation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entreprise' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'code_activation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('code_activation')) {
                return;
            }

            $code = $this->codeActivation();

            if (! $code) {
                $validator->errors()->add('code_activation', __("Ce code d'activation est introuvable."));

                return;
            }

            if ($code->tenant_id !== null) {
                $validator->errors()->add('code_activation', __('Ce code est réservé au renouvellement d\'un abonnement existant.'));

                return;
            }

            if (! $code->estUtilisable()) {
                $validator->errors()->add('code_activation', __("Ce code d'activation a déjà été utilisé ou a été révoqué."));
            }
        });
    }

    public function codeActivation(): ?CodeActivation
    {
        return CodeActivation::where('code', trim((string) $this->input('code_activation')))->first();
    }
}
