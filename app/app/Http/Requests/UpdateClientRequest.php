<?php

namespace App\Http\Requests;

use App\Enums\TypeClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isComptable();
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TypeClient::class)],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'plafond_credit' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $client = $this->route('client');
            $plafond = $this->input('plafond_credit');

            if ($plafond === null || $plafond === '') {
                return;
            }

            // Abaisser le plafond sous la dette déjà engagée placerait le
            // client en dépassement immédiat : on l'interdit plutôt que de
            // laisser une situation incohérente s'installer.
            $dette = $client->soldeDette();

            if ((int) $plafond < $dette) {
                $validator->errors()->add('plafond_credit', __(
                    'Ce client doit déjà :dette FCFA : le plafond ne peut pas être inférieur.',
                    ['dette' => number_format($dette, 0, ',', ' ')]
                ));
            }
        });
    }
}
