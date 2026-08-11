<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnexionSurveillanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_successful_tenant_login_is_recorded(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->post('/login', ['email' => $patron->email, 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('tentatives_connexion', [
            'email' => $patron->email,
            'reussie' => true,
            'role' => 'tenant',
            'user_id' => $patron->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_a_failed_tenant_login_is_recorded_without_a_resolved_user(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->post('/login', ['email' => $patron->email, 'password' => 'mauvais-mot-de-passe']);

        $this->assertDatabaseHas('tentatives_connexion', [
            'email' => $patron->email,
            'reussie' => false,
            'role' => 'tenant',
            'user_id' => null,
        ]);
    }

    public function test_a_successful_admin_login_is_recorded_with_the_admin_role(): void
    {
        $admin = Admin::factory()->create(['email' => 'staff@gestion-stock.test']);

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('tentatives_connexion', [
            'email' => 'staff@gestion-stock.test',
            'reussie' => true,
            'role' => 'admin',
        ]);
    }

    public function test_a_suspended_tenant_login_attempt_is_recorded_as_failed(): void
    {
        $tenant = Tenant::factory()->create(['actif' => false]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->post('/login', ['email' => $patron->email, 'password' => 'password']);

        $this->assertDatabaseHas('tentatives_connexion', [
            'email' => $patron->email,
            'reussie' => false,
            'user_id' => $patron->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_the_admin_can_view_and_filter_the_connexions_log(): void
    {
        $superadmin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->post('/login', ['email' => $patron->email, 'password' => 'password']);
        $this->post('/login', ['email' => $patron->email, 'password' => 'faux']);

        $response = $this->actingAs($superadmin, 'admin')->get(route('admin.connexions.index'));
        $response->assertOk();
        $response->assertSee($patron->email);

        $reussies = $this->actingAs($superadmin, 'admin')->get(route('admin.connexions.index', ['statut' => 'reussie']));
        $reussies->assertOk();

        $echouees = $this->actingAs($superadmin, 'admin')->get(route('admin.connexions.index', ['statut' => 'echouee']));
        $echouees->assertOk();
    }

    public function test_a_tenant_user_cannot_access_the_connexions_log(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->get(route('admin.connexions.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_the_tenant_overview_shows_the_last_login_per_user(): void
    {
        $superadmin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron, 'name' => 'Adama Koné']);

        $this->post('/login', ['email' => $patron->email, 'password' => 'password']);

        $response = $this->actingAs($superadmin, 'admin')->get(route('admin.tenants.show', $tenant));

        $response->assertOk();
        $response->assertDontSee('Jamais connecté');
    }
}
