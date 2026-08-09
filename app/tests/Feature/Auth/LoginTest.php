<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_authenticate_with_correct_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'mot-de-passe-sur',
            'role' => UserRole::Patron,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-sur',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_authentication_fails_with_wrong_password(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_deactivated_account_cannot_authenticate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => 'mot-de-passe-sur',
            'actif' => false,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
