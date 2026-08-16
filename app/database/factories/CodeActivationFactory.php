<?php

namespace Database\Factories;

use App\Enums\Plan;
use App\Enums\StatutCodeActivation;
use App\Models\Admin;
use App\Models\CodeActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeActivation>
 */
class CodeActivationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => CodeActivation::genererCode(),
            'plan' => Plan::Starter,
            'duree_mois' => 1,
            'statut' => StatutCodeActivation::EnAttente,
            'genere_par_admin_id' => Admin::factory(),
        ];
    }
}
