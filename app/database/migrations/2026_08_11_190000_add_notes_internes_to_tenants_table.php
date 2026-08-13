<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notes internes du superadmin sur un client (contexte commercial,
     * historique d'un incident, conditions négociées). Jamais visibles
     * côté tenant : ce champ n'est lu que dans l'espace superadmin.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('notes_internes')->nullable()->after('abonnement_expire_le');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('notes_internes');
        });
    }
};
