<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute les champs de date pour le filtrage des mouvements
     */
    public function up(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            // Date du mouvement (pour filtrage)
            $table->date('date_mouvement')->nullable()->after('type')->comment('Date du mouvement');
            $table->integer('annee')->nullable()->after('date_mouvement')->comment('Année du mouvement');
            $table->integer('mois')->nullable()->after('annee')->comment('Mois du mouvement (1-12)');

            // Métadonnées supplémentaires pour filtrage avancé
            $table->string('programme', 100)->nullable()->after('mois')->comment('Programme (si applicable)');
            $table->string('nature', 100)->nullable()->after('programme')->comment('Nature (si applicable)');

            // Index pour optimiser les requêtes de filtrage
            $table->index(['dashboard_id', 'date_mouvement']);
            $table->index(['dashboard_id', 'annee', 'mois']);
            $table->index(['type', 'date_mouvement']);
            $table->index('programme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropIndex(['dashboard_id', 'date_mouvement']);
            $table->dropIndex(['dashboard_id', 'annee', 'mois']);
            $table->dropIndex(['type', 'date_mouvement']);
            $table->dropIndex(['programme']);

            $table->dropColumn(['date_mouvement', 'annee', 'mois', 'programme', 'nature']);
        });
    }
};








