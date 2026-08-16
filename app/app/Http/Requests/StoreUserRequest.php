<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)->except(UserRole::Patron)],
            'boutique_id' => [
                Rule::requiredIf(fn () => in_array($this->input('role'), array_map(fn ($r) => $r->value, UserRole::scopedToBoutique()), true)),
                'nullable',
                Rule::exists('boutiques', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
        ];
    }
}
