<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Chaque responsable peut couper la réception du courriel
            // quotidien : un gérant qui consulte l'écran « Alertes » tous les
            // matins n'a pas besoin d'un rappel dans sa boîte.
            $table->boolean('alertes_email')->default(true)->after('actif');

            // Date du dernier envoi, pour ne pas expédier deux fois le même
            // récapitulatif si la tâche planifiée est rejouée dans la journée.
            $table->date('alerte_envoyee_le')->nullable()->after('alertes_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['alertes_email', 'alerte_envoyee_le']);
        });
    }
};
