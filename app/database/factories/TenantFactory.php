<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'email_contact' => fake()->unique()->companyEmail(),
            'telephone' => fake()->phoneNumber(),
            'plan' => Plan::Pro,
            'abonnement_expire_le' => now()->addMonth(),
            'actif' => true,
        ];
    }

    public function abonnementExpire(): static
    {
        return $this->state(fn (array $attributes) => [
            'abonnement_expire_le' => now()->subDay(),
        ]);
    }
}
