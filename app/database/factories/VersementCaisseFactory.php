<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VersementCaisse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VersementCaisse>
 */
class VersementCaisseFactory extends Factory
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
            'boutique_id' => Boutique::factory(),
            'remis_par_id' => User::factory(),
            'remis_par_nom' => fake()->name(),
            'montant' => fake()->numberBetween(5000, 200000),
            'date' => now(),
            'cree_par_id' => User::factory(),
        ];
    }
}
