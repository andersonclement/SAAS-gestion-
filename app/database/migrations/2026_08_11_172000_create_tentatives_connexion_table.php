<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trace chaque tentative de connexion, réussie ou non, côté tenant
     * comme côté superadmin — visibilité que le journal d'activité
     * n'offre pas (celui-ci ne trace que les actions d'un utilisateur
     * déjà authentifié). Sert au superadmin pour surveiller le trafic
     * réel de la plateforme (adresse IP, appareil, tentatives échouées).
     */
    public function up(): void
    {
        Schema::create('tentatives_connexion', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->boolean('reussie');
            $table->string('role')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('adresse_ip')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('email');
            $table->index('reussie');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tentatives_connexion');
    }
};
