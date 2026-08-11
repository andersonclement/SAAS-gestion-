<?php

namespace Tests\Feature\Admin;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantFilterAndGlobalJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tenant_list_can_be_filtered_by_subscription_status(): void
    {
        $admin = Admin::factory()->create();
        $actif = Tenant::factory()->create(['nom' => 'Actif SARL', 'plan' => Plan::Starter, 'abonnement_expire_le' => now()->addMonth()]);
        $expire = Tenant::factory()->create(['nom' => 'Expire SARL', 'plan' => Plan::Starter, 'abonnement_expire_le' => now()->subDay()]);
        $suspendu = Tenant::factory()->create(['nom' => 'Suspendu SARL', 'actif' => false]);

        $reponseActifs = $this->actingAs($admin, 'admin')->get(route('admin.tenants.index', ['statut' => 'actif']));
        $reponseActifs->assertSee('Actif SARL');
        $reponseActifs->assertDontSee('Expire SARL');
        $reponseActifs->assertDontSee('Suspendu SARL');

        $reponseExpires = $this->actingAs($admin, 'admin')->get(route('admin.tenants.index', ['statut' => 'expire']));
        $reponseExpires->assertSee('Expire SARL');
        $reponseExpires->assertDontSee('Actif SARL');

        $reponseSuspendus = $this->actingAs($admin, 'admin')->get(route('admin.tenants.index', ['statut' => 'suspendu']));
        $reponseSuspendus->assertSee('Suspendu SARL');
        $reponseSuspendus->assertDontSee('Actif SARL');
        $reponseSuspendus->assertDontSee('Expire SARL');
    }

    public function test_the_global_journal_lists_activity_across_tenants_and_can_be_filtered_by_tenant(): void
    {
        $admin = Admin::factory()->create();
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $patronB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => UserRole::Patron]);

        $this->actingAs($patronA);
        Journal::enregistrer('test.action', 'Action du tenant A');
        $this->actingAs($patronB);
        Journal::enregistrer('test.action', 'Action du tenant B');

        $global = $this->actingAs($admin, 'admin')->get(route('admin.journal.index'));
        $global->assertOk();
        $global->assertSee('Action du tenant A');
        $global->assertSee('Action du tenant B');

        $filtre = $this->actingAs($admin, 'admin')->get(route('admin.journal.index', ['tenant_id' => $tenantA->id]));
        $filtre->assertOk();
        $filtre->assertSee('Action du tenant A');
        $filtre->assertDontSee('Action du tenant B');
    }

    public function test_a_tenant_user_cannot_access_the_global_journal(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->get(route('admin.journal.index'));

        $response->assertRedirect(route('admin.login'));
    }
}
