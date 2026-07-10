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

    public function test_natures_ce_stats_count_nav_lines_and_not_distinct_mandats(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 'san-token',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D-SAN-2024',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 12,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-1',
            'libelle' => 'Mandat CE ligne 1',
            'montant' => 1000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'nature' => 'Nature CE 1',
            'nature_ce' => 'CE-1',
            'chapitre' => '60',
            'statut' => 'Transmis',
            'statut_code' => 'S00',
            'beneficiaire' => 'Fournisseur A',
            'source_numero_mandat' => 'M-001',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-2',
            'libelle' => 'Mandat CE ligne 2',
            'montant' => 2000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'nature' => 'Nature CE 1',
            'nature_ce' => 'CE-1',
            'chapitre' => '60',
            'statut' => 'Admis',
            'statut_code' => 'S30',
            'beneficiaire' => 'Fournisseur A',
            'source_numero_mandat' => 'M-001',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
        ]);

        $response = $this->getJson('/api/v1/natures-ce?region_code=SAN&annee=2024&mois=12');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.mandats_count', 2);
        $response->assertJsonPath('stats.natures_ce.0.code', 'CE-1');
        $response->assertJsonPath('stats.natures_ce.0.count', 2);
    }

    public function test_natures_ce_falls_back_to_nature_when_nature_ce_is_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 'san-token',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D-SAN-2024',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 12,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-nature-1',
            'libelle' => 'Mandat nature 1',
            'montant' => 1000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'nature' => '64-9-1-15',
            'nature_ce' => null,
            'chapitre' => '60',
            'statut' => 'Transmis',
            'statut_code' => 'S00',
            'beneficiaire' => 'Fournisseur A',
            'source_numero_mandat' => 'M-001',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
            'date_mouvement' => '2024-12-10',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-nature-2',
            'libelle' => 'Mandat nature 2',
            'montant' => 2000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'nature' => '60-5-4-01',
            'nature_ce' => null,
            'chapitre' => '61',
            'statut' => 'Admis',
            'statut_code' => 'S30',
            'beneficiaire' => 'Fournisseur B',
            'source_numero_mandat' => 'M-002',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
            'date_mouvement' => '2024-12-11',
        ]);

        $response = $this->getJson('/api/v1/natures-ce?' . http_build_query([
            'region_code' => 'SAN',
            'annee' => 2024,
            'mois' => 12,
            'type' => 'depense',
        ]));

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.natures_ce_count', 2);
        $response->assertJsonPath('stats.natures_ce.0.code', '60-5-4-01');
        $response->assertJsonPath('stats.natures_ce.1.code', '64-9-1-15');

        $filteredResponse = $this->getJson('/api/v1/natures-ce?' . http_build_query([
            'region_code' => 'SAN',
            'annee' => 2024,
            'mois' => 12,
            'nature_ce' => '64-9-1-15',
            'type' => 'depense',
        ]));

        $filteredResponse->assertOk();
        $filteredResponse->assertJsonPath('meta.total', 1);
        $filteredResponse->assertJsonPath('data.0.libelle', 'Mandat nature 1');
    }
}
