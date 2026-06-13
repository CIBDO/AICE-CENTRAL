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
        Schema::table('mouvements', function (Blueprint $table) {
            // Champs analytiques pour enrichissement dashboard
            $table->string('code_programme', 100)->nullable()->after('nature')->comment('Code programme (ex: P001)');
            $table->string('chapitre', 100)->nullable()->after('code_programme')->comment('Chapitre budgétaire');
            $table->string('nature_ce', 100)->nullable()->after('chapitre')->comment('Nature CE (ex: 1, 2, 3...)');
            $table->string('statut', 50)->nullable()->after('nature_ce')->comment('Statut du mandat (Payé, Admis, etc.)');
            $table->string('beneficiaire', 255)->nullable()->after('statut')->comment('Bénéficiaire du mouvement');
            $table->string('source_numero_mandat', 100)->nullable()->after('beneficiaire')->comment('Numéro mandat source (debug)');
            $table->string('source_id', 100)->nullable()->after('source_numero_mandat')->comment('ID source (debug)');

            // Index pour optimiser les requêtes analytiques
            $table->index('code_programme');
            $table->index('chapitre');
            $table->index('nature_ce');
            $table->index('statut');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropIndex(['code_programme']);
            $table->dropIndex(['chapitre']);
            $table->dropIndex(['nature_ce']);
            $table->dropIndex(['statut']);

            $table->dropColumn([
                'code_programme',
                'chapitre',
                'nature_ce',
                'statut',
                'beneficiaire',
                'source_numero_mandat',
                'source_id',
            ]);
        });
    }
};
