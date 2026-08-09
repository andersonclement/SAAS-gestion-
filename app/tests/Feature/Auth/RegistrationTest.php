<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_patron_can_create_his_own_tenant_space(): void
    {
        $response = $this->post('/register', [
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

        $patron = $tenant->users()->firstOrFail();
        $this->assertSame(UserRole::Patron, $patron->role);
        $this->assertSame('patron@agroplus.test', $patron->email);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $response = $this->from('/register')->post('/register', [
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
}
