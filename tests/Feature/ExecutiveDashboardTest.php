<?php

namespace Tests\Feature;

use App\Models\BanquePush;
use App\Models\Dashboard;
use App\Models\Mouvement;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_kpis_returns_indicators(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'RGF',
            'nom' => 'Région du Fleuve',
            'actif' => true,
            'token' => 't1',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'RGF',
            'regional_id' => 'D1',
            'total_ordonnance' => 400,
            'total_recouvrements_4121' => 1000,
            'total_montant_paye' => 100,
            'solde' => 600,
            'tresorerie_reelle' => 50,
            'annee' => 2024,
            'mois' => 6,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M1',
            'libelle' => 'Mandat test',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '0',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Payé',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'M2',
            'libelle' => 'Mandat rejeté',
            'montant' => 50,
            'type' => 'depense',
            'type_mandat' => '1',
            'annee' => 2024,
            'mois' => 6,
            'statut' => 'Rejeté',
        ]);

        BanquePush::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'BANQUE-ENTRY-RGF-1',
            'numero_compte' => 'CPT-RGF',
            'libelle' => 'Compte RGF',
            'date_mouvement' => '2024-06-14',
            'debit' => 0,
            'credit' => 0,
            'solde' => 4200,
            'entry_no' => 'RGF-7001',
            'exercice' => 2024,
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonPath('data.indicateurs.mandats_total', 2);
        $response->assertJsonPath('data.indicateurs.recouvrements_4121_total', 1000);
        $response->assertJsonPath('data.indicateurs.tresorerie_reelle_total', 4200);
        $response->assertJsonPath('data.parametres.compare_mode', 'mois_precedent');
        $response->assertJsonPath('data.workflow.admis.count', 0);
        $response->assertJsonPath('data.workflow.total_hors_rejet.count', 0);
        $response->assertJsonPath('data.workflow_aging.count', 0);
        $response->assertJsonPath('data.meta.mandats_count', 2);
        $response->assertJsonPath('data.meta.recettes_count', 0);
        $response->assertJsonPath('data.meta.mouvements_count', 2);
    }

    public function test_executive_kpis_count_nav_lines_for_mandats_total(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'SAN',
            'nom' => 'San',
            'actif' => true,
            'token' => 't-san',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'SAN',
            'regional_id' => 'D-SAN',
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
            'libelle' => 'Mandat SAN ligne 1',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MDT-001',
            'annee' => 2024,
            'mois' => 12,
            'date_mouvement' => '2024-12-10',
            'statut' => 'Transmis',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'push-2',
            'libelle' => 'Mandat SAN ligne 2',
            'montant' => 200,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MDT-001',
            'annee' => 2024,
            'mois' => 12,
            'date_mouvement' => '2024-12-11',
            'statut' => 'Visé',
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?' . http_build_query([
            'region_code' => 'SAN',
            'date_debut' => '2024-01-01',
            'date_fin' => '2024-12-31',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.indicateurs.mandats_total', 2);
        $response->assertJsonPath('data.meta.mandats_count', 2);
    }

    public function test_executive_kpis_return_workflow_backlog_breakdown(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'BKO',
            'nom' => 'Bamako',
            'actif' => true,
            'token' => 't-bko',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'BKO',
            'regional_id' => 'D-BKO',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 7,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'bko-admis',
            'libelle' => 'Mandat admis',
            'montant' => 800,
            'solde_a_payer' => -600,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MDT-BKO-1',
            'annee' => 2024,
            'mois' => 7,
            'date_mouvement' => '2024-07-03',
            'statut' => 'Admis',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'bko-vise',
            'libelle' => 'Mandat visé',
            'montant' => 400,
            'type' => 'depense',
            'type_mandat' => '1',
            'source_numero_mandat' => 'MDT-BKO-2',
            'annee' => 2024,
            'mois' => 7,
            'date_mouvement' => '2024-07-04',
            'statut' => 'Visé',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'bko-rejet',
            'libelle' => 'Mandat rejeté',
            'montant' => 250,
            'type' => 'depense',
            'type_mandat' => '2',
            'source_numero_mandat' => 'MDT-BKO-3',
            'annee' => 2024,
            'mois' => 7,
            'date_mouvement' => '2024-07-05',
            'statut' => 'Rejeté',
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?annee=2024&mois=7&region_code=BKO');

        $response->assertOk();
        $response->assertJsonPath('data.workflow.admis.count', 1);
        $response->assertJsonPath('data.workflow.admis.montant', 800);
        $response->assertJsonPath('data.workflow.autres_non_payes.count', 1);
        $response->assertJsonPath('data.workflow.autres_non_payes.montant', 400);
        $response->assertJsonPath('data.workflow.total_hors_rejet.count', 2);
        $response->assertJsonPath('data.workflow.total_hors_rejet.montant', 1200);
        $response->assertJsonPath('data.workflow_aging.count', 2);
    }

    public function test_executive_alertes_lists_region_without_data(): void
    {
        Sanctum::actingAs(User::factory()->create());

        Region::create([
            'code' => 'RGD',
            'nom' => 'Région de Dakar',
            'actif' => true,
            'token' => 't2',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $response = $this->getJson('/api/v1/executive/alertes?annee=2024&mois=6');

        $response->assertOk();
        $response->assertJsonFragment(['categorie' => 'donnees']);
    }

    public function test_executive_kpis_accepts_parametrized_comparison_mode_for_date_ranges(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'KAY',
            'nom' => 'Kayes',
            'actif' => true,
            'token' => 't-kay',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'KAY',
            'regional_id' => 'D-KAY',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 7,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'kay-july',
            'libelle' => 'Mandat juillet',
            'montant' => 200,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'KAY-07',
            'annee' => 2024,
            'mois' => 7,
            'date_mouvement' => '2024-07-10',
            'statut' => 'Payé',
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'kay-june',
            'libelle' => 'Mandat juin',
            'montant' => 100,
            'type' => 'depense',
            'type_mandat' => '1',
            'source_numero_mandat' => 'KAY-06',
            'annee' => 2024,
            'mois' => 6,
            'date_mouvement' => '2024-06-10',
            'statut' => 'Payé',
        ]);

        $response = $this->getJson('/api/v1/executive/kpis?' . http_build_query([
            'region_code' => 'KAY',
            'date_debut' => '2024-07-01',
            'date_fin' => '2024-07-31',
            'compare_mode' => 'mois_precedent',
        ]));

        $response->assertOk();
        $response->assertJsonPath('data.parametres.compare_mode', 'mois_precedent');
        $response->assertJsonPath('data.comparaison_reference.label', 'mois précédent');
        $response->assertJsonPath('data.comparaison_reference.ordonnance_evolution_pct', 100);
    }

    public function test_executive_alertes_use_parametrized_workflow_sla(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $region = Region::create([
            'code' => 'MOP',
            'nom' => 'Mopti',
            'actif' => true,
            'token' => 't-mop',
            'source_type' => 'api',
            'ordre' => 1,
        ]);

        $dashboard = Dashboard::create([
            'region_id' => $region->id,
            'local_id' => 'MOP',
            'regional_id' => 'D-MOP',
            'total_ordonnance' => 0,
            'total_recouvrements_4121' => 0,
            'total_montant_paye' => 0,
            'solde' => 0,
            'tresorerie_reelle' => 0,
            'annee' => 2024,
            'mois' => 7,
        ]);

        Mouvement::create([
            'dashboard_id' => $dashboard->id,
            'regional_id' => 'mop-vise',
            'libelle' => 'Mandat visé ancien',
            'montant' => 250,
            'type' => 'depense',
            'type_mandat' => '0',
            'source_numero_mandat' => 'MOP-01',
            'annee' => 2024,
            'mois' => 7,
            'date_mouvement' => '2024-07-01',
            'statut' => 'Visé',
        ]);

        $response = $this->getJson('/api/v1/executive/alertes?' . http_build_query([
            'region_code' => 'MOP',
            'date_debut' => '2024-07-01',
            'date_fin' => '2024-07-10',
            'sla_warning_days' => 3,
            'sla_critical_days' => 5,
        ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => 'sla_workflow_critique',
            'categorie' => 'workflow',
        ]);
    }
}
