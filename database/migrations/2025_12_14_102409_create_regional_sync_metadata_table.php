<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     *
     * Table pour stocker les métadonnées de synchronisation par région
     * Permet la synchronisation incrémentale en suivant la dernière date/ID récupéré
     */
    public function up(): void
    {
        Schema::create('regional_sync_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');

            // Dernière date de synchronisation
            $table->date('last_sync_date')->nullable()->comment('Dernière date de synchronisation');
            $table->datetime('last_sync_at')->nullable()->comment('Dernière heure de synchronisation');

            // Dernier ID récupéré (pour synchronisation incrémentale par ID)
            $table->string('last_dashboard_id', 100)->nullable()->comment('Dernier dashboard_id récupéré');
            $table->string('last_mouvement_id', 100)->nullable()->comment('Dernier mouvement_id récupéré');

            // Statistiques de la dernière synchronisation
            $table->integer('last_dashboards_count')->default(0)->comment('Nombre de dashboards récupérés lors de la dernière sync');
            $table->integer('last_mouvements_count')->default(0)->comment('Nombre de mouvements récupérés lors de la dernière sync');

            // Statut de la dernière synchronisation
            $table->enum('last_sync_status', ['success', 'error', 'partial'])->nullable()->comment('Statut de la dernière synchronisation');
            $table->text('last_sync_error')->nullable()->comment('Erreur de la dernière synchronisation si échec');

            $table->timestamps();

            // Index unique pour une seule entrée par région
            $table->unique('region_id');

            // Index pour recherche rapide
            $table->index('last_sync_date');
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regional_sync_metadata');
    }
};
