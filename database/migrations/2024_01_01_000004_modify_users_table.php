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
        Schema::table('users', function (Blueprint $table) {
            // Renommer le champ name en nom
            $table->renameColumn('name', 'nom');

            // Ajouter les nouveaux champs
            $table->string('prenom')->after('nom');
            $table->string('login')->unique()->after('email');
            $table->foreignId('role_id')->nullable()->after('login')->constrained()->nullOnDelete();
            $table->boolean('premiere_connexion')->default(true)->after('password');
            $table->boolean('actif')->default(true)->after('premiere_connexion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('nom', 'name');
            $table->dropColumn(['prenom', 'login', 'premiere_connexion', 'actif']);
            $table->dropConstrainedForeignId('role_id');
        });
    }
};
