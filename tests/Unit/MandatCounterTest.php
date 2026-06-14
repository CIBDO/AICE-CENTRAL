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
            ]),
            new Mouvement([
                'type_mandat' => '0',
                'type_mandat_libelle' => 'Matériel',
                'source_numero_mandat' => 'M-001',
                'date_mouvement' => '2024-03-10',
                'montant' => 100,
            ]),
            new Mouvement([
                'type_mandat' => '1',
                'type_mandat_libelle' => 'Salaire',
                'source_numero_mandat' => 'S-001',
                'date_mouvement' => '2024-03-11',
                'montant' => 50,
            ]),
        ]);

        $result = MandatCounter::parType($rows);

        $this->assertSame(3, count($result));
        $this->assertSame('Matériel', $result[0]['libelle']);
        $this->assertSame(1, $result[0]['count']);
        $this->assertSame(1, $result[1]['count']);
        $this->assertSame(0, $result[2]['count']);
    }
}
