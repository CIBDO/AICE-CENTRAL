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

class ProgrammeQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_programmes_api_returns_aggregated_stats(): void
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

        $response = $this->getJson('/api/v1/programmes?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertJsonStructure([
            'stats' => ['totaux', 'programmes', 'par_statut', 'par_chapitre', 'par_jour'],
            'data',
            'meta',
        ]);
        $response->assertJsonPath('stats.totaux.programmes_count', fn ($v) => $v >= 1);
    }

    public function test_programmes_export_returns_csv(): void
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

        $response = $this->get('/api/v1/programmes/export?region_code=RGF&annee=' . now()->year . '&mois=' . now()->month);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_programmes_stats_count_nav_lines_and_not_distinct_mandats(): void
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
            'libelle' => 'Mandat ligne 1',
            'montant' => 1000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'programme' => 'Programme 2041',
            'code_programme' => '2041',
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
            'libelle' => 'Mandat ligne 2',
            'montant' => 2000,
            'type' => 'depense',
            'annee' => 2024,
            'mois' => 12,
            'programme' => 'Programme 2041',
            'code_programme' => '2041',
            'chapitre' => '60',
            'statut' => 'Admis',
            'statut_code' => 'S30',
            'beneficiaire' => 'Fournisseur A',
            'source_numero_mandat' => 'M-001',
            'type_mandat' => '0',
            'type_mandat_libelle' => 'Matériel',
        ]);

        $response = $this->getJson('/api/v1/programmes?region_code=SAN&annee=2024&mois=12');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.mandats_count', 2);
        $response->assertJsonPath('stats.programmes.0.code', '2041');
        $response->assertJsonPath('stats.programmes.0.count', 2);
    }
}
