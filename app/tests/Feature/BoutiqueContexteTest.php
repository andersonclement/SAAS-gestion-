<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoutiqueContexteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_switch_to_a_single_boutique_view(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'AgroPlus Centre-ville']);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'AgroPlus Zone Nord']);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->post('/boutique-contexte', ['boutique_id' => $boutiqueA->id])
            ->assertRedirect();

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee(route('boutiques.show', $boutiqueA));
        $response->assertDontSee(route('boutiques.show', $boutiqueB));
    }

    public function test_a_patron_can_return_to_the_all_shops_view(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'AgroPlus Centre-ville']);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'AgroPlus Zone Nord']);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->post('/boutique-contexte', ['boutique_id' => $boutiqueA->id]);
        $this->actingAs($patron)->post('/boutique-contexte', ['boutique_id' => null]);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('AgroPlus Centre-ville');
        $response->assertSee('AgroPlus Zone Nord');
    }

    public function test_a_gerant_cannot_switch_the_boutique_context(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);

        $response = $this->actingAs($gerant)->post('/boutique-contexte', ['boutique_id' => $boutique->id]);

        $response->assertForbidden();
    }

    public function test_a_patron_cannot_select_a_boutique_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenantB->id]);
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patronA)->post('/boutique-contexte', ['boutique_id' => $boutiqueB->id]);

        $response->assertNotFound();
    }
}
