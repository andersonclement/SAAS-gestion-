<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_acces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('portee');

            // Le code est nominatif : il ne vaut que pour le gérant à qui le
            // patron l'a remis, et les opérations lui sont imputées.
            $table->foreignId('genere_par_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('destinataire_user_id')->constrained('users')->cascadeOnDelete();

            $table->timestamp('expire_le');
            $table->timestamp('active_le')->nullable();
            $table->timestamp('revoque_le')->nullable();
            $table->string('motif')->nullable();
            $table->timestamps();
        });

        Schema::table('journal_activites', function (Blueprint $table) {
            // Rattache une opération au code qui l'a rendue possible : c'est
            // ce lien qui permet au patron de relire, code par code, ce que le
            // gérant a fait de la délégation qu'il lui a accordée.
            $table->foreignId('code_acces_id')->nullable()->after('boutique_id')
                ->constrained('codes_acces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_activites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('code_acces_id');
        });

        Schema::dropIfExists('codes_acces');
    }
};
