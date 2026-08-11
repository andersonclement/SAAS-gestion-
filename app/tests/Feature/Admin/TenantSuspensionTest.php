<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSuspensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_suspend_a_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create(['actif' => true]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.tenants.toggle-actif', $tenant))
            ->assertRedirect();

        $this->assertFalse($tenant->fresh()->actif);
    }

    public function test_an_admin_can_reactivate_a_suspended_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create(['actif' => false]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.tenants.toggle-actif', $tenant))
            ->assertRedirect();

        $this->assertTrue($tenant->fresh()->actif);
    }

    public function test_a_user_of_a_suspended_tenant_cannot_log_in(): void
    {
        $tenant = Tenant::factory()->create(['actif' => false]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->from('/login')->post('/login', [
            'email' => $patron->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_an_active_session_is_logged_out_when_the_tenant_gets_suspended(): void
    {
        $tenant = Tenant::factory()->create(['actif' => true]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->get('/dashboard')->assertOk();

        $tenant->update(['actif' => false]);

        // ->fresh() : une vraie requête HTTP suivante recharge toujours
        // l'utilisateur et sa relation tenant depuis la base ; on simule
        // cette indépendance plutôt que de réutiliser l'instance PHP mise
        // en cache par le premier appel actingAs().
        $response = $this->actingAs($patron->fresh())->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
