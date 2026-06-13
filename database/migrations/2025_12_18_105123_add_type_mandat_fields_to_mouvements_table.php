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
            $table->string('type_mandat', 10)->nullable()->after('nature')->comment('Code type mandat (0=Matériel, 1=Salaire, 2=Reversement)');
            $table->string('type_mandat_libelle', 50)->nullable()->after('type_mandat')->comment('Libellé type mandat (Matériel, Salaire, Reversement)');
            $table->index('type_mandat');
            $table->index('type_mandat_libelle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropIndex(['type_mandat']);
            $table->dropIndex(['type_mandat_libelle']);
            $table->dropColumn(['type_mandat', 'type_mandat_libelle']);
        });
    }
};
