<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // « seuil_alerte » désignait déjà le stock minimum : on le renomme
        // plutôt que d'ajouter une colonne concurrente, pour qu'il n'existe
        // qu'une seule notion de seuil bas dans l'application.
        Schema::table('produits', function (Blueprint $table) {
            $table->renameColumn('seuil_alerte', 'stock_min');
        });

        Schema::table('produits', function (Blueprint $table) {
            // 0 = pas de plafond défini (cas des produits déjà enregistrés).
            $table->unsignedInteger('stock_max')->default(0)->after('stock_min');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('stock_max');
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->renameColumn('stock_min', 'seuil_alerte');
        });
    }
};
