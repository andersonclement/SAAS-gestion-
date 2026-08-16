<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\TransfertStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransfertStock>
 */
class TransfertStockFactory extends Factory
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
            'lot_id' => Lot::factory(),
            'boutique_source_id' => Boutique::factory(),
            'boutique_destination_id' => Boutique::factory(),
            'quantite' => fake()->numberBetween(1, 50),
            'cree_par_id' => User::factory(),
        ];
    }
}
