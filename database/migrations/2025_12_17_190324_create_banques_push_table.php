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
        Schema::create('banques_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->onDelete('cascade');
            $table->string('regional_id', 100)->nullable()->comment('ID unique côté régional pour upsert');
            $table->string('numero_compte', 50)->comment('Numéro de compte bancaire');
            $table->string('libelle', 255)->comment('Nom de la banque');
            $table->date('date_mouvement')->nullable()->comment('Date du mouvement');
            $table->decimal('debit', 18, 2)->default(0)->comment('Montant débit');
            $table->decimal('credit', 18, 2)->default(0)->comment('Montant crédit');
            $table->decimal('solde', 18, 2)->default(0)->comment('Solde du compte');
            $table->string('reference', 100)->nullable()->comment('Référence document (Document No_)');
            $table->string('entry_no', 50)->nullable()->comment('Entry No_');
            $table->integer('exercice')->nullable()->comment('Année exercice');
            $table->string('type_document', 50)->nullable()->comment('Type de document');
            $table->text('description')->nullable()->comment('Description');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['dashboard_id', 'numero_compte']);
            $table->index('numero_compte');
            $table->index('libelle');
            $table->index('date_mouvement');
            $table->index('regional_id');

            // Contrainte unique pour éviter les doublons (mode différentiel)
            $table->unique(['dashboard_id', 'regional_id'], 'banques_push_dashboard_regional_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banques_push');
    }
};
