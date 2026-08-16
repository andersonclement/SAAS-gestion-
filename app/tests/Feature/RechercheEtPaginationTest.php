<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechercheEtPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function patron(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
    }

    public function test_products_can_be_searched_by_name_and_barcode(): void
    {
        $patron = $this->patron();
        Produit::factory()->create(['tenant_id' => $patron->tenant_id, 'nom' => 'Engrais NPK', 'code_barres' => '111']);
        Produit::factory()->create(['tenant_id' => $patron->tenant_id, 'nom' => 'Glyphosate', 'code_barres' => '222']);

        $parNom = $this->actingAs($patron)->get('/produits?q=Engrais');
        $parNom->assertSee('Engrais NPK');
        $parNom->assertDontSee('Glyphosate');

        $parCode = $this->actingAs($patron)->get('/produits?q=222');
        $parCode->assertSee('Glyphosate');
        $parCode->assertDontSee('Engrais NPK');
    }

    public function test_the_product_list_is_paginated(): void
    {
        $patron = $this->patron();
        Produit::factory()->count(25)->create(['tenant_id' => $patron->tenant_id]);

        $response = $this->actingAs($patron)->get('/produits');

        $response->assertOk();
        // 20 par page : la 2e page doit exister.
        $response->assertSee('page=2', false);
    }

    public function test_customers_can_be_searched(): void
    {
        $patron = $this->patron();
        Client::factory()->create(['tenant_id' => $patron->tenant_id, 'nom' => 'Coopérative du Nord']);
        Client::factory()->create(['tenant_id' => $patron->tenant_id, 'nom' => 'Ferme Sud']);

        $response = $this->actingAs($patron)->get('/clients?q=Nord');

        $response->assertSee('Coopérative du Nord');
        $response->assertDontSee('Ferme Sud');
    }

    public function test_sales_can_be_filtered_by_date_range(): void
    {
        $patron = $this->patron();

        $ancienne = Vente::factory()->create(['tenant_id' => $patron->tenant_id, 'numero' => 'VT-ANCIENNE']);
        $ancienne->forceFill(['created_at' => now()->subDays(30)])->save();

        Vente::factory()->create(['tenant_id' => $patron->tenant_id, 'numero' => 'VT-RECENTE']);

        $response = $this->actingAs($patron)->get('/ventes?du='.now()->subDays(2)->toDateString());

        $response->assertSee('VT-RECENTE');
        $response->assertDontSee('VT-ANCIENNE');
    }

    public function test_sales_can_be_searched_by_number(): void
    {
        $patron = $this->patron();
        Vente::factory()->create(['tenant_id' => $patron->tenant_id, 'numero' => 'VT-000042']);
        Vente::factory()->create(['tenant_id' => $patron->tenant_id, 'numero' => 'VT-000099']);

        $response = $this->actingAs($patron)->get('/ventes?q=000042');

        $response->assertSee('VT-000042');
        $response->assertDontSee('VT-000099');
    }

    public function test_a_search_never_leaks_data_from_another_tenant(): void
    {
        $patron = $this->patron();
        $autreTenant = Tenant::factory()->create();
        Produit::factory()->create(['tenant_id' => $autreTenant->id, 'nom' => 'Produit du voisin']);

        $response = $this->actingAs($patron)->get('/produits?q=voisin');

        $response->assertOk();
        $response->assertDontSee('Produit du voisin');
    }
}
