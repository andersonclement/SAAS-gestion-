<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBoutique>
 */
class StockBoutiqueFactory extends Factory
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
            'produit_id' => Produit::factory(),
            'lot_id' => Lot::factory(),
            'quantite' => fake()->numberBetween(0, 200),
        ];
    }
}
