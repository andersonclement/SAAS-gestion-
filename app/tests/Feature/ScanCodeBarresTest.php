<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Scan du code-barres au comptoir. Le décodage lui-même se joue dans le
 * navigateur ; ce qui se vérifie ici est ce dont il dépend côté serveur : le
 * code présent dans la page, et la caméra autorisée par l'en-tête.
 */
class ScanCodeBarresTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_caisse_expose_le_code_barres_de_chaque_produit(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);
        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id, 'code_barres' => '3017620422003', 'actif' => true,
        ]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id, 'boutique_id' => $boutique->id,
            'produit_id' => $produit->id, 'lot_id' => $lot->id, 'quantite' => 5,
        ]);

        $reponse = $this->actingAs($vendeur)->get('/ventes/create');

        $reponse->assertOk();
        $reponse->assertSee('data-code-barres="3017620422003"', false);
        $reponse->assertSee(__('Scannez, ou tapez le code puis Entrée'));
    }

    public function test_le_code_barres_d_un_autre_tenant_n_est_pas_dans_la_page(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        Produit::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id, 'code_barres' => '9999999999999',
        ]);

        $this->actingAs($vendeur)->get('/ventes/create')
            ->assertOk()
            ->assertDontSee('9999999999999');
    }

    public function test_la_boutique_unique_du_vendeur_est_deja_choisie(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Yaoundé Centre']);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $reponse = $this->actingAs($vendeur)->get('/ventes/create');

        // Sans cela, le formulaire refuse de partir juste après une série de
        // scans, sans autre explication qu'une bulle du navigateur.
        $reponse->assertOk();

        // « — Choisir — » figure aussi dans le mode de paiement : l'assertion
        // porte donc sur le seul <select> des boutiques.
        preg_match('/<select id="boutique_id".*?<\/select>/s', $reponse->getContent(), $select);
        $this->assertMatchesRegularExpression('/value="'.$boutique->id.'"\s+selected/', $select[0]);
        $this->assertStringNotContainsString(__('Choisir'), $select[0]);
    }

    public function test_un_patron_multi_boutiques_doit_toujours_choisir(): void
    {
        $tenant = Tenant::factory()->create();
        Boutique::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->get('/ventes/create')
            ->assertOk()
            ->assertSee('— '.__('Choisir').' —');
    }

    public function test_la_camera_est_ouverte_a_nos_propres_pages_seulement(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $reponse = $this->actingAs($patron)->get('/dashboard');

        // camera=() interdirait la lecture par la caméra ; camera=* l'ouvrirait
        // à n'importe quel site nous embarquant dans un cadre.
        $politique = $reponse->headers->get('Permissions-Policy');
        $this->assertStringContainsString('camera=(self)', $politique);
        $this->assertStringContainsString('microphone=()', $politique);
        $this->assertStringContainsString('geolocation=()', $politique);
    }

    public function test_le_script_du_scan_reste_sous_nonce(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $reponse = $this->actingAs($vendeur)->get('/ventes/create');

        // La politique de contenu ne tolère aucun script en ligne sans nonce :
        // un bloc oublié serait bloqué par le navigateur, et le scan muet. Les
        // fichiers servis par <script src> relèvent, eux, de 'self'.
        preg_match_all('/<script\b([^>]*)>/', $reponse->getContent(), $balises);

        foreach ($balises[1] as $attributs) {
            if (! str_contains($attributs, 'src=')) {
                $this->assertStringContainsString('nonce=', $attributs);
            }
        }
        $directives = explode('; ', (string) $reponse->headers->get('Content-Security-Policy'));
        $scriptSrc = current(array_filter($directives, fn (string $d) => str_starts_with($d, 'script-src ')));

        // C'est 'unsafe-inline' qui neutraliserait le nonce : sa présence
        // rendrait exécutable n'importe quel script injecté dans la page.
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }
}
