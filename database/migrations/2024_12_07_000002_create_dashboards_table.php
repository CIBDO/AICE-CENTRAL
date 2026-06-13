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
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('regions')->onDelete('cascade');
            $table->string('local_id', 100)->nullable()->comment('Code unique du poste régional');
            $table->decimal('total_recettes', 15, 2)->default(0)->comment('Total des recettes');
            $table->decimal('total_depenses', 15, 2)->default(0)->comment('Total des dépenses');
            $table->decimal('solde', 15, 2)->default(0)->comment('Solde disponible');
            $table->decimal('encaisse', 15, 2)->default(0)->comment('Montant en caisse');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['region_id', 'created_at']);
            $table->index('local_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboards');
    }
};
