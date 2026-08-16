<?php

namespace Database\Factories;

use App\Enums\CategorieDepense;
use App\Models\Boutique;
use App\Models\Depense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depense>
 */
class DepenseFactory extends Factory
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
            'categorie' => fake()->randomElement(CategorieDepense::cases()),
            'montant' => fake()->numberBetween(1000, 100000),
            'description' => fake()->sentence(4),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
