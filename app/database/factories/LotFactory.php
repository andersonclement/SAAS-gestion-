<?php

namespace Database\Factories;

use App\Models\Lot;
use App\Models\Produit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lot>
 */
class LotFactory extends Factory
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
            'produit_id' => Produit::factory(),
            'numero_lot' => fake()->unique()->bothify('LOT-####??'),
            'date_fabrication' => fake()->dateTimeBetween('-6 months', '-1 month'),
            'date_peremption' => fake()->dateTimeBetween('+6 months', '+2 years'),
        ];
    }
}
