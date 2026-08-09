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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            // La contrainte de clé étrangère vers `boutiques` est ajoutée dans la
            // migration create_boutiques_table, car cette table n'existe pas encore ici.
            $table->foreignId('boutique_id')->nullable()->after('tenant_id');
            $table->string('role')->after('boutique_id');
            $table->string('telephone')->nullable()->after('email');
            $table->boolean('actif')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['boutique_id', 'role', 'telephone', 'actif']);
        });
    }
};
