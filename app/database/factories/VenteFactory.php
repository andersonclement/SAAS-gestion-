<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vente>
 */
class VenteFactory extends Factory
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
            'vendeur_id' => User::factory(),
            'numero' => 'VT-'.fake()->unique()->numerify('######'),
        ];
    }
}
