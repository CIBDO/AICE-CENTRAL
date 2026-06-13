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
     * en mode différentiel (upsert)
     */
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            // ID unique côté régional (pour mode différentiel)
            $table->string('regional_id', 100)->nullable()->after('local_id')
                ->comment('ID unique du dashboard côté régional (pour upsert)');

            // Index unique pour éviter les doublons (region_id + regional_id)
            // Permet l'upsert : si existe, mise à jour ; sinon, insertion
            $table->unique(['region_id', 'regional_id'], 'dashboards_region_regional_unique');

            // Index pour recherche rapide
            $table->index('regional_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropUnique('dashboards_region_regional_unique');
            $table->dropIndex(['regional_id']);
            $table->dropColumn('regional_id');
        });
    }
};








