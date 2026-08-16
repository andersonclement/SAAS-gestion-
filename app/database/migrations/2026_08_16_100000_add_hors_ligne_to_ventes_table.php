<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes nécessaires au mode hors-ligne (§5).
 *
 * `uuid_client` est l'identifiant que le navigateur attribue à la vente au
 * moment de l'encaissement, avant même de connaître le réseau. Il rend la
 * synchronisation idempotente : si le vendeur relance l'envoi, ou si la
 * réponse du serveur se perd en route, la même vente ne peut pas être
 * enregistrée deux fois.
 *
 * `encaissee_le` conserve l'heure réelle de l'encaissement, qui peut
 * précéder de plusieurs heures la date de synchronisation. Sans elle, les
 * rapports journaliers rattacheraient la vente au mauvais jour et la
 * promotion appliquée serait celle du jour de la synchronisation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->uuid('uuid_client')->nullable()->after('numero');
            $table->timestamp('encaissee_le')->nullable()->after('uuid_client');

            // Unicité par tenant : deux clients de la plateforme pourraient
            // théoriquement générer le même UUID sans que l'un bloque l'autre.
            $table->unique(['tenant_id', 'uuid_client']);
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'uuid_client']);
            $table->dropColumn(['uuid_client', 'encaissee_le']);
        });
    }
};
