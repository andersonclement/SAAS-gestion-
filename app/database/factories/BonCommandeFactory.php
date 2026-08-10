<?php

namespace Database\Factories;

use App\Models\BonCommande;
use App\Models\Boutique;
use App\Models\Fournisseur;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonCommande>
 */
class BonCommandeFactory extends Factory
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
            'fournisseur_id' => Fournisseur::factory(),
            'cree_par_id' => User::factory(),
            'numero' => 'BC-'.fake()->unique()->numerify('######'),
            'statut' => 'brouillon',
        ];
    }
}
