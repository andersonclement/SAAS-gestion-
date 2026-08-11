<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('codes_activation', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('plan');
            $table->unsignedInteger('duree_mois')->default(1);
            $table->string('statut')->default('en_attente');
            // Boutique destinataire prévue par le superadmin (renouvellement) : peut
            // rester nulle pour un code destiné à une inscription à venir.
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('genere_par_admin_id')->constrained('admins')->restrictOnDelete();
            $table->foreignId('utilise_par_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('utilise_le')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('codes_activation');
    }
};
