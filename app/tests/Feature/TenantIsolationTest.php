<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chaque patron dispose d'un espace SaaS totalement isolé des autres
 * (§4.1 du cahier des charges). Ces tests vérifient que le scope tenant
 * empêche toute fuite de données entre deux comptes patron distincts.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_only_sees_boutiques_from_his_own_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        Boutique::factory()->create(['tenant_id' => $tenantA->id, 'nom' => 'Boutique A']);
        Boutique::factory()->create(['tenant_id' => $tenantB->id, 'nom' => 'Boutique B']);

        $response = $this->actingAs($patronA)->get('/boutiques');

        $response->assertOk();
        $response->assertSee('Boutique A');
        $response->assertDontSee('Boutique B');
    }

    public function test_a_patron_cannot_view_a_boutique_belonging_to_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenantB->id]);

        // La global scope masque la boutique d'un autre tenant : elle est
        // introuvable (404), jamais exposée avec un 403 qui confirmerait
        // son existence à un tiers.
        $response = $this->actingAs($patronA)->get("/boutiques/{$boutiqueB->id}");

        $response->assertNotFound();
    }

    public function test_a_gerant_only_sees_his_own_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique A']);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique B']);

        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutiqueA->id,
            'role' => UserRole::Gerant,
        ]);

        $response = $this->actingAs($gerant)->get('/boutiques');

        $response->assertOk();
        $response->assertSee('Boutique A');
        $response->assertDontSee('Boutique B');
    }

    public function test_only_a_patron_can_create_a_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'role' => UserRole::Gerant,
        ]);

        $response = $this->actingAs($gerant)->post('/boutiques', [
            'nom' => 'Nouvelle boutique',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('boutiques', ['nom' => 'Nouvelle boutique']);
    }

    public function test_a_created_boutique_is_automatically_scoped_to_the_creating_patron_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->post('/boutiques', [
            'nom' => 'Boutique Sud',
            'adresse' => 'Yamoussoukro',
        ])->assertRedirect(route('boutiques.index'));

        $this->assertDatabaseHas('boutiques', [
            'nom' => 'Boutique Sud',
            'tenant_id' => $tenant->id,
        ]);
    }
}
