<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interface_defaults_to_french(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Connexion');
    }

    public function test_a_user_can_switch_the_interface_to_english(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->put('/locale/en')->assertRedirect();

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertDontSee('Tableau de bord');
    }

    public function test_the_chosen_locale_persists_across_requests(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->put('/locale/en');

        $response = $this->actingAs($patron)->get('/boutiques');

        $response->assertOk();
        $response->assertSee('Shops');
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->put('/locale/de')->assertNotFound();
    }
}
