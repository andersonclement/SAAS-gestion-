<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('plafond_credit')->nullable()->after('adresse');
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->date('date_echeance')->nullable()->after('vendeur_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('plafond_credit');
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('date_echeance');
        });
    }
};
