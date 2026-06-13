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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('Code unique de la région (ex: DAKAR, THIES)');
            $table->string('nom', 100)->comment('Nom complet de la région');
            $table->string('db_host')->nullable()->comment('Hôte de la base de données distante');
            $table->integer('db_port')->default(1433)->comment('Port de la base de données');
            $table->string('db_database')->nullable()->comment('Nom de la base de données');
            $table->string('db_username')->nullable()->comment('Utilisateur de connexion (lecture seule)');
            $table->text('db_password')->nullable()->comment('Mot de passe chiffré');
            $table->string('db_charset')->default('utf8')->comment('Charset de la connexion');
            $table->boolean('actif')->default(true)->comment('Si la région est active ou non');
            $table->integer('ordre')->default(0)->comment('Ordre d\'affichage dans le dropdown');
            $table->json('metadata')->nullable()->comment('Métadonnées supplémentaires (contact, timezone, etc.)');
            $table->timestamp('derniere_connexion')->nullable()->comment('Dernière connexion réussie');
            $table->text('derniere_erreur')->nullable()->comment('Dernière erreur de connexion');
            $table->timestamps();
            $table->softDeletes();

            // Index pour optimiser les requêtes
            $table->index(['actif', 'ordre']);
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};


