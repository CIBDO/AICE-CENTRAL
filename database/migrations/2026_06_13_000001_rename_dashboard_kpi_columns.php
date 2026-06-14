<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->decimal('total_ordonnance', 15, 2)->default(0)->after('regional_id');
            $table->decimal('total_recouvrements_4121', 15, 2)->default(0)->after('total_ordonnance');
            $table->decimal('total_montant_paye', 15, 2)->default(0)->after('total_recouvrements_4121');
            $table->decimal('tresorerie_reelle', 15, 2)->default(0)->after('solde');
        });

        if (Schema::hasColumn('dashboards', 'total_depenses')) {
            DB::table('dashboards')->select('id', 'total_recettes', 'total_depenses', 'encaisse')->orderBy('id')->each(function ($row) {
                DB::table('dashboards')->where('id', $row->id)->update([
                    'total_ordonnance' => $row->total_depenses,
                    'total_recouvrements_4121' => $row->total_recettes,
                    'tresorerie_reelle' => $row->encaisse,
                ]);
            });

            Schema::table('dashboards', function (Blueprint $table) {
                $table->dropColumn(['total_recettes', 'total_depenses', 'encaisse']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('dashboards', function (Blueprint $table) {
            $table->decimal('total_recettes', 15, 2)->default(0);
            $table->decimal('total_depenses', 15, 2)->default(0);
            $table->decimal('encaisse', 15, 2)->default(0);
        });

        DB::table('dashboards')->select('id', 'total_ordonnance', 'total_recouvrements_4121', 'tresorerie_reelle')->orderBy('id')->each(function ($row) {
            DB::table('dashboards')->where('id', $row->id)->update([
                'total_recettes' => $row->total_recouvrements_4121,
                'total_depenses' => $row->total_ordonnance,
                'encaisse' => $row->tresorerie_reelle,
            ]);
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn([
                'total_ordonnance',
                'total_recouvrements_4121',
                'total_montant_paye',
                'tresorerie_reelle',
            ]);
        });
    }
};
