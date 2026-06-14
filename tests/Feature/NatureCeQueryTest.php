<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DemoDashboardSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NatureCeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_natures_ce_api_returns_aggregated_stats(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        (new DemoDashboardSeeder())->run();

        $response = $this->getJson('/api/v1/natures-ce?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => ['totaux', 'natures_ce', 'par_statut', 'par_chapitre', 'par_jour'],
            'data',
            'meta',
        ]);
        $response->assertJsonPath('stats.totaux.natures_ce_count', fn ($v) => $v >= 1);
    }

    public function test_natures_ce_filter_non_renseigne_matches_null_values(): void
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
            'total_ordonnance' => 500,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 200,
            'solde' => -500,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M-null-1',
            'libelle' => 'Mandat sans nature CE',
            'montant' => 500,
            'type' => 'depense',
            'type_mandat' => '0',
            'date_mouvement' => '2024-03-15',
            'annee' => 2024,
            'mois' => 3,
            'statut' => 'Payé',
            'nature_ce' => null,
            'source_numero_mandat' => 'MDT-001',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M-ce-1',
            'libelle' => 'Mandat avec nature CE',
            'montant' => 300,
            'type' => 'depense',
            'type_mandat' => '1',
            'date_mouvement' => '2024-03-20',
            'annee' => 2024,
            'mois' => 3,
            'statut' => 'Admis',
            'nature_ce' => 'CE-1',
            'source_numero_mandat' => 'MDT-002',
        ]);

        $response = $this->getJson('/api/v1/natures-ce?' . http_build_query([
            'region_code' => 'SAN',
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-12-31',
            'nature_ce' => 'Non renseigné',
            'type' => 'depense',
        ]));

        $response->assertOk();
        $response->assertJsonPath('meta.total', 1);
        $response->assertJsonPath('stats.totaux.mandats_count', 1);
        $response->assertJsonPath('data.0.libelle', 'Mandat sans nature CE');
    }

    public function test_natures_ce_export_returns_csv(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        (new DemoDashboardSeeder())->run();

        $response = $this->get('/api/v1/natures-ce/export?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
