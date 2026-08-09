<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_create_a_gerant_scoped_to_a_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($patron)->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
            'boutique_id' => $boutique->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'fatou@agroplus.test',
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'role' => UserRole::Gerant->value,
        ]);
    }

    public function test_creating_a_gerant_without_a_boutique_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->from('/users/create')->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
        ]);

        $response->assertSessionHasErrors('boutique_id');
        $this->assertDatabaseMissing('users', ['email' => 'fatou@agroplus.test']);
    }

    public function test_a_gerant_cannot_create_other_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'role' => UserRole::Gerant,
        ]);

        $response = $this->actingAs($gerant)->post('/users', [
            'name' => 'Intrus',
            'email' => 'intrus@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Vendeur->value,
            'boutique_id' => $boutique->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intrus@agroplus.test']);
    }

    public function test_a_patron_cannot_assign_a_boutique_from_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $foreignBoutique = Boutique::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($patron)->from('/users/create')->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
            'boutique_id' => $foreignBoutique->id,
        ]);

        $response->assertSessionHasErrors('boutique_id');
        $this->assertDatabaseMissing('users', ['email' => 'fatou@agroplus.test']);
    }
}
