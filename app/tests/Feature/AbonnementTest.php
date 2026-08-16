<?php

namespace Tests\Feature;

use App\Enums\Plan;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\CodeActivation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_with_an_expired_subscription_is_redirected_to_the_subscription_page(): void
    {
        $tenant = Tenant::factory()->abonnementExpire()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->get('/dashboard');

        $response->assertRedirect(route('abonnement.index'));
    }

    public function test_a_patron_can_still_reach_the_subscription_page_when_expired(): void
    {
        $tenant = Tenant::factory()->abonnementExpire()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->get('/abonnement')->assertOk();
    }

    public function test_a_patron_can_renew_the_subscription_with_a_valid_code(): void
    {
        $tenant = Tenant::factory()->abonnementExpire()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $code = CodeActivation::factory()->create(['plan' => Plan::Pro, 'duree_mois' => 2]);

        $this->actingAs($patron)
            ->post('/abonnement/activer', ['code_activation' => $code->code])
            ->assertRedirect(route('abonnement.index'));

        $tenant->refresh();
        $this->assertSame(Plan::Pro, $tenant->plan);
        $this->assertTrue($tenant->abonnementActif());

        // L'accès normal est restauré.
        $this->actingAs($patron)->get('/dashboard')->assertOk();
    }

    public function test_a_gerant_cannot_activate_a_code(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);
        $code = CodeActivation::factory()->create();

        $this->actingAs($gerant)
            ->post('/abonnement/activer', ['code_activation' => $code->code])
            ->assertForbidden();
    }

    public function test_a_code_reserved_for_another_tenant_cannot_be_used_to_renew(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $patronA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => UserRole::Patron]);
        $codeForB = CodeActivation::factory()->create(['tenant_id' => $tenantB->id]);

        $response = $this->actingAs($patronA)->post('/abonnement/activer', ['code_activation' => $codeForB->code]);

        $response->assertSessionHasErrors('code_activation');
    }

    public function test_a_starter_plan_cannot_exceed_five_boutiques(): void
    {
        $tenant = Tenant::factory()->create(['plan' => Plan::Starter, 'abonnement_expire_le' => now()->addMonth()]);
        Boutique::factory()->count(5)->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->post('/boutiques', [
            'nom' => 'Boutique en trop',
            'adresse' => 'Douala',
        ]);

        $response->assertSessionHasErrors('nom');
        $this->assertSame(5, $tenant->boutiques()->count());
    }
}
