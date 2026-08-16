<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications internes : les alertes deviennent une pile de travail.
 *
 * La page « Alertes » existante calcule un état instantané — ce qui ne va pas
 * en ce moment. C'est utile, mais on ne sait jamais ce qu'on a déjà vu, ni ce
 * qu'on a laissé traîner. Cette table apporte l'autre moitié : ce qui m'a été
 * signalé, ce que j'ai lu, ce qui persiste malgré tout.
 *
 * Deux colonnes portent l'essentiel :
 *
 * - `cle` identifie la **situation**, pas l'événement : « rupture du produit 12
 *   dans la boutique 3 ». Tant qu'elle est ouverte, la génération quotidienne
 *   la reconnaît et ne crée pas de doublon. C'est ce qui distingue un centre de
 *   notifications utile d'un déversoir que plus personne ne lit.
 * - `resolue_le` est posée automatiquement quand la situation disparaît du
 *   calcul d'alertes : un stock réapprovisionné referme sa notification sans
 *   que personne ait à cliquer.
 *
 * Une ligne par destinataire : les volumes sont petits (quelques alertes, deux
 * ou trois responsables) et cela évite une table de lectures pour savoir qui a
 * vu quoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_internes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Nulle pour ce qui dépasse une boutique : abonnement, par exemple.
            $table->foreignId('boutique_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type');
            $table->string('importance');
            $table->string('cle');
            $table->string('titre');
            $table->text('message');
            // Route à ouvrir depuis la notification, pour agir sans chercher.
            $table->string('lien')->nullable();

            $table->timestamp('lu_le')->nullable();
            $table->timestamp('resolue_le')->nullable();
            $table->timestamp('rappele_le')->nullable();
            $table->unsignedSmallInteger('nombre_rappels')->default(0);

            $table->timestamps();

            // Une seule notification vivante par situation et par destinataire.
            $table->unique(['user_id', 'cle']);
            $table->index(['tenant_id', 'resolue_le']);
            $table->index(['user_id', 'lu_le', 'resolue_le']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_internes');
    }
};
