<?php

namespace Tests\Feature\Admin;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotesEtExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_save_internal_notes_on_a_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.tenants.notes', $tenant), ['notes_internes' => 'Remise négociée à 45 000 F.'])
            ->assertRedirect();

        $this->assertSame('Remise négociée à 45 000 F.', $tenant->fresh()->notes_internes);
    }

    public function test_internal_notes_are_never_visible_to_the_tenant(): void
    {
        $tenant = Tenant::factory()->create(['notes_internes' => 'Client difficile, relancer par téléphone.']);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        foreach (['/dashboard', '/abonnement', '/boutiques'] as $url) {
            $this->actingAs($patron)->get($url)->assertDontSee('Client difficile');
        }
    }

    public function test_a_tenant_user_cannot_write_internal_notes(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)
            ->post(route('admin.tenants.notes', $tenant), ['notes_internes' => 'injection'])
            ->assertRedirect(route('admin.login'));

        $this->assertNull($tenant->fresh()->notes_internes);
    }

    public function test_the_customer_list_can_be_exported_as_csv(): void
    {
        $admin = Admin::factory()->create();
        Tenant::factory()->create([
            'nom' => 'AgroPlus Distribution',
            'plan' => Plan::Pro,
            'abonnement_expire_le' => now()->addMonth(),
        ]);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.tenants.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('AgroPlus Distribution', $csv);
        $this->assertStringContainsString('80000', $csv);
    }

    public function test_a_guest_cannot_export_the_customer_list(): void
    {
        Tenant::factory()->create(['nom' => 'Client confidentiel']);

        $this->get(route('admin.tenants.export'))->assertRedirect(route('admin.login'));
    }
}
