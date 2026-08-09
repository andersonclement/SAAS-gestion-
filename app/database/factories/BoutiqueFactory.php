<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Boutique>
 */
class BoutiqueFactory extends Factory
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
            'nom' => fake()->city().' — '.fake()->streetName(),
            'adresse' => fake()->address(),
            'telephone' => fake()->phoneNumber(),
            'actif' => true,
        ];
    }
}
