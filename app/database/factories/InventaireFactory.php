<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Inventaire;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventaire>
 */
class InventaireFactory extends Factory
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
            'cree_par_id' => User::factory(),
        ];
    }
}
