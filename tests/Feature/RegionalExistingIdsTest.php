<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalExistingIdsTest extends TestCase
{
    use RefreshDatabase;

    private function createRegion(string $code = 'SKO'): Region
    {
        return Region::create([
            'code' => $code,
            'nom' => 'Région ' . $code,
            'actif' => true,
            'token' => 'test-token-existing-ids',
            'db_host' => 'localhost',
            'db_port' => 1433,
            'db_database' => 'dummy',
            'db_username' => 'dummy',
            'db_password' => 'dummy',
            'db_charset' => 'utf8',
            'source_type' => 'api',
        ]);
    }

    public function test_existing_ids_returns_stored_regional_ids_for_region_and_periode(): void
    {
        $region = $this->createRegion('SKO');

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SKO',
            'regional_id' => 'DASHBOARD-SKO-2026',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2026,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'MVT-2026-aaa111',
            'libelle' => 'Mandat 1',
            'montant' => 1000,
            'type' => 'depense',
            'annee' => 2026,
            'mois' => 1,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'MVT-2026-bbb222',
            'libelle' => 'Mandat 2',
            'montant' => 2000,
            'type' => 'depense',
            'annee' => 2026,
            'mois' => 2,
        ]);

        $response = $this->withHeader('X-REGION-TOKEN', $region->token)
            ->getJson('/api/v1/regions/SKO/mouvements/existing-ids?periode=2026');

        $response->assertOk();
        $response->assertJson([
            'status' => 'OK',
            'region_code' => 'SKO',
            'periode' => 2026,
            'count' => 2,
        ]);
        $response->assertJsonFragment(['regional_ids' => ['MVT-2026-aaa111', 'MVT-2026-bbb222']]);
    }

    public function test_existing_ids_rejects_mismatched_region_code(): void
    {
        $region = $this->createRegion('SKO');

        $response = $this->withHeader('X-REGION-TOKEN', $region->token)
            ->getJson('/api/v1/regions/RGF/mouvements/existing-ids?periode=2026');

        $response->assertForbidden();
        $response->assertJsonFragment([
            'status' => 'error',
        ]);
    }

    public function test_existing_ids_requires_periode(): void
    {
        $region = $this->createRegion('SKO');

        $response = $this->withHeader('X-REGION-TOKEN', $region->token)
            ->getJson('/api/v1/regions/SKO/mouvements/existing-ids');

        $response->assertUnprocessable();
    }

    public function test_existing_ids_excludes_other_years(): void
    {
        $region = $this->createRegion('SKO');

        $dashboard2025 = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SKO',
            'regional_id' => 'DASHBOARD-SKO-2025',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2025,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard2025->id,
            'regional_id' => 'MVT-2025-old',
            'libelle' => 'Ancien',
            'montant' => 500,
            'type' => 'depense',
            'annee' => 2025,
            'mois' => 12,
        ]);

        $response = $this->withHeader('X-REGION-TOKEN', $region->token)
            ->getJson('/api/v1/regions/SKO/mouvements/existing-ids?periode=2026');

        $response->assertOk();
        $response->assertJson([
            'count' => 0,
            'regional_ids' => [],
        ]);
    }
}
