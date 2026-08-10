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
        Schema::create('ligne_inventaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produit_id')->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantite_theorique');
            $table->unsignedInteger('quantite_physique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ligne_inventaires');
    }
};
