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
        Schema::create('bon_commandes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boutique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fournisseur_id')->constrained()->restrictOnDelete();
            $table->foreignId('cree_par_id')->constrained('users')->restrictOnDelete();
            $table->string('numero');
            $table->string('statut')->default('brouillon');
            $table->timestamp('recu_le')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bon_commandes');
    }
};
