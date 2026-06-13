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
        Schema::create('recettes_clients_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->onDelete('cascade');
            $table->string('regional_id', 100)->nullable()->comment('ID unique côté régional pour upsert');
            $table->string('client_no', 50)->comment('Numéro client');
            $table->string('client_name', 255)->comment('Nom du client');
            $table->string('gl_account', 50)->nullable()->comment('Compte GL (ex: 4121)');
            $table->date('date_posting')->nullable()->comment('Date de comptabilisation');
            $table->decimal('montant', 18, 2)->default(0)->comment('Montant de la recette');
            $table->text('description')->nullable()->comment('Description');
            $table->string('source_no', 50)->nullable()->comment('Source No_');
            $table->integer('exercice')->nullable()->comment('Année exercice');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['dashboard_id', 'client_no']);
            $table->index('client_no');
            $table->index('client_name');
            $table->index('date_posting');
            $table->index('regional_id');

            // Contrainte unique pour éviter les doublons (mode différentiel)
            $table->unique(['dashboard_id', 'regional_id'], 'recettes_clients_push_dashboard_regional_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recettes_clients_push');
    }
};
