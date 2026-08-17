<?php

namespace Tests\Feature;

use App\Enums\ImportanceNotification;
use App\Enums\TypeNotification;
use App\Enums\UserRole;
use App\Models\Admin;
use App\Models\Boutique;
use App\Models\Client;
use App\Models\Lot;
use App\Models\NotificationInterne;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vente;
use App\Support\GenerateurNotifications;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Centre de notifications : les alertes deviennent une pile de travail,
 * consultable via un bouton et visible sur le tableau de bord.
 */
class NotificationsInternesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{tenant: Tenant, boutique: Boutique, patron: User, gerant: User, vendeur: User, produit: Produit, lot: Lot, stock: StockBoutique}
     */
    private function contexte(int $quantiteEnStock = 0): array
    {
        $tenant = Tenant::factory()->create(['abonnement_expire_le' => now()->addYear()]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron, 'boutique_id' => null]);
        $gerant = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutique->id]);
        $vendeur = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id]);

        $produit = Produit::factory()->create([
            'tenant_id' => $tenant->id,
            'nom' => 'Engrais NPK',
            'stock_min' => 10,
            // Plafond large : un réapprovisionnement ne doit pas déclencher un
            // surstock qui viendrait brouiller les assertions.
            'stock_max' => 100000,
        ]);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);
        $stock = StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => $quantiteEnStock,
        ]);

        return compact('tenant', 'boutique', 'patron', 'gerant', 'vendeur', 'produit', 'lot', 'stock');
    }

    private function generer(): array
    {
        return app(GenerateurNotifications::class)->pourTousLesTenants();
    }

    /** @return NotificationInterne[]|Collection */
    private function notificationsDe(User $utilisateur)
    {
        return NotificationInterne::withoutGlobalScopes()->where('user_id', $utilisateur->id)->get();
    }

    private function rupture(User $utilisateur): ?NotificationInterne
    {
        return $this->notificationsDe($utilisateur)->firstWhere('type', TypeNotification::Rupture);
    }

    // --- Génération -------------------------------------------------------

    public function test_une_rupture_notifie_le_gerant_de_la_boutique_et_le_patron(): void
    {
        $c = $this->contexte(0);

        $bilan = $this->generer();

        $this->assertSame(2, $bilan['creees']);

        foreach ([$c['gerant'], $c['patron']] as $destinataire) {
            $notification = $this->notificationsDe($destinataire)->firstWhere('type', TypeNotification::Rupture);

            $this->assertNotNull($notification, "aucune notification pour {$destinataire->role->value}");
            $this->assertSame(ImportanceNotification::Critique, $notification->importance);
            $this->assertStringContainsString('Engrais NPK', $notification->titre);
            $this->assertNull($notification->lu_le);
        }
    }

    public function test_un_vendeur_ne_recoit_aucune_notification_de_stock(): void
    {
        $c = $this->contexte(0);

        $this->generer();

        $this->assertCount(0, $this->notificationsDe($c['vendeur']));
    }

    public function test_un_gerant_ne_recoit_pas_les_alertes_d_une_autre_boutique(): void
    {
        $c = $this->contexte(0);

        $autreBoutique = Boutique::factory()->create(['tenant_id' => $c['tenant']->id]);
        $autreGerant = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Gerant,
            'boutique_id' => $autreBoutique->id,
        ]);

        $this->generer();

        $this->assertCount(1, $this->notificationsDe($c['gerant']));
        $this->assertCount(0, $this->notificationsDe($autreGerant));
    }

    public function test_deux_executions_ne_creent_pas_de_doublon(): void
    {
        $this->contexte(0);

        $this->generer();
        $bilan = $this->generer();

        $this->assertSame(0, $bilan['creees']);
        $this->assertSame(0, $bilan['rappels']);
        // Une seule notification par destinataire : le gérant et le patron.
        $this->assertSame(2, NotificationInterne::withoutGlobalScopes()->count());
    }

    public function test_une_creance_en_retard_notifie_aussi_le_comptable(): void
    {
        $c = $this->contexte(50);
        $comptable = User::factory()->create([
            'tenant_id' => $c['tenant']->id,
            'role' => UserRole::Comptable,
            'boutique_id' => null,
        ]);

        $client = Client::factory()->create(['tenant_id' => $c['tenant']->id, 'plafond_credit' => 100000]);
        $vente = Vente::withoutEvents(fn () => Vente::create([
            'tenant_id' => $c['tenant']->id,
            'boutique_id' => $c['boutique']->id,
            'client_id' => $client->id,
            'vendeur_id' => $c['vendeur']->id,
            'numero' => 'VT-000001',
            'date_echeance' => now()->subWeek(),
        ]));
        $vente->lignes()->create([
            'produit_id' => $c['produit']->id,
            'lot_id' => $c['lot']->id,
            'quantite' => 1,
            'prix_unitaire' => 5000,
        ]);

        $this->generer();

        $this->assertNotNull($this->notificationsDe($comptable)->firstWhere('type', TypeNotification::CreanceEnRetard));
        // Le comptable ne suit pas les rayons : aucune alerte de stock.
        $this->assertNull($this->notificationsDe($comptable)->firstWhere('type', TypeNotification::StockFaible));
    }

    public function test_un_abonnement_qui_expire_ne_concerne_que_le_patron(): void
    {
        $c = $this->contexte(50);
        $c['tenant']->forceFill(['abonnement_expire_le' => now()->addDays(3)])->save();

        $this->generer();

        $this->assertNotNull($this->notificationsDe($c['patron'])->firstWhere('type', TypeNotification::AbonnementExpireBientot));
        $this->assertNull($this->notificationsDe($c['gerant'])->firstWhere('type', TypeNotification::AbonnementExpireBientot));
    }

    // --- Rappels ----------------------------------------------------------

    public function test_une_notification_lue_mais_non_reglee_revient_en_rappel(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $notification = $this->rupture($c['gerant']);
        $notification->marquerLue();

        // Une rupture se rappelle au bout de deux jours. Seule celle du gérant
        // a été lue : celle du patron est toujours en attente, donc pas rappelée.
        $this->travel(3)->days();
        $bilan = $this->generer();

        $notification->refresh();

        $this->assertSame(1, $bilan['rappels']);
        $this->assertNull($notification->lu_le, 'la notification doit redevenir non lue');
        $this->assertSame(1, $notification->nombre_rappels);
        $this->assertTrue($notification->estRappel());
    }

    public function test_aucun_rappel_avant_le_delai_propre_au_type(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->rupture($c['gerant'])->marquerLue();

        $this->travel(1)->days();
        $bilan = $this->generer();

        $this->assertSame(0, $bilan['rappels']);
        $this->assertNotNull($this->rupture($c['gerant'])->lu_le);
    }

    // --- Clôture ----------------------------------------------------------

    public function test_une_situation_reglee_referme_sa_notification(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        // Réapprovisionnement : la rupture n'existe plus.
        $c['stock']->update(['quantite' => 500]);
        $bilan = $this->generer();

        $this->assertSame(2, $bilan['resolues']);
        $this->assertNotNull($this->rupture($c['gerant'])->resolue_le);
        $this->assertSame(0, NotificationInterne::withoutGlobalScopes()->whereNull('resolue_le')->count());
    }

    public function test_une_situation_qui_revient_rouvre_la_notification(): void
    {
        $c = $this->contexte(0);
        $this->generer();
        $this->rupture($c['gerant'])->marquerLue();

        $c['stock']->update(['quantite' => 500]);
        $this->generer();

        // Puis le stock retombe à zéro : c'est un fait nouveau, pas un rappel.
        $c['stock']->update(['quantite' => 0]);
        $bilan = $this->generer();

        $notification = $this->rupture($c['gerant']);

        $this->assertSame(2, $bilan['creees']);
        $this->assertNull($notification->resolue_le);
        $this->assertNull($notification->lu_le);
    }

    // --- Consultation -----------------------------------------------------

    public function test_le_bouton_de_la_barre_affiche_le_nombre_a_traiter(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->actingAs($c['gerant'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(__('Notifications'), false);
    }

    public function test_le_tableau_de_bord_du_gerant_montre_les_alertes_a_traiter(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->actingAs($c['gerant'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(__('À traiter'), false)
            ->assertSee('Engrais NPK', false);
    }

    public function test_le_patron_voit_la_boutique_concernee_sur_son_tableau_de_bord(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->actingAs($c['patron'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee($c['boutique']->nom, false);
    }

    public function test_la_page_des_notifications_liste_ce_qui_reste_a_traiter(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->actingAs($c['gerant'])
            ->get('/notifications')
            ->assertOk()
            ->assertSee('Engrais NPK', false);
    }

    public function test_chacun_ne_voit_que_ses_propres_notifications(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $notificationDuPatron = $this->rupture($c['patron']);

        $this->actingAs($c['gerant'])
            ->post("/notifications/{$notificationDuPatron->id}/lire")
            ->assertStatus(403);

        $this->assertNull($notificationDuPatron->fresh()->lu_le);
    }

    public function test_les_notifications_d_un_autre_tenant_sont_invisibles(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $autreTenant = Tenant::factory()->create(['abonnement_expire_le' => now()->addYear()]);
        $intrus = User::factory()->create(['tenant_id' => $autreTenant->id, 'role' => UserRole::Patron]);

        $this->actingAs($intrus)
            ->get('/notifications')
            ->assertOk()
            ->assertDontSee('Engrais NPK', false);
    }

    public function test_lire_une_notification_la_marque_et_ouvre_l_ecran_ou_agir(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $notification = $this->rupture($c['gerant']);

        $this->actingAs($c['gerant'])
            ->post("/notifications/{$notification->id}/lire")
            ->assertRedirect($notification->lien);

        $notification->refresh();

        $this->assertNotNull($notification->lu_le);
        // Lire n'est pas résoudre : la situation reste ouverte, et reviendra.
        $this->assertNull($notification->resolue_le);
    }

    public function test_tout_marquer_comme_lu_vide_la_pastille(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $this->actingAs($c['gerant'])->post('/notifications/tout-lire')->assertRedirect();

        $this->assertSame(
            0,
            NotificationInterne::withoutGlobalScopes()
                ->where('user_id', $c['gerant']->id)
                ->whereNull('lu_le')
                ->count()
        );
    }

    // --- Superadmin -------------------------------------------------------

    public function test_le_superadmin_repere_les_clients_qui_accumulent_des_alertes(): void
    {
        $c = $this->contexte(0);
        $this->generer();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee(__('Clients à rappeler'), false)
            ->assertSee($c['tenant']->nom, false);
    }

    public function test_la_liste_des_clients_affiche_les_alertes_non_traitees(): void
    {
        $this->contexte(0);
        $this->generer();

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin/tenants')
            ->assertOk()
            ->assertSee(__('Alertes non traitées'), false);
    }
}
