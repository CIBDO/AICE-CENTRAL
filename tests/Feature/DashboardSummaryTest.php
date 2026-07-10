<?php

namespace Tests\Feature;

use App\Models\BanquePush;
use App\Models\Dashboard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_kpis_for_region(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 'token-test',
            'source_type' => 'api',
        ]);

        Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'RGF',
            'regional_id' => 'DASH-RGF-2024-06',
            'total_ordonnance' => 400,
            'total_recouvrements_4121' => 1000,
            'total_montant_paye' => 300,
            'solde' => 600,
            'tresorerie_reelle' => 50,
            'annee' => 2024,
            'mois' => 6,
        ]);

        $response = $this->getJson('/api/v1/dashboards/summary?region_code=RGF&annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.kpis.total_recouvrements_4121', 1000);
        $response->assertJsonPath('data.region.code', 'RGF');
    }

    public function test_summary_returns_kpis_for_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'SAN',
            'actif' => true,
            'token' => 'token-san',
            'source_type' => 'api',
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'DASH-SAN-2024',
            'total_ordonnance' => 9999,
            'total_recouvrements_4121' => 9999,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 100,
            'annee' => 2024,
            'mois' => null,
        ]);

        $dashboard->mouvements()->create([
            'regional_id' => 'SAN-M-1',
            'libelle' => 'Recette test',
            'montant' => 500,
            'type' => 'recette',
            'date_mouvement' => '2024-06-15',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Payé',
        ]);

        $dashboard->mouvements()->create([
            'regional_id' => 'SAN-M-2',
            'libelle' => 'Mandat test',
            'montant' => 200,
            'type' => 'depense',
            'date_mouvement' => '2024-06-20',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Admis',
            'type_mandat' => '1',
        ]);

        $response = $this->getJson('/api/v1/dashboards/summary?region_code=SAN&date_debut=2024-06-01&date_fin=2024-06-30');

        $response->assertOk();
        $response->assertJsonPath('data.kpis.total_recouvrements_4121', 500);
        $response->assertJsonPath('data.kpis.total_ordonnance', 200);
        $response->assertJsonPath('data.kpis.solde', 300);
        $response->assertJsonPath('data.workflow.admis.count', 1);
        $response->assertJsonPath('data.workflow.admis.montant', 200);
        $response->assertJsonPath('data.workflow.autres_non_payes.count', 0);
        $response->assertJsonPath('data.workflow.total_hors_rejet.count', 1);
        $response->assertJsonPath('data.meta.mouvements_count', 2);
        $response->assertJsonPath('data.meta.mandats_count', 1);
        $response->assertJsonPath('data.meta.recettes_count', 1);
    }

    public function test_summary_counts_mandats_par_type_as_nav_lignes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'SAN',
            'actif' => true,
            'token' => 'token-san',
            'source_type' => 'api',
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'DASH-SAN-MANDATS',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => null,
        ]);

        foreach (['M-001', 'M-002'] as $index => $numero) {
            $dashboard->mouvements()->create([
                'regional_id' => "SAN-DUP-A-{$index}",
                'libelle' => "Matériel {$numero}",
                'montant' => 100,
                'type' => 'recette',
                'date_mouvement' => '2024-03-10',
                'annee' => 2024,
                'mois' => 3,
                'statut' => 'Payé',
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
                'source_numero_mandat' => $numero,
            ]);
            $dashboard->mouvements()->create([
                'regional_id' => "SAN-DUP-B-{$index}",
                'libelle' => "Matériel dup {$numero}",
                'montant' => 100,
                'type' => 'recette',
                'date_mouvement' => '2024-03-10',
                'annee' => 2024,
                'mois' => 3,
                'statut' => 'Payé',
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
                'source_numero_mandat' => $numero,
            ]);
        }

        $dashboard->mouvements()->create([
            'regional_id' => 'SAN-SAL-1',
            'libelle' => 'Salaire',
            'montant' => 50,
            'type' => 'depense',
            'date_mouvement' => '2024-03-11',
            'annee' => 2024,
            'mois' => 3,
            'statut' => 'Admis',
            'type_mandat' => '1',
            'type_mandat_libelle' => 'Salaire',
            'source_numero_mandat' => 'S-001',
        ]);

        $response = $this->getJson('/api/v1/dashboards/summary?region_code=SAN&date_debut=2024-03-01&date_fin=2024-03-31');

        $response->assertOk();
        $response->assertJsonPath('data.mandats_par_type.0.libelle', 'Matériel');
        $response->assertJsonPath('data.mandats_par_type.0.count', 4);
        $response->assertJsonPath('data.mandats_par_type.1.count', 1);
        $response->assertJsonPath('data.mandats_par_type.2.count', 0);
        $response->assertJsonPath('data.meta.mandats_count', 5);
        $response->assertJsonPath('data.meta.recettes_count', 4);
    }

    public function test_summary_returns_filtered_bank_balance_for_visible_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'SAN',
            'actif' => true,
            'token' => 'token-san',
            'source_type' => 'api',
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'DASH-SAN-BANK',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 99_999,
            'annee' => 2024,
            'mois' => null,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-1',
            'numero_compte' => 'CPT-A',
            'libelle' => 'Compte A',
            'date_mouvement' => '2024-06-05',
            'debit' => 0,
            'credit' => 0,
            'solde' => 1000,
            'entry_no' => '9001',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-2',
            'numero_compte' => 'CPT-A',
            'libelle' => 'Compte A',
            'date_mouvement' => '2024-06-18',
            'debit' => 0,
            'credit' => 0,
            'solde' => 1400,
            'entry_no' => '9002',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-3',
            'numero_compte' => 'CPT-B',
            'libelle' => 'Compte B',
            'date_mouvement' => '2024-06-10',
            'debit' => 0,
            'credit' => 0,
            'solde' => 2000,
            'entry_no' => '9003',
            'exercice' => 2024,
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-4',
            'numero_compte' => 'CPT-A',
            'libelle' => 'Compte A',
            'date_mouvement' => '2024-07-01',
            'debit' => 0,
            'credit' => 0,
            'solde' => 5000,
            'entry_no' => '9004',
            'exercice' => 2024,
        ]);

        $response = $this->getJson('/api/v1/dashboards/summary?region_code=SAN&date_debut=2024-06-01&date_fin=2024-06-30');

        $response->assertOk();
        $response->assertJsonPath('data.kpis.tresorerie_reelle', 3400);
        $response->assertJsonPath('data.meta.mouvements_count', 0);
    }
}
