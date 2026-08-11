<?php

namespace Database\Seeders;

use App\Enums\Plan;
use App\Enums\TypeProduit;
use App\Enums\UniteMesure;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Produit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Jeu de données de démonstration : un patron possédant deux boutiques,
     * chacune avec son gérant et son vendeur, pour illustrer l'isolation
     * multi-tenant et le scope par boutique (§4.1 du cahier des charges).
     */
    public function run(): void
    {
        // Ce seeder crée des comptes dont le mot de passe est « password » :
        // l'exécuter en production ouvrirait la plateforme à n'importe qui.
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Le seeder de démonstration ne doit jamais être exécuté en production.'
            );
        }

        $tenant = Tenant::create([
            'nom' => 'AgroPlus Distribution',
            'email_contact' => 'contact@agroplus.test',
            'telephone' => '+225 07 00 00 00 00',
            'plan' => Plan::Pro,
            'abonnement_expire_le' => now()->addMonth(),
        ]);

        Admin::create([
            'name' => 'Superadmin',
            'email' => 'superadmin@gestion-stock.test',
            'password' => 'password',
        ]);

        $patron = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Adama Koné',
            'email' => 'patron@agroplus.test',
            'password' => 'password',
            'role' => UserRole::Patron,
        ]);

        $boutiqueCentre = Boutique::create([
            'tenant_id' => $tenant->id,
            'nom' => 'AgroPlus Centre-ville',
            'adresse' => 'Avenue de la République, Abidjan',
            'telephone' => '+225 07 00 00 00 01',
        ]);

        $boutiqueNord = Boutique::create([
            'tenant_id' => $tenant->id,
            'nom' => 'AgroPlus Zone Nord',
            'adresse' => 'Route de Bouaké, km 12',
            'telephone' => '+225 07 00 00 00 02',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutiqueCentre->id,
            'name' => 'Fatou Diabaté',
            'email' => 'gerant.centre@agroplus.test',
            'password' => 'password',
            'role' => UserRole::Gerant,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutiqueCentre->id,
            'name' => 'Ibrahim Traoré',
            'email' => 'vendeur.centre@agroplus.test',
            'password' => 'password',
            'role' => UserRole::Vendeur,
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutiqueNord->id,
            'name' => 'Aminata Touré',
            'email' => 'gerant.nord@agroplus.test',
            'password' => 'password',
            'role' => UserRole::Gerant,
        ]);

        $categorieEngrais = Categorie::create(['tenant_id' => $tenant->id, 'nom' => 'Engrais']);
        $categoriePhyto = Categorie::create(['tenant_id' => $tenant->id, 'nom' => 'Produits phytosanitaires']);
        $categorieSemences = Categorie::create(['tenant_id' => $tenant->id, 'nom' => 'Semences']);

        Produit::create([
            'tenant_id' => $tenant->id,
            'categorie_id' => $categorieEngrais->id,
            'nom' => 'Engrais NPK 15-15-15',
            'type' => TypeProduit::IntrantAgricole,
            'unite_mesure' => UniteMesure::Sac,
            'code_barres' => '3760000000011',
            'prix_achat' => 12000,
            'prix_vente' => 15000,
            'seuil_alerte' => 10,
        ]);

        Produit::create([
            'tenant_id' => $tenant->id,
            'categorie_id' => $categorieSemences->id,
            'nom' => 'Semence de maïs hybride',
            'type' => TypeProduit::IntrantAgricole,
            'unite_mesure' => UniteMesure::Sac,
            'code_barres' => '3760000000028',
            'prix_achat' => 8000,
            'prix_vente' => 10000,
            'seuil_alerte' => 15,
        ]);

        Produit::create([
            'tenant_id' => $tenant->id,
            'categorie_id' => $categoriePhyto->id,
            'nom' => 'Glyphosate 1L',
            'type' => TypeProduit::ProduitPhytosanitaire,
            'unite_mesure' => UniteMesure::Litre,
            'code_barres' => '3760000000035',
            'prix_achat' => 5000,
            'prix_vente' => 6500,
            'seuil_alerte' => 5,
        ]);

        $this->command->info('Comptes de démonstration créés (mot de passe : "password") :');
        $this->command->table(
            ['Rôle', 'E-mail'],
            [
                ['Patron', $patron->email],
                ['Gérant — Centre-ville', 'gerant.centre@agroplus.test'],
                ['Vendeur — Centre-ville', 'vendeur.centre@agroplus.test'],
                ['Gérant — Zone Nord', 'gerant.nord@agroplus.test'],
                ['Superadmin (/admin/login)', 'superadmin@gestion-stock.test'],
            ]
        );
    }
}
