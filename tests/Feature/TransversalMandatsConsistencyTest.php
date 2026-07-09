<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransversalMandatsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mandats_programmes_and_natures_ce_use_same_nav_counting_logic(): void
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

        $baseRows = [
            [
                'regional_id' => 'nav-1',
                'libelle' => 'Mandat 1',
                'montant' => 1000,
                'programme' => 'Programme 2041',
                'code_programme' => '2041',
                'nature' => 'Nature CE 1',
                'nature_ce' => 'CE-1',
                'chapitre' => '60',
                'statut' => 'Transmis',
                'statut_code' => 'S00',
                'source_numero_mandat' => 'M-001',
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
            ],
            [
                'regional_id' => 'nav-2',
                'libelle' => 'Mandat 2',
                'montant' => 2000,
                'programme' => 'Programme 2041',
                'code_programme' => '2041',
                'nature' => 'Nature CE 2',
                'nature_ce' => 'CE-2',
                'chapitre' => '60',
                'statut' => 'Admis',
                'statut_code' => 'S30',
                'source_numero_mandat' => 'M-002',
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
            ],
            [
                'regional_id' => 'nav-3',
                'libelle' => 'Mandat 3',
                'montant' => 3000,
                'programme' => 'Programme 2054',
                'code_programme' => '2054',
                'nature' => 'Nature CE 1',
                'nature_ce' => 'CE-1',
                'chapitre' => '61',
                'statut' => 'Payé',
                'statut_code' => 'S92',
                'source_numero_mandat' => 'M-003',
                'type_mandat' => '1',
                'type_mandat_libelle' => 'Salaire',
            ],
            [
                'regional_id' => 'nav-4',
                'libelle' => 'Mandat 4',
                'montant' => 4000,
                'programme' => 'Programme 2054',
                'code_programme' => '2054',
                'nature' => 'Nature CE 2',
                'nature_ce' => 'CE-2',
                'chapitre' => '61',
                'statut' => 'Visé',
                'statut_code' => 'S03',
                'source_numero_mandat' => 'M-004',
                'type_mandat' => '2',
                'type_mandat_libelle' => 'Reversement',
            ],
            [
                // Exclu des stats NAV
                'regional_id' => 'nav-test',
                'libelle' => 'Mandat test',
                'montant' => 999,
                'programme' => 'Programme TEST',
                'code_programme' => '2999',
                'nature' => 'Nature TEST',
                'nature_ce' => 'CE-T',
                'chapitre' => '99',
                'statut' => 'TEST',
                'statut_code' => null,
                'source_numero_mandat' => 'M-TEST',
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
            ],
        ];

        foreach ($baseRows as $row) {
            Mouvement::create(array_merge($row, [
                'dashboard_id' => $dashboard->id,
                'type' => 'depense',
                'annee' => 2024,
                'mois' => 12,
                'beneficiaire' => 'Fournisseur A',
                'date_mouvement' => '2024-12-15',
            ]));
        }

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'recette-1',
            'libelle' => 'Recette client',
            'montant' => 5000,
            'type' => 'recette',
            'annee' => 2024,
            'mois' => 12,
            'date_mouvement' => '2024-12-20',
            'programme' => null,
            'code_programme' => null,
            'nature' => null,
            'nature_ce' => null,
            'chapitre' => null,
            'statut' => null,
            'statut_code' => null,
        ]);

        $query = http_build_query([
            'region_code' => 'SAN',
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-12-31',
            'type' => 'depense',
        ]);

        $mouvementsResponse = $this->getJson('/api/v1/mouvements?' . $query);
        $programmesResponse = $this->getJson('/api/v1/programmes?' . $query);
        $naturesResponse = $this->getJson('/api/v1/natures-ce?' . $query);

        $mouvementsResponse->assertOk();
        $programmesResponse->assertOk();
        $naturesResponse->assertOk();

        $expectedNavCount = 4;

        $mouvementsResponse->assertJsonPath('stats.totaux.depenses_count', $expectedNavCount);
        $programmesResponse->assertJsonPath('stats.totaux.mandats_count', $expectedNavCount);
        $naturesResponse->assertJsonPath('stats.totaux.mandats_count', $expectedNavCount);

        $this->assertSame(
            $expectedNavCount,
            collect($programmesResponse->json('stats.programmes'))->sum('count')
        );
        $this->assertSame(
            $expectedNavCount,
            collect($naturesResponse->json('stats.natures_ce'))->sum('count')
        );
    }
}
