<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_patron_can_create_a_gerant_scoped_to_a_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($patron)->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
            'boutique_id' => $boutique->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'fatou@agroplus.test',
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'role' => UserRole::Gerant->value,
        ]);
    }

    public function test_creating_a_gerant_without_a_boutique_fails_validation(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $response = $this->actingAs($patron)->from('/users/create')->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
        ]);

        $response->assertSessionHasErrors('boutique_id');
        $this->assertDatabaseMissing('users', ['email' => 'fatou@agroplus.test']);
    }

    public function test_a_gerant_cannot_create_other_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'role' => UserRole::Gerant,
        ]);

        $response = $this->actingAs($gerant)->post('/users', [
            'name' => 'Intrus',
            'email' => 'intrus@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Vendeur->value,
            'boutique_id' => $boutique->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'intrus@agroplus.test']);
    }

    public function test_a_patron_can_change_the_role_and_boutique_of_an_account(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutiqueA = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $boutiqueB = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutiqueA->id,
        ]);

        $ancienMotDePasse = $vendeur->password;

        $response = $this->actingAs($patron)->put("/users/{$vendeur->id}", [
            'name' => 'Awa Koné',
            'email' => $vendeur->email,
            'role' => UserRole::Gerant->value,
            'boutique_id' => $boutiqueB->id,
            'alertes_email' => '1',
        ]);

        $response->assertRedirect(route('users.index'));
        $vendeur->refresh();
        $this->assertSame('Awa Koné', $vendeur->name);
        $this->assertSame(UserRole::Gerant, $vendeur->role);
        $this->assertSame($boutiqueB->id, $vendeur->boutique_id);
        $this->assertTrue($vendeur->alertes_email);
        // Champ mot de passe laissé vide : l'ancien reste valable.
        $this->assertSame($ancienMotDePasse, $vendeur->password);
    }

    public function test_the_password_is_only_replaced_when_provided(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $this->actingAs($patron)->put("/users/{$vendeur->id}", [
            'name' => $vendeur->name,
            'email' => $vendeur->email,
            'role' => UserRole::Vendeur->value,
            'boutique_id' => $boutique->id,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $vendeur->fresh()->password));
    }

    public function test_the_patron_account_cannot_be_downgraded(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->put("/users/{$patron->id}", [
            'name' => 'Patron renommé',
            'email' => $patron->email,
            'role' => UserRole::Vendeur->value,
            'boutique_id' => $boutique->id,
        ])->assertRedirect(route('users.index'));

        $patron->refresh();
        $this->assertSame(UserRole::Patron, $patron->role);
        $this->assertNull($patron->boutique_id);
        // Le reste de la fiche est bien enregistré.
        $this->assertSame('Patron renommé', $patron->name);
    }

    public function test_a_patron_can_deactivate_and_reactivate_an_account(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $this->actingAs($patron)->post("/users/{$vendeur->id}/activation")->assertRedirect(route('users.index'));
        $this->assertFalse($vendeur->fresh()->actif);

        // Le compte désactivé ne peut plus ouvrir de session.
        Auth::logout();
        $this->post('/login', ['email' => $vendeur->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($patron)->post("/users/{$vendeur->id}/activation")->assertRedirect(route('users.index'));
        $this->assertTrue($vendeur->fresh()->actif);
    }

    public function test_a_patron_cannot_deactivate_his_own_account(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->post("/users/{$patron->id}/activation")->assertForbidden();
        $this->assertTrue($patron->fresh()->actif);
    }

    public function test_a_patron_cannot_touch_an_account_of_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $autreTenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $etranger = User::factory()->create(['tenant_id' => $autreTenant->id, 'role' => UserRole::Vendeur]);

        $this->actingAs($patron)->get("/users/{$etranger->id}/edit")->assertForbidden();
        $this->actingAs($patron)->post("/users/{$etranger->id}/activation")->assertForbidden();
        $this->actingAs($patron)->put("/users/{$etranger->id}", [
            'name' => 'Piraté', 'email' => $etranger->email, 'role' => UserRole::Comptable->value,
        ])->assertForbidden();

        $this->assertSame(UserRole::Vendeur, $etranger->fresh()->role);
    }

    public function test_a_gerant_cannot_edit_accounts(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $this->actingAs($gerant)->get("/users/{$vendeur->id}/edit")->assertForbidden();
        $this->actingAs($gerant)->post("/users/{$vendeur->id}/activation")->assertForbidden();
        // Y compris sa propre fiche : il pourrait sinon s'attribuer un autre rôle.
        $this->actingAs($gerant)->get("/users/{$gerant->id}/edit")->assertForbidden();
    }

    public function test_the_team_list_can_be_filtered_by_role_and_status(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Comptable, 'name' => 'Comptable Yao',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id, 'name' => 'Vendeur actif',
        ]);
        User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id, 'name' => 'Vendeur parti', 'actif' => false,
        ]);

        $this->actingAs($patron)->get('/users?role='.UserRole::Vendeur->value)
            ->assertSee('Vendeur actif')
            ->assertSee('Vendeur parti')
            ->assertDontSee('Comptable Yao');

        $this->actingAs($patron)->get('/users?statut=inactif')
            ->assertSee('Vendeur parti')
            ->assertDontSee('Vendeur actif');

        $this->actingAs($patron)->get('/users?q=parti')
            ->assertSee('Vendeur parti')
            ->assertDontSee('Vendeur actif');
    }

    public function test_a_patron_cannot_assign_a_boutique_from_another_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $foreignBoutique = Boutique::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($patron)->from('/users/create')->post('/users', [
            'name' => 'Fatou Diabaté',
            'email' => 'fatou@agroplus.test',
            'password' => 'mot-de-passe-sur',
            'password_confirmation' => 'mot-de-passe-sur',
            'role' => UserRole::Gerant->value,
            'boutique_id' => $foreignBoutique->id,
        ]);

        $response->assertSessionHasErrors('boutique_id');
        $this->assertDatabaseMissing('users', ['email' => 'fatou@agroplus.test']);
    }
}
