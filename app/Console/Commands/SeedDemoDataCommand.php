<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Console\Command;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'aice:seed-demo {--fresh : Supprime les dashboards demo existants avant injection}';

    protected $description = 'Injecte des données de démonstration (mouvements, recettes, banques) pour toutes les régions';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->warn('Suppression des dashboards DEMO existants…');
            \App\Models\Dashboard::query()
                ->where('regional_id', 'like', 'DEMO-%')
                ->each(function ($dashboard) {
                    $dashboard->mouvements()->delete();
                    $dashboard->banquesPush()->delete();
                    $dashboard->recettesClientsPush()->delete();
                    $dashboard->delete();
                });
        }

        $this->info('Injection des données de démonstration…');
        (new DemoDashboardSeeder())->run();
        $this->info('Terminé. Rechargez les dashboards dans le navigateur.');

        return self::SUCCESS;
    }
}
