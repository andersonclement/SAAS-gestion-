<?php

namespace Database\Factories;

use App\Models\Conditionnement;
use App\Models\Produit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conditionnement>
 */
class ConditionnementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $facteur = fake()->randomElement([1, 5, 10, 25, 50]);

        return [
            'tenant_id' => Tenant::factory(),
            'produit_id' => Produit::factory(),
            'libelle' => "Sac de {$facteur}",
            'facteur' => $facteur,
            // Multiple du facteur : le prix unitaire doit tomber juste.
            'prix_vente' => $facteur * fake()->numberBetween(100, 1000),
            'par_defaut' => false,
            'actif' => true,
        ];
    }
}
