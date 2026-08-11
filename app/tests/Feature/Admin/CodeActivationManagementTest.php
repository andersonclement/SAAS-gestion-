<?php

namespace Tests\Feature\Admin;

use App\Enums\Plan;
use App\Enums\StatutCodeActivation;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\CodeActivation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CodeActivationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_access_the_admin_dashboard(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_a_tenant_user_cannot_access_the_admin_dashboard(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->get('/admin');

        $response->assertRedirect(route('admin.login'));
    }

    public function test_an_admin_can_log_in_and_generate_an_activation_code(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post('/admin/codes', [
                'plan' => Plan::Starter->value,
                'duree_mois' => 1,
                'notes' => 'Premier client',
            ])
            ->assertRedirect(route('admin.codes.index'));

        $this->assertDatabaseCount('codes_activation', 1);
        $code = CodeActivation::firstOrFail();
        $this->assertSame(Plan::Starter, $code->plan);
        $this->assertSame(StatutCodeActivation::EnAttente, $code->statut);
        $this->assertSame($admin->id, $code->genere_par_admin_id);
        $this->assertStringStartsWith('AGRO-', $code->code);
    }

    public function test_an_admin_can_revoke_a_pending_code(): void
    {
        $admin = Admin::factory()->create();
        $code = CodeActivation::factory()->create(['genere_par_admin_id' => $admin->id]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.codes.revoquer', $code))
            ->assertRedirect();

        $this->assertSame(StatutCodeActivation::Revoque, $code->fresh()->statut);
    }

    public function test_a_revoked_code_cannot_be_used_at_registration(): void
    {
        $code = CodeActivation::factory()->create(['statut' => StatutCodeActivation::Revoque]);

        $response = $this->post('/register', [
            'code_activation' => $code->code,
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('code_activation');
    }

    public function test_the_dashboard_shows_tenant_and_revenue_kpis(): void
    {
        $admin = Admin::factory()->create();
        Tenant::factory()->create(['plan' => Plan::Starter, 'abonnement_expire_le' => now()->addMonth()]);
        Tenant::factory()->create(['plan' => Plan::Pro, 'abonnement_expire_le' => now()->addMonth()]);
        Tenant::factory()->create(['plan' => Plan::Starter, 'abonnement_expire_le' => now()->subDay()]);

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $response->assertOk();
        $response->assertSee('130 000 FCFA');
    }
}
