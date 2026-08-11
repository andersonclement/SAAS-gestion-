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
        Schema::create('versements_caisse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boutique_id')->constrained()->restrictOnDelete();
            $table->foreignId('remis_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remis_par_nom');
            $table->unsignedInteger('montant');
            $table->date('date');
            $table->string('description')->nullable();
            $table->foreignId('cree_par_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('versements_caisse');
    }
};
