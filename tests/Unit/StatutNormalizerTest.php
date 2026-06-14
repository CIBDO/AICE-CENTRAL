<?php

namespace Tests\Unit;

use App\Models\Mouvement;
use App\Support\MandatCounter;
use App\Support\StatutNormalizer;
use Tests\TestCase;

class StatutNormalizerTest extends TestCase
{
    public function test_normalizes_nav_status_codes(): void
    {
        $this->assertSame('Payé', StatutNormalizer::normalize(null, 'S92'));
        $this->assertSame('Admis', StatutNormalizer::normalize(null, 'S30'));
        $this->assertSame('Réglé', StatutNormalizer::normalize('Réglé', 'S29'));
    }

    public function test_excludes_diagnostic_statuses(): void
    {
        $this->assertTrue(StatutNormalizer::isExcluded('DIAG', null));
        $this->assertFalse(StatutNormalizer::isExcluded('Payé', 'S92'));
    }

    public function test_montant_for_admis_uses_solde_when_available(): void
    {
        $m = new Mouvement([
            'statut' => 'Admis',
            'statut_code' => 'S30',
            'montant' => 1000,
            'solde_a_payer' => -750,
        ]);

        $this->assertSame(750.0, StatutNormalizer::montantForStatut($m));
    }

    public function test_par_statut_groups_normalized_labels(): void
    {
        $rows = collect([
            new Mouvement([
                'type_mandat' => '0',
                'source_numero_mandat' => 'M-1',
                'date_mouvement' => '2024-06-01',
                'statut' => 'Payé',
                'statut_code' => 'S92',
                'montant' => 100,
                'montant_paye' => 100,
            ]),
            new Mouvement([
                'type_mandat' => '0',
                'source_numero_mandat' => 'M-1',
                'date_mouvement' => '2024-06-01',
                'statut' => 'PAYE',
                'statut_code' => 'S92',
                'montant' => 100,
                'montant_paye' => 100,
            ]),
            new Mouvement([
                'type_mandat' => '1',
                'source_numero_mandat' => 'S-1',
                'date_mouvement' => '2024-06-02',
                'statut' => 'Admis',
                'statut_code' => 'S30',
                'montant' => 50,
                'solde_a_payer' => -50,
            ]),
        ]);

        $result = MandatCounter::parStatut($rows);

        $this->assertSame(1, collect($result)->firstWhere('statut', 'Payé')['count']);
        $this->assertSame(1, collect($result)->firstWhere('statut', 'Admis')['count']);
    }
}
