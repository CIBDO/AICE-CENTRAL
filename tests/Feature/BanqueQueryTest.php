<?php

namespace Tests\Feature;

use App\Models\BanquePush;
use App\Models\Dashboard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BanqueQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_banques_api_dedupes_by_entry_no_and_sums_debit_credit(): void
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
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 10_000,
            'annee' => 2024,
            'mois' => 1,
        ]);

        foreach ([
            ['regional_id' => 'B1', 'entry_no' => '5001', 'debit' => 1_000, 'credit' => 0, 'date' => '2024-03-10'],
            ['regional_id' => 'B2', 'entry_no' => '5002', 'debit' => 0, 'credit' => 2_500, 'date' => '2024-03-11'],
            ['regional_id' => 'B3', 'entry_no' => '5001', 'debit' => 1_000, 'credit' => 0, 'date' => '2024-03-10'],
        ] as $row) {
            BanquePush::create([
                'dashboard_id' => $dashboard->id,
                'regional_id' => $row['regional_id'],
                'numero_compte' => 'CPT-001',
                'libelle' => 'Compte Trésor',
                'date_mouvement' => $row['date'],
                'debit' => $row['debit'],
                'credit' => $row['credit'],
                'solde' => 50_000,
                'entry_no' => $row['entry_no'],
                'exercice' => 2024,
            ]);
        }

        $response = $this->getJson('/api/v1/banques?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 2);
        // Convention État : débit NAV sortie → crédit État ; crédit NAV entrée → débit État
        $response->assertJsonPath('stats.totaux.total_debit', 2500);
        $response->assertJsonPath('stats.totaux.total_credit', 1000);
        $response->assertJsonPath('stats.totaux.flux_net', 1500);
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_banques_filter_by_numero_compte(): void
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
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'B-A',
            'numero_compte' => 'CPT-A',
            'libelle' => 'Compte A',
            'date_mouvement' => '2024-06-01',
            'debit' => 0,
            'credit' => 800,
            'solde' => 10_000,
            'entry_no' => '6001',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'B-B',
            'numero_compte' => 'CPT-B',
            'libelle' => 'Compte B',
            'date_mouvement' => '2024-06-02',
            'debit' => 300,
            'credit' => 0,
            'solde' => 5_000,
            'entry_no' => '6002',
            'exercice' => 2024,
        ]);

        $response = $this->getJson('/api/v1/banques?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31&numero_compte=CPT-A');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.count', 1);
        $response->assertJsonPath('stats.totaux.total_debit', 800);
    }

    public function test_banques_apply_state_convention_for_negative_nav_debit(): void
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
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 1,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-2024-recu',
            'numero_compte' => 'RECU',
            'libelle' => 'Banque Reçu de versement',
            'date_mouvement' => '2024-08-31',
            'debit' => -6_517_990,
            'credit' => 0,
            'solde' => 0,
            'entry_no' => '7001',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-2024-dep',
            'numero_compte' => 'SAN-BDM-DEP',
            'libelle' => 'BDM DEPENSES',
            'date_mouvement' => '2024-10-18',
            'debit' => 0,
            'credit' => 291_268,
            'solde' => 110_914_885,
            'entry_no' => '7002',
            'exercice' => 2024,
        ]);

        $response = $this->getJson('/api/v1/banques?region_code=SAN&date_debut=2024-01-01&date_fin=2024-12-31');

        $response->assertOk();
        $response->assertJsonPath('stats.totaux.total_debit', 291268);
        $response->assertJsonPath('stats.totaux.total_credit', 6517990);
        $response->assertJsonPath('stats.totaux.flux_net', 291268 - 6517990);
    }
}
