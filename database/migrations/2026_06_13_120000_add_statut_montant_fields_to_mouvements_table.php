<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->string('statut_code', 10)->nullable()->after('statut')->comment('Code NAV (S92, S29, S30, …)');
            $table->decimal('montant_paye', 18, 2)->nullable()->after('montant')->comment('Montant payé NAV');
            $table->decimal('solde_a_payer', 18, 2)->nullable()->after('montant_paye')->comment('Solde à payer NAV');
            $table->index('statut_code');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements', function (Blueprint $table) {
            $table->dropIndex(['statut_code']);
            $table->dropColumn(['statut_code', 'montant_paye', 'solde_a_payer']);
        });
    }
};
