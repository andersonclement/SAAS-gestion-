<?php

namespace Database\Factories;

use App\Enums\ModeRemboursement;
use App\Enums\MotifRetour;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\Retour;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Retour>
 */
class RetourFactory extends Factory
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
            'quantite' => fake()->numberBetween(1, 5),
            'motif' => fake()->randomElement(MotifRetour::cases()),
            'remis_en_stock' => fake()->boolean(),
            'montant_avoir' => fake()->numberBetween(500, 20000),
            'mode_remboursement' => fake()->randomElement(ModeRemboursement::cases()),
            'cree_par_id' => User::factory(),
        ];
    }
}
