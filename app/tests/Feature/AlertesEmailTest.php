<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\RecapitulatifAlertes;
use App\Models\Boutique;
use App\Models\Lot;
use App\Models\Produit;
use App\Models\StockBoutique;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CentreAlertes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertesEmailTest extends TestCase
{
    use RefreshDatabase;

    private function tenantAvecRupture(): array
    {
        $tenant = Tenant::factory()->create(['abonnement_expire_le' => now()->addMonth()]);
        $boutique = Boutique::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Boutique Daloa']);
        $produit = Produit::factory()->create(['tenant_id' => $tenant->id, 'nom' => 'Engrais NPK']);
        $lot = Lot::factory()->create(['tenant_id' => $tenant->id, 'produit_id' => $produit->id]);

        StockBoutique::factory()->create([
            'tenant_id' => $tenant->id,
            'boutique_id' => $boutique->id,
            'produit_id' => $produit->id,
            'lot_id' => $lot->id,
            'quantite' => 0,
        ]);

        return [$tenant, $boutique, $produit];
    }

    public function test_the_daily_summary_reaches_the_patron(): void
    {
        Mail::fake();
        [$tenant] = $this->tenantAvecRupture();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertSent(RecapitulatifAlertes::class, fn ($mail) => $mail->hasTo($patron->email));
        $this->assertTrue($patron->fresh()->alerte_envoyee_le->isToday());
    }

    public function test_no_mail_is_sent_when_nothing_is_wrong(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create(['abonnement_expire_le' => now()->addMonth()]);
        Boutique::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_summary_is_not_sent_twice_the_same_day(): void
    {
        Mail::fake();
        [$tenant] = $this->tenantAvecRupture();
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $this->artisan('alertes:envoyer')->assertSuccessful();
        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertSentCount(1);

        // …sauf si l'exploitant force le renvoi.
        $this->artisan('alertes:envoyer --force')->assertSuccessful();
        Mail::assertSentCount(2);
    }

    public function test_a_gerant_only_gets_the_alerts_of_his_own_boutique(): void
    {
        Mail::fake();
        [$tenant, $boutiqueEnRupture] = $this->tenantAvecRupture();
        $autreBoutique = Boutique::factory()->create(['tenant_id' => $tenant->id]);

        $gerantConcerne = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $boutiqueEnRupture->id,
        ]);
        $gerantEpargne = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Gerant, 'boutique_id' => $autreBoutique->id,
        ]);

        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertSent(RecapitulatifAlertes::class, fn ($mail) => $mail->hasTo($gerantConcerne->email));
        Mail::assertNotSent(RecapitulatifAlertes::class, fn ($mail) => $mail->hasTo($gerantEpargne->email));
    }

    public function test_a_vendeur_and_an_opted_out_user_receive_nothing(): void
    {
        Mail::fake();
        [$tenant, $boutique] = $this->tenantAvecRupture();

        $vendeur = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Vendeur, 'boutique_id' => $boutique->id,
        ]);
        $patronDesabonne = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Patron, 'alertes_email' => false,
        ]);
        $patronDesactive = User::factory()->create([
            'tenant_id' => $tenant->id, 'role' => UserRole::Patron, 'actif' => false,
        ]);

        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertNull($vendeur->fresh()->alerte_envoyee_le);
        $this->assertNull($patronDesabonne->fresh()->alerte_envoyee_le);
        $this->assertNull($patronDesactive->fresh()->alerte_envoyee_le);
    }

    public function test_an_expired_or_suspended_tenant_is_skipped(): void
    {
        Mail::fake();
        [$expire] = $this->tenantAvecRupture();
        $expire->update(['abonnement_expire_le' => now()->subDay()]);
        User::factory()->create(['tenant_id' => $expire->id, 'role' => UserRole::Patron]);

        [$suspendu] = $this->tenantAvecRupture();
        $suspendu->update(['actif' => false]);
        User::factory()->create(['tenant_id' => $suspendu->id, 'role' => UserRole::Patron]);

        $this->artisan('alertes:envoyer')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_the_message_lists_the_product_out_of_stock(): void
    {
        [$tenant] = $this->tenantAvecRupture();
        $patron = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Patron]);

        $alertes = CentreAlertes::pour(Boutique::where('tenant_id', $tenant->id)->pluck('id'));
        $rendu = (new RecapitulatifAlertes($patron, $alertes, $tenant->nom))->render();

        $this->assertStringContainsString('Engrais NPK', $rendu);
        $this->assertStringContainsString('Boutique Daloa', $rendu);
    }
}
