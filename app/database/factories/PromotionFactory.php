<?php

namespace Database\Factories;

use App\Enums\PorteePromotion;
use App\Enums\TypePromotion;
use App\Models\Promotion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
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
            'nom' => fake()->words(3, true),
            'type' => TypePromotion::Pourcentage,
            'valeur' => fake()->numberBetween(5, 30),
            'portee' => PorteePromotion::Globale,
            'date_debut' => now()->subDay(),
            'date_fin' => now()->addMonth(),
            'actif' => true,
            'cree_par_id' => User::factory(),
        ];
    }
}
