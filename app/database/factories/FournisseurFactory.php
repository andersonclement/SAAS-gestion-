<?php

namespace Database\Factories;

use App\Models\Fournisseur;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fournisseur>
 */
class FournisseurFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nom' => fake()->company(),
            'contact' => fake()->name(),
            'telephone' => fake()->phoneNumber(),
            'adresse' => fake()->address(),
        ];
    }
}
