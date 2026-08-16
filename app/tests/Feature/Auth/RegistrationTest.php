<?php

namespace Tests\Feature\Auth;

use App\Enums\Plan;
use App\Enums\StatutCodeActivation;
use App\Enums\UserRole;
use App\Models\CodeActivation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_patron_can_create_his_own_tenant_space_with_a_valid_code(): void
    {
        $code = CodeActivation::factory()->create(['plan' => Plan::Starter, 'duree_mois' => 1]);

        $response = $this->post('/register', [
            'code_activation' => $code->code,
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard'));

        $tenant = Tenant::firstOrFail();
        $this->assertSame('AgroPlus', $tenant->nom);
        $this->assertSame(Plan::Starter, $tenant->plan);
        $this->assertTrue($tenant->abonnementActif());

        $patron = $tenant->users()->firstOrFail();
        $this->assertSame(UserRole::Patron, $patron->role);
        $this->assertSame('patron@agroplus.test', $patron->email);

        $code->refresh();
        $this->assertSame(StatutCodeActivation::Utilise, $code->statut);
        $this->assertSame($tenant->id, $code->tenant_id);
        $this->assertSame($patron->id, $code->utilise_par_user_id);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $code = CodeActivation::factory()->create();

        $response = $this->from('/register')->post('/register', [
            'code_activation' => $code->code,
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'autre-chose',
        ]);

        $response->assertRedirect('/register');
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_registration_requires_a_valid_activation_code(): void
    {
        $response = $this->from('/register')->post('/register', [
            'code_activation' => 'AGRO-INVALIDE',
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('code_activation');
        $this->assertGuest();
        $this->assertDatabaseMissing('tenants', ['nom' => 'AgroPlus']);
    }

    public function test_registration_rejects_an_already_used_code(): void
    {
        $code = CodeActivation::factory()->create(['statut' => StatutCodeActivation::Utilise]);

        $response = $this->from('/register')->post('/register', [
            'code_activation' => $code->code,
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('code_activation');
        $this->assertGuest();
    }

    public function test_registration_rejects_a_code_reserved_for_an_existing_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $code = CodeActivation::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->from('/register')->post('/register', [
            'code_activation' => $code->code,
            'entreprise' => 'AgroPlus',
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('code_activation');
        $this->assertGuest();
    }
}
