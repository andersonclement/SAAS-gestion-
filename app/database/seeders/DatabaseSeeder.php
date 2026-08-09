<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Boutique;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

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
        $tenant = Tenant::create([
            'nom' => 'AgroPlus Distribution',
            'email_contact' => 'contact@agroplus.test',
            'telephone' => '+225 07 00 00 00 00',
            'plan' => 'essai',
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

        $this->command->info('Comptes de démonstration créés (mot de passe : "password") :');
        $this->command->table(
            ['Rôle', 'E-mail'],
            [
                ['Patron', $patron->email],
                ['Gérant — Centre-ville', 'gerant.centre@agroplus.test'],
                ['Vendeur — Centre-ville', 'vendeur.centre@agroplus.test'],
                ['Gérant — Zone Nord', 'gerant.nord@agroplus.test'],
            ]
        );
    }
}
