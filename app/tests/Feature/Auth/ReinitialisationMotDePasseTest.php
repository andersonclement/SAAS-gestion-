<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReinitialisationMotDePasseTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
    }

    public function test_the_forgotten_password_screen_is_reachable(): void
    {
        $this->get('/mot-de-passe-oublie')->assertOk();
    }

    public function test_a_reset_link_is_emailed_to_a_known_account(): void
    {
        Notification::fake();
        $patron = $this->patron();

        $this->post('/mot-de-passe-oublie', ['email' => $patron->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($patron, ResetPassword::class);
    }

    public function test_an_unknown_address_gets_the_same_answer_and_no_email(): void
    {
        Notification::fake();

        $response = $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@exemple.test']);

        // Réponse volontairement identique : révéler qu'une adresse est
        // inconnue permettrait d'énumérer les comptes de la plateforme.
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_a_user_can_choose_a_new_password_with_a_valid_token(): void
    {
        Notification::fake();
        $patron = $this->patron();

        $this->post('/mot-de-passe-oublie', ['email' => $patron->email]);

        Notification::assertSentTo($patron, ResetPassword::class, function (ResetPassword $notification) use ($patron) {
            $this->post('/reinitialiser-mot-de-passe', [
                'token' => $notification->token,
                'email' => $patron->email,
                'password' => 'nouveau-mot-de-passe-sur',
                'password_confirmation' => 'nouveau-mot-de-passe-sur',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-sur', $patron->fresh()->password));

        $this->post('/login', ['email' => $patron->email, 'password' => 'nouveau-mot-de-passe-sur'])
            ->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $patron = $this->patron();

        $response = $this->post('/reinitialiser-mot-de-passe', [
            'token' => 'jeton-bidon',
            'email' => $patron->email,
            'password' => 'nouveau-mot-de-passe-sur',
            'password_confirmation' => 'nouveau-mot-de-passe-sur',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $patron->fresh()->password));
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        Notification::fake();
        $patron = $this->patron();

        $this->post('/mot-de-passe-oublie', ['email' => $patron->email]);

        Notification::assertSentTo($patron, ResetPassword::class, function (ResetPassword $notification) use ($patron) {
            $this->post('/reinitialiser-mot-de-passe', [
                'token' => $notification->token,
                'email' => $patron->email,
                'password' => 'nouveau-mot-de-passe-sur',
                'password_confirmation' => 'autre-chose',
            ])->assertSessionHasErrors('password');

            return true;
        });

        $this->assertTrue(Hash::check('password', $patron->fresh()->password));
    }
}
