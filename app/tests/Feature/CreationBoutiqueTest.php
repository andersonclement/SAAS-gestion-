<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreationBoutiqueTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
    }

    public function test_the_location_is_required(): void
    {
        $patron = $this->patron();

        $this->actingAs($patron)->from('/boutiques/create')->post('/boutiques', [
            'nom' => 'Boutique sans adresse',
        ])->assertSessionHasErrors('adresse');

        $this->assertDatabaseMissing('boutiques', ['nom' => 'Boutique sans adresse']);
    }

    public function test_the_patron_can_attach_an_existing_gerant_when_creating_the_boutique(): void
    {
        $patron = $this->patron();
        $gerant = User::factory()->create([
            'tenant_id' => $patron->tenant_id, 'role' => UserRole::Gerant, 'boutique_id' => null,
        ]);

        $this->actingAs($patron)->post('/boutiques', [
            'nom' => 'Boutique Bonabéri',
            'adresse' => 'Bonabéri, Douala',
            'gerant_id' => $gerant->id,
        ])->assertRedirect();

        $boutique = Boutique::where('nom', 'Boutique Bonabéri')->firstOrFail();
        $this->assertSame($boutique->id, $gerant->fresh()->boutique_id);
        $this->assertSame('Bonabéri, Douala', $boutique->adresse);
    }

    public function test_the_patron_can_create_the_gerant_account_along_with_the_boutique(): void
    {
        $patron = $this->patron();

        $this->actingAs($patron)->post('/boutiques', [
            'nom' => 'Boutique Mvog-Mbi',
            'adresse' => 'Mvog-Mbi, Yaoundé',
            'gerant_nom' => 'Serge Ateba',
            'gerant_email' => 'serge@agroplus.test',
            'gerant_mot_de_passe' => 'mot-de-passe-sur',
            'gerant_mot_de_passe_confirmation' => 'mot-de-passe-sur',
        ])->assertRedirect();

        $boutique = Boutique::where('nom', 'Boutique Mvog-Mbi')->firstOrFail();
        $gerant = User::where('email', 'serge@agroplus.test')->firstOrFail();

        $this->assertSame(UserRole::Gerant, $gerant->role);
        $this->assertSame($boutique->id, $gerant->boutique_id);
        $this->assertSame($patron->tenant_id, $gerant->tenant_id);
        $this->assertTrue(Hash::check('mot-de-passe-sur', $gerant->password));
    }

    public function test_an_incomplete_gerant_account_is_rejected(): void
    {
        $patron = $this->patron();

        $this->actingAs($patron)->from('/boutiques/create')->post('/boutiques', [
            'nom' => 'Boutique Akwa',
            'adresse' => 'Akwa, Douala',
            'gerant_nom' => 'Serge Ateba',
        ])->assertSessionHasErrors(['gerant_email', 'gerant_mot_de_passe']);

        $this->assertDatabaseMissing('boutiques', ['nom' => 'Boutique Akwa']);
    }

    public function test_a_gerant_from_another_tenant_cannot_be_attached(): void
    {
        $patron = $this->patron();
        $etranger = User::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id, 'role' => UserRole::Gerant, 'boutique_id' => null,
        ]);

        $this->actingAs($patron)->from('/boutiques/create')->post('/boutiques', [
            'nom' => 'Boutique Akwa',
            'adresse' => 'Akwa, Douala',
            'gerant_id' => $etranger->id,
        ])->assertSessionHasErrors('gerant_id');

        $this->assertNull($etranger->fresh()->boutique_id);
    }

    public function test_the_creation_is_recorded_in_the_activity_log(): void
    {
        $patron = $this->patron();

        $this->actingAs($patron)->post('/boutiques', [
            'nom' => 'Boutique Bafoussam',
            'adresse' => 'Marché A, Bafoussam',
            'gerant_nom' => 'Alice Nana',
            'gerant_email' => 'alice@agroplus.test',
            'gerant_mot_de_passe' => 'mot-de-passe-sur',
            'gerant_mot_de_passe_confirmation' => 'mot-de-passe-sur',
        ])->assertRedirect();

        $this->assertDatabaseHas('journal_activites', ['action' => 'boutique.creee']);
        $this->assertDatabaseHas('journal_activites', ['action' => 'boutique.gerant_rattache']);
    }
}
