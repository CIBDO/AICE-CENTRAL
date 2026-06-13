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
        Schema::create('mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->onDelete('cascade');
            $table->string('libelle')->comment('Libellé du mouvement');
            $table->decimal('montant', 15, 2)->default(0)->comment('Montant du mouvement');
            $table->enum('type', ['recette', 'depense'])->comment('Type de mouvement');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['dashboard_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvements');
    }
};
