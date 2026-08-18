<?php

namespace Tests\Feature;

use App\Enums\PorteeCodeAcces;
use App\Enums\TypeProduit;
use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\CodeAcces;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const ENTETE = 'nom;type;unite_mesure;code_barres;categorie;prix_achat;prix_vente;stock_min;stock_max;quantite_initiale;numero_lot;date_fabrication;date_peremption'
        .';format1_libelle;format1_contenu;format1_prix;format2_libelle;format2_contenu;format2_prix;format3_libelle;format3_contenu;format3_prix';

    private function fichier(string $contenu, string $nom = 'catalogue.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nom, $contenu);
    }

    private function ligne(string $nom, array $remplacements = []): string
    {
        $colonnes = array_merge([
            'nom' => $nom,
            'type' => TypeProduit::IntrantAgricole->value,
            'unite_mesure' => 'sac',
            'code_barres' => '',
            'categorie' => 'Engrais',
            'prix_achat' => '12000',
            'prix_vente' => '15000',
            'stock_min' => '10',
            'stock_max' => '200',
            'quantite_initiale' => '40',
            'numero_lot' => 'LOT-'.substr(md5($nom), 0, 6),
            'date_fabrication' => '',
            'date_peremption' => '',
            'format1_libelle' => '',
            'format1_contenu' => '',
            'format1_prix' => '',
            'format2_libelle' => '',
            'format2_contenu' => '',
            'format2_prix' => '',
            'format3_libelle' => '',
            'format3_contenu' => '',
            'format3_prix' => '',
        ], $remplacements);

        return implode(';', $colonnes);
    }

    public function test_a_patron_imports_products_with_their_initial_stock(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Engrais NPK'),
            $this->ligne('Urée 46%', ['categorie' => 'Engrais', 'quantite_initiale' => '15']),
        ]);

        $this->actingAs($patron)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        $this->assertSame(2, Produit::count());

        // La catégorie nommée une seule fois pour deux lignes ne doit pas être
        // créée deux fois : l'index unique (tenant_id, nom) la refuserait.
        $this->assertDatabaseCount('categories', 1);

        $produit = Produit::where('nom', 'Urée 46%')->firstOrFail();
        $this->assertDatabaseHas('stock_boutiques', [
            'produit_id' => $produit->id,
            'boutique_id' => $boutique->id,
            'quantite' => 15,
        ]);
        $this->assertDatabaseHas('journal_activites', ['action' => 'produit.importe']);
    }

    public function test_nothing_is_imported_when_a_single_line_is_wrong(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Produit correct'),
            // Maximum sous le minimum : la même règle qu'au formulaire.
            $this->ligne('Produit fautif', ['stock_min' => '80', 'stock_max' => '20']),
        ]);

        $reponse = $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $reponse->assertRedirect('/produits/import');

        // Le rapport désigne la ligne telle que le tableur l'affiche : l'en-tête
        // est la ligne 1, donc la ligne fautive est la 3.
        $erreurs = session('erreurs');
        $this->assertCount(1, $erreurs);
        $this->assertStringStartsWith('Ligne 3 :', $erreurs[0]);

        // Y compris la ligne valide qui la précédait.
        $this->assertSame(0, Produit::count());
    }

    public function test_a_phytosanitary_product_still_needs_its_expiry_date(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Glyphosate 1L', [
                'type' => TypeProduit::ProduitPhytosanitaire->value,
                'unite_mesure' => 'litre',
            ]),
        ]);

        $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $this->assertStringContainsString('date de péremption', session('erreurs')[0]);
        $this->assertSame(0, Produit::count());
    }

    public function test_a_barcode_repeated_inside_the_file_is_caught(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        // Rien n'est encore écrit en base quand la validation tourne : sans
        // contrôle en mémoire, les deux lignes passeraient puis heurteraient
        // l'index unique (tenant_id, code_barres).
        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Premier', ['code_barres' => '1234567890123']),
            $this->ligne('Second', ['code_barres' => '1234567890123']),
        ]);

        $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $this->assertStringContainsString('ligne 2', session('erreurs')[0]);
        $this->assertSame(0, Produit::count());
    }

    public function test_a_windows_excel_file_is_read_correctly(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        // Ce qu'« Enregistrer sous > CSV » produit sous Windows : marque
        // d'ordre des octets, accents en Windows-1252, fins de ligne CRLF.
        $csv = "\xEF\xBB\xBF".mb_convert_encoding(implode("\r\n", [
            'Nom;Type;Unité de mesure;Code barres;Catégorie;Prix achat;Prix vente;Stock min;Stock max;Quantité initiale;Numéro lot;Date fabrication;Date péremption',
            $this->ligne('Urée dosée à 46%'),
            '',
        ]), 'Windows-1252', 'UTF-8');

        $this->actingAs($patron)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        // Les intitulés accentués ont été reconnus, et l'accent du nom survit.
        $this->assertDatabaseHas('produits', ['nom' => 'Urée dosée à 46%']);
    }

    public function test_a_comma_separated_file_is_accepted_too(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            str_replace(';', ',', self::ENTETE),
            str_replace(';', ',', $this->ligne('Semence maïs')),
        ]);

        $this->actingAs($patron)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        $this->assertDatabaseHas('produits', ['nom' => 'Semence maïs']);
    }

    public function test_an_empty_retail_price_leaves_the_product_undetailable(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Sac scellé', [
                'prix_vente' => '',
                'format1_libelle' => 'Sac de 50 kg',
                'format1_contenu' => '50',
                'format1_prix' => '25000',
            ]),
        ]);

        $this->actingAs($patron)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        // Ramené à 0, le produit serait offert au comptoir.
        $produit = Produit::where('nom', 'Sac scellé')->firstOrFail();
        $this->assertNull($produit->prix_vente);
        $this->assertTrue($produit->estVendable());
    }

    public function test_the_format_columns_create_the_sale_formats(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Insecticide poudre', [
                'unite_mesure' => 'sachet',
                'prix_vente' => '500',
                'format1_libelle' => 'Carton de 24',
                'format1_contenu' => '24',
                'format1_prix' => '12000',
                'format2_libelle' => 'Boîte de 6',
                'format2_contenu' => '6',
                'format2_prix' => '3000',
            ]),
        ]);

        $this->actingAs($patron)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        $produit = Produit::where('nom', 'Insecticide poudre')->firstOrFail();
        $formats = $produit->conditionnements->keyBy('libelle');

        $this->assertCount(2, $formats);
        $this->assertSame(12000, $formats['Carton de 24']->prix_vente);
        $this->assertSame(3000, $formats['Boîte de 6']->prix_vente);

        // Le premier format déclaré est celui que la caisse propose d'emblée.
        $this->assertTrue($formats['Carton de 24']->par_defaut);
        $this->assertFalse($formats['Boîte de 6']->par_defaut);
    }

    public function test_a_format_price_that_does_not_divide_is_refused(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        // 11 000 / 24 ne tombe pas juste : le prix unitaire retenu par les
        // lignes de vente perdrait de l'argent à chaque passage en caisse.
        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Poudre mal tarifée', [
                'format1_libelle' => 'Carton de 24',
                'format1_contenu' => '24',
                'format1_prix' => '11001',
            ]),
        ]);

        $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $this->assertStringContainsString('multiple de 24', session('erreurs')[0]);
        $this->assertSame(0, Produit::count());
    }

    public function test_a_line_without_any_price_is_refused(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $csv = implode("\n", [
            self::ENTETE,
            $this->ligne('Sans aucun prix', ['prix_vente' => '']),
        ]);

        $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $this->assertStringContainsString('au moins un format', session('erreurs')[0]);
        $this->assertSame(0, Produit::count());
    }

    public function test_a_vendeur_cannot_import(): void
    {
        $tenant = Tenant::factory()->create();
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);

        $csv = self::ENTETE."\n".$this->ligne('Interdit');

        $this->actingAs($vendeur)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertForbidden();

        $this->assertSame(0, Produit::count());
    }

    public function test_a_gerant_imports_under_an_access_code_and_the_patron_sees_it(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id,
        ]);

        $code = CodeAcces::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'portee' => PorteeCodeAcces::Catalogue,
            'genere_par_user_id' => $patron->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addHours(4),
        ]);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();

        $csv = self::ENTETE."\n".$this->ligne('Importé par le gérant');

        $this->actingAs($gerant)
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)])
            ->assertRedirect(route('produits.index'));

        // La trace de l'import est rattachée au code : c'est ce que le patron
        // consulte pour savoir ce qu'a permis chaque délégation.
        $this->assertDatabaseHas('journal_activites', [
            'action' => 'produit.importe',
            'code_acces_id' => $code->id,
        ]);

        $this->actingAs($patron)->get(route('codes-acces.show', $code))
            ->assertOk()
            ->assertSee('produit.importe');
    }

    public function test_a_gerant_cannot_import_into_another_boutique(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $sienne = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $autre = Boutique::factory()->create(['tenant_id' => $tenant->id]);
        $gerant = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $sienne->id,
        ]);

        $code = CodeAcces::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $sienne->id,
            'portee' => PorteeCodeAcces::Catalogue,
            'genere_par_user_id' => $patron->id,
            'destinataire_user_id' => $gerant->id,
            'expire_le' => now()->addHours(4),
        ]);

        $this->actingAs($gerant)->post('/acces-privilegie', ['code' => $code->code])->assertRedirect();

        $csv = self::ENTETE."\n".$this->ligne('Détourné');

        $this->actingAs($gerant)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $autre->id, 'fichier' => $this->fichier($csv)])
            ->assertSessionHasErrors('boutique_id');

        $this->assertSame(0, Produit::count());
    }

    public function test_the_import_screen_and_its_error_report_render(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($patron)->get('/produits/import')
            ->assertOk()
            ->assertSee(__('Télécharger le modèle'))
            ->assertSee('quantite_initiale');

        // Le rapport d'erreurs n'apparaît qu'après un refus : c'est le seul
        // chemin qui rend cette partie de la vue.
        $csv = self::ENTETE."\n".$this->ligne('Fautif', ['prix_achat' => 'beaucoup']);

        $this->actingAs($patron)->from('/produits/import')
            ->post('/produits/import', ['boutique_id' => $boutique->id, 'fichier' => $this->fichier($csv)]);

        $this->actingAs($patron)->get('/produits/import')
            ->assertOk()
            ->assertSee("Ligne 2 : Le champ prix d'achat doit être un nombre entier.");
    }

    public function test_the_catalogue_offers_the_import(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($patron)->get('/produits')
            ->assertOk()
            ->assertSee(route('produits.import.create'));
    }

    public function test_the_template_lists_the_expected_columns(): void
    {
        $tenant = Tenant::factory()->create();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $reponse = $this->actingAs($patron)->get('/produits/import/modele.csv');

        $reponse->assertOk();
        $contenu = $reponse->streamedContent();

        $this->assertStringContainsString('quantite_initiale;numero_lot', $contenu);
        // La marque d'ordre des octets, sans laquelle Excel abîme les accents.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenu);
    }
}
