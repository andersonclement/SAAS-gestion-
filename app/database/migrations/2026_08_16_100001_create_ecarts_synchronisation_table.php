<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Écarts constatés à la synchronisation d'une vente hors-ligne.
 *
 * Le cas : le vendeur encaisse 10 sacs sans réseau ; entre-temps une autre
 * boutique a été servie depuis le même stock, et il n'en reste que 6 en base
 * au moment de la synchronisation.
 *
 * La marchandise est déjà partie et l'argent déjà encaissé : refuser la vente
 * ferait disparaître une recette réelle. La table `stock_boutiques` interdit
 * par ailleurs les quantités négatives (colonne non signée). On enregistre
 * donc la vente pour ce qui a pu être alloué, et on consigne ici la quantité
 * manquante pour que le gérant tranche — inventaire, correction, ou simple
 * constat de vol/casse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecarts_synchronisation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vente_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantite_demandee');
            $table->unsignedInteger('quantite_allouee');
            $table->unsignedInteger('quantite_manquante');
            $table->timestamp('resolu_le')->nullable();
            $table->foreignId('resolu_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'resolu_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecarts_synchronisation');
    }
};
