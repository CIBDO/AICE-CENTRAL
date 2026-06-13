<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute les champs nécessaires pour le filtrage des données
     */
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            // Période de référence pour le dashboard
            $table->integer('annee')->nullable()->after('encaisse')->comment('Année de référence');
            $table->integer('mois')->nullable()->after('annee')->comment('Mois de référence (1-12)');
            $table->date('date_debut')->nullable()->after('mois')->comment('Date de début de la période');
            $table->date('date_fin')->nullable()->after('date_debut')->comment('Date de fin de la période');

            // Index pour optimiser les requêtes de filtrage
            $table->index(['region_id', 'annee', 'mois']);
            $table->index(['region_id', 'date_debut', 'date_fin']);
            $table->index(['annee', 'mois']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropIndex(['region_id', 'annee', 'mois']);
            $table->dropIndex(['region_id', 'date_debut', 'date_fin']);
            $table->dropIndex(['annee', 'mois']);

            $table->dropColumn(['annee', 'mois', 'date_debut', 'date_fin']);
        });
    }
};








