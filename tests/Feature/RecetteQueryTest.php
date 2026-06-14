<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecetteQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recettes_api_dedupes_by_entry_no_and_sums_line_amounts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'Sangha',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D1',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 1_500,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        foreach ([
            ['regional_id' => 'RECETTE-2024-abc1', 'source_id' => '1001', 'montant' => 1_000, 'client' => '18000', 'name' => 'Client A', 'date' => '2024-07-31'],
            ['regional_id' => 'RECETTE-2024-abc2', 'source_id' => '1002', 'montant' => 500, 'client' => '18000', 'name' => 'Client A', 'date' => '2024-08-15'],
            ['regional_id' => 'RECETTE-2024-abc3', 'source_id' => '1001', 'montant' => 1_000, 'client' => '18000', 'name' => 'Client A dup', 'date' => '2024-07-31'],
        ] as $row) {
            Mouvement::create([
                'dashboard_id' => $dashboard->id,
                'regional_id' => $row['regional_id'],
                'libelle' => 'Recette test',
                'montant' => $row['montant'],
                'type' => 'recette',
                'date_mouvement' => $row['date'],
                'beneficiaire' => $row['name'],
                'code_programme' => $row['client'],
                'source_id' => $row['source_id'],
                'annee' => 2024,
                'mois' => 7,
            ]);
        }

        $response = $this->getJson('/api/v1/recettes?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 2);
        $response->assertJsonPath('stats.totaux.montant_total', 1500);
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_recettes_filter_by_client_no(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'Sangha',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D1',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 800,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'RECETTE-2024-aaa',
            'libelle' => 'Recette A',
            'montant' => 300,
            'type' => 'recette',
            'date_mouvement' => '2024-05-10',
            'beneficiaire' => 'Client A',
            'code_programme' => '4121',
            'source_id' => '2001',
            'annee' => 2024,
            'mois' => 5,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'RECETTE-2024-bbb',
            'libelle' => 'Recette B',
            'montant' => 500,
            'type' => 'recette',
            'date_mouvement' => '2024-05-11',
            'beneficiaire' => 'Client B',
            'code_programme' => '9999',
            'source_id' => '2002',
            'annee' => 2024,
            'mois' => 5,
        ]);

        $response = $this->getJson('/api/v1/recettes?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31&client_no=4121');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 1);
        $response->assertJsonPath('stats.totaux.montant_total', 300);
    }

    public function test_recettes_filter_by_client_name_when_code_programme_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'Sangha',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D1',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 900,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'RECETTE-2024-treso',
            'libelle' => 'Encaissement',
            'montant' => 900,
            'type' => 'recette',
            'date_mouvement' => '2024-07-31',
            'beneficiaire' => 'TRESORERIE REGIONALE DE SAN',
            'code_programme' => null,
            'source_id' => '3001',
            'annee' => 2024,
            'mois' => 7,
        ]);

        $response = $this->getJson('/api/v1/recettes?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31&client_no=' . urlencode('TRESORERIE REGIONALE DE SAN'));

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 1);
        $response->assertJsonPath('meta.total', 1);
    }

    public function test_recettes_excludes_mandat_recette_mouvements(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'Sangha',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D1',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 1_000,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'RECETTE-2024-gl',
            'libelle' => 'GL 4121',
            'montant' => 400,
            'type' => 'recette',
            'date_mouvement' => '2024-04-01',
            'beneficiaire' => 'Client GL',
            'code_programme' => '18000',
            'source_id' => '4001',
            'annee' => 2024,
            'mois' => 4,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'MVT-2024-mandat',
            'libelle' => 'Reversement mandat',
            'montant' => 600,
            'type' => 'recette',
            'date_mouvement' => '2024-04-02',
            'beneficiaire' => null,
            'code_programme' => null,
            'source_id' => '9001',
            'annee' => 2024,
            'mois' => 4,
        ]);

        $response = $this->getJson('/api/v1/recettes?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 1);
        $response->assertJsonPath('stats.totaux.montant_total', 400);
    }
}
