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
        Schema::create('retours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boutique_id')->constrained()->restrictOnDelete();
            $table->foreignId('ligne_vente_id')->constrained()->restrictOnDelete();
            $table->foreignId('produit_id')->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantite');
            $table->string('motif');
            $table->boolean('remis_en_stock')->default(false);
            $table->unsignedInteger('montant_avoir');
            $table->string('mode_remboursement');
            $table->foreignId('cree_par_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('retours');
    }
};
