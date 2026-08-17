<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le prix de vente à l'unité de base devient facultatif.
 *
 * `produits.prix_vente` est le prix **au détail** : le prix du kilo pour un
 * engrais, du litre pour un herbicide. Le renseigner, c'est déclarer que le
 * produit peut être vendu à la mesure. Le laisser vide, c'est déclarer
 * l'inverse : le sac ne s'ouvre pas au comptoir, et seuls les formats entiers
 * sont vendables.
 *
 * C'est une règle métier, pas une commodité d'affichage : beaucoup d'intrants
 * arrivent scellés — semences traitées, bidons de phytosanitaire — et les
 * ouvrir au détail est soit impossible, soit interdit. La caisse doit refuser
 * la vente à la mesure dans ce cas, pas se contenter de ne pas la proposer.
 *
 * Les produits déjà au catalogue gardent leur prix : ils restent détaillables,
 * ce qui est le comportement qu'ils avaient jusqu'ici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedInteger('prix_vente')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Un produit non détaillable n'a pas de prix au détail : on ne peut pas
        // en inventer un. Zéro est la seule valeur neutre au retour arrière.
        DB::table('produits')->whereNull('prix_vente')->update(['prix_vente' => 0]);

        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedInteger('prix_vente')->default(0)->nullable(false)->change();
        });
    }
};
