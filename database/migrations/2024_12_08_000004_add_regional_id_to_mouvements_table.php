<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute le champ regional_id et la contrainte unique pour éviter les doublons
     * en mode différentiel (upsert) pour les mouvements
     */
    public function up(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            // ID unique côté régional (pour mode différentiel)
            $table->string('regional_id', 100)->nullable()->after('dashboard_id')
                ->comment('ID unique du mouvement côté régional (pour upsert)');

            // Index unique pour éviter les doublons (dashboard_id + regional_id)
            // Permet l'upsert : si existe, mise à jour ; sinon, insertion
            $table->unique(['dashboard_id', 'regional_id'], 'mouvements_dashboard_regional_unique');

            // Index pour recherche rapide
            $table->index('regional_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropUnique('mouvements_dashboard_regional_unique');
            $table->dropIndex(['regional_id']);
            $table->dropColumn('regional_id');
        });
    }
};








