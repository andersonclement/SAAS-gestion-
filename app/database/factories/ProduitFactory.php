<?php

namespace Database\Factories;

use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Models\Produit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Produit>
 */
class ProduitFactory extends Factory
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
            'nom' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(TypeProduit::cases()),
            'unite_mesure' => fake()->randomElement(UniteMesure::cases()),
            'code_barres' => fake()->unique()->ean13(),
            'prix_achat' => fake()->numberBetween(500, 20000),
            'prix_vente' => fake()->numberBetween(500, 25000),
            'seuil_alerte' => fake()->numberBetween(5, 50),
            'actif' => true,
        ];
    }
}
