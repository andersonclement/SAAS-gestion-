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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('categorie_id')->nullable()->constrained()->nullOnDelete();
            $table->string('nom');
            $table->string('type');
            $table->string('unite_mesure');
            $table->string('code_barres')->nullable();
            $table->unsignedInteger('prix_achat')->default(0);
            $table->unsignedInteger('prix_vente')->default(0);
            $table->unsignedInteger('seuil_alerte')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code_barres']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
