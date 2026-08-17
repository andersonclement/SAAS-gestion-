<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conditionnements de vente et d'achat (lacune n°2 de l'analyse du cahier
 * des charges).
 *
 * Un même engrais se vend au sac de 50 kg et au kilo détail. Sans cela, il
 * fallait créer deux fiches produit — donc deux stocks distincts pour une
 * seule marchandise, et un inventaire faux dès la première vente au détail.
 *
 * Le principe : le stock reste **toujours** compté dans l'unité de base du
 * produit (celle de `produits.unite_mesure`). Un conditionnement n'est qu'un
 * multiplicateur — « sac de 50 kg » = facteur 50 — assorti de son propre prix
 * de vente. Rien ne change donc dans les stocks, les rapports ou les marges
 * existants.
 *
 * `prix_vente` est le prix du conditionnement entier. Il doit être un multiple
 * du facteur : les lignes de vente conservent un prix unitaire par unité de
 * base, et c'est ce prix unitaire que tous les calculs de chiffre d'affaires
 * et de marge utilisent déjà. Un sac de 50 kg à 27 510 F donnerait 550,20 F le
 * kilo — impossible à représenter en francs entiers sans perdre de l'argent à
 * chaque ligne. La contrainte est vérifiée à la saisie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conditionnements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained()->cascadeOnDelete();
            $table->string('libelle');
            $table->unsignedInteger('facteur');
            $table->unsignedInteger('prix_vente');
            $table->boolean('par_defaut')->default(false);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['produit_id', 'libelle']);
            $table->index(['tenant_id', 'produit_id']);
        });

        // Conditionnement retenu pour la ligne. La quantité reste exprimée en
        // unité de base : une vente de 2 sacs de 50 kg peut se répartir sur
        // plusieurs lots (FEFO), et ne correspond alors plus à un nombre entier
        // de sacs par ligne. Le nombre de conditionnements est donc déduit de
        // la quantité, jamais stocké — il ne pourrait pas rester exact.
        Schema::table('ligne_ventes', function (Blueprint $table) {
            $table->foreignId('conditionnement_id')->nullable()->after('lot_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('ligne_bon_commandes', function (Blueprint $table) {
            $table->foreignId('conditionnement_id')->nullable()->after('produit_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ligne_bon_commandes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conditionnement_id');
        });

        Schema::table('ligne_ventes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conditionnement_id');
        });

        Schema::dropIfExists('conditionnements');
    }
};
