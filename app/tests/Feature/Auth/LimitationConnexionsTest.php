<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimitationConnexionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_account_is_locked_after_five_failed_attempts(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $patron->email, 'password' => 'faux'])
                ->assertSessionHasErrors('email');
        }

        // 6e tentative : verrouillée, même avec le BON mot de passe.
        $response = $this->post('/login', ['email' => $patron->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'Trop de tentatives',
            session('errors')->first('email')
        );
    }

    public function test_a_successful_login_clears_the_counter(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', ['email' => $patron->email, 'password' => 'faux']);
        }

        $this->post('/login', ['email' => $patron->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticated();

        $this->post('/logout');

        // Le compteur ayant été remis à zéro, on peut de nouveau échouer 5 fois.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $patron->email, 'password' => 'faux'])
                ->assertSessionHasErrors('email');
        }

        $this->assertStringNotContainsString(
            'Trop de tentatives',
            session('errors')->first('email')
        );
    }

    public function test_the_lockout_is_scoped_to_the_targeted_account(): void
    {
        $tenant = Tenant::factory()->create();
        $cible = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $autre = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $cible->email, 'password' => 'faux']);
        }

        // Un autre utilisateur ne doit pas être pénalisé par ce verrouillage.
        $this->post('/login', ['email' => $autre->email, 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_the_superadmin_login_is_also_rate_limited(): void
    {
        $admin = Admin::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'faux'])
                ->assertSessionHasErrors('email');
        }

        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('email'));
    }
}
