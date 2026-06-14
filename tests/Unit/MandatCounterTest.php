<?php

namespace Tests\Unit;

use App\Models\Mouvement;
use App\Support\MandatCounter;
use Tests\TestCase;

class MandatCounterTest extends TestCase
{
    public function test_par_type_deduplicates_by_numero_type_and_date(): void
    {
        $rows = collect([
            new Mouvement([
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
                'source_numero_mandat' => 'M-001',
                'date_mouvement' => '2024-03-10',
                'montant' => 100,
                'type' => 'depense',
            ]),
            new Mouvement([
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
                'source_numero_mandat' => 'M-001',
                'date_mouvement' => '2024-03-10',
                'montant' => 100,
                'type' => 'depense',
            ]),
            new Mouvement([
                'type_mandat' => '1',
                'type_mandat_libelle' => 'Salaire',
                'source_numero_mandat' => 'S-001',
                'date_mouvement' => '2024-03-11',
                'montant' => 50,
                'type' => 'depense',
            ]),
        ]);

        $result = MandatCounter::parType($rows);

        $this->assertSame(3, count($result));
        $this->assertSame('Matériel', $result[0]['libelle']);
        $this->assertSame(1, $result[0]['count']);
        $this->assertSame(1, $result[1]['count']);
        $this->assertSame(0, $result[2]['count']);
    }

    public function test_montant_paye_total_sums_paye_and_regle_mandats(): void
    {
        $rows = collect([
            new Mouvement([
                'type' => 'depense',
                'type_mandat' => '0',
                'source_numero_mandat' => 'M-1',
                'date_mouvement' => '2024-03-10',
                'montant' => 100,
                'montant_paye' => 90,
                'statut' => 'Payé',
            ]),
            new Mouvement([
                'type' => 'depense',
                'type_mandat' => '1',
                'source_numero_mandat' => 'M-2',
                'date_mouvement' => '2024-03-11',
                'montant' => 200,
                'montant_paye' => 200,
                'statut' => 'Réglé',
            ]),
            new Mouvement([
                'type' => 'depense',
                'type_mandat' => '0',
                'source_numero_mandat' => 'M-3',
                'date_mouvement' => '2024-03-12',
                'montant' => 300,
                'statut' => 'Admis',
            ]),
        ]);

        $this->assertSame(290.0, MandatCounter::montantPayeTotal($rows));
    }
}
