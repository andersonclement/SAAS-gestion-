<?php

namespace Database\Factories;

use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vente>
 */
class VenteFactory extends Factory
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
            // La boutique et le vendeur suivent le tenant de la vente : sinon
            // ils appartiendraient à un tenant distinct, les relations
            // seraient filtrées par le scope multi-tenant et reviendraient
            // nulles — produisant des données de test incohérentes.
            'boutique_id' => fn (array $attributs) => Boutique::factory()->create([
                'tenant_id' => $attributs['tenant_id'],
            ]),
            'vendeur_id' => fn (array $attributs) => User::factory()->create([
                'tenant_id' => $attributs['tenant_id'],
            ]),
            'numero' => 'VT-'.fake()->unique()->numerify('######'),
        ];
    }
}
