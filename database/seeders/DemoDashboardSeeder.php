<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Services\DashboardReceiveService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoDashboardSeeder extends Seeder
{
    private const STATUTS = ['Payé', 'Payé', 'Payé', 'Admis', 'Admis', 'Rejeté', 'Rejeté - Erreur comptable'];

    private const PROGRAMMES = ['P001', 'P002', 'P003', 'P004', 'P005'];

    private const BENEFICIAIRES = [
        'Ministère des Finances', 'Direction du Budget', 'Agence Comptable Centrale',
        'Fournisseur Alpha SARL', 'Fournisseur Beta SA', 'Régie des Transports',
    ];

    private const CLIENTS = [
        ['no' => 'CLT-001', 'name' => 'Direction Générale des Impôts'],
        ['no' => 'CLT-002', 'name' => 'Office des Postes'],
        ['no' => 'CLT-003', 'name' => 'Agence Nationale des Titres'],
        ['no' => 'CLT-004', 'name' => 'Collectivité Territoriale'],
        ['no' => 'CLT-005', 'name' => 'Entreprise Publique Énergie'],
    ];

    public function run(): void
    {
        $service = app(DashboardReceiveService::class);
        $now = Carbon::now();

        foreach (Region::query()->actives()->ordered()->get() as $region) {
            foreach ([0, 1] as $offset) {
                $date = $now->copy()->subMonths($offset);
                $annee = $date->year;
                $mois = $date->month;
                $regionalId = sprintf('DEMO-%s-%04d-%02d', $region->code, $annee, $mois);

                $mouvements = $this->buildMouvements($region->code, $annee, $mois);
                $banques = $this->buildBanques($region->code, $annee, $mois);
                $recettes = $this->buildRecettesClients($region->code, $annee, $mois);

                $totalOrdonnance = collect($mouvements)->where('type', 'depense')->sum('montant');
                $totalRecouvrements = collect($mouvements)->where('type', 'recette')->sum('montant')
                    + collect($recettes)->sum('montant');
                $totalMontantPaye = collect($mouvements)
                    ->where('type', 'depense')
                    ->filter(fn (array $m) => in_array($m['statut'] ?? '', ['Payé', 'Réglé'], true))
                    ->sum(fn (array $m) => (float) ($m['montant_paye'] ?? $m['montant'] ?? 0));

                $service->handle($region->id, [
                    'local_id' => $region->code,
                    'regional_id' => $regionalId,
                    'total_ordonnance' => round($totalOrdonnance, 2),
                    'total_recouvrements_4121' => round($totalRecouvrements, 2),
                    'total_montant_paye' => round($totalMontantPaye, 2),
                    'solde' => round($totalRecouvrements - $totalOrdonnance, 2),
                    'tresorerie_reelle' => round($totalRecouvrements * 0.15, 2),
                    'annee' => $annee,
                    'mois' => $mois,
                    'date_debut' => $date->copy()->startOfMonth()->toDateString(),
                    'date_fin' => $date->copy()->endOfMonth()->toDateString(),
                    'mouvements' => $mouvements,
                    'banques' => $banques,
                    'recettes_clients' => $recettes,
                ]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function buildMouvements(string $regionCode, int $annee, int $mois): array
    {
        $items = [];
        $daysInMonth = Carbon::create($annee, $mois, 1)->daysInMonth;

        for ($i = 1; $i <= 72; $i++) {
            $day = ($i % $daysInMonth) + 1;
            $date = sprintf('%04d-%02d-%02d', $annee, $mois, min($day, $daysInMonth));
            $isRecette = $i % 6 === 0;
            $statut = self::STATUTS[$i % count(self::STATUTS)];
            $montant = rand(150_000, 12_000_000);

            $items[] = [
                'regional_id' => sprintf('%s-M-%04d-%02d-%04d', $regionCode, $annee, $mois, $i),
                'libelle' => $isRecette
                    ? "Recette encaissement dossier {$i}"
                    : "Mandat dépense n° {$regionCode}-{$i}",
                'montant' => $montant,
                'type' => $isRecette ? 'recette' : 'depense',
                'date_mouvement' => $date,
                'statut' => $isRecette ? 'Payé' : $statut,
                'type_mandat' => (string) ($i % 3),
                'type_mandat_libelle' => ['Matériel', 'Salaire', 'Reversement'][$i % 3],
                'code_programme' => self::PROGRAMMES[$i % count(self::PROGRAMMES)],
                'programme' => 'Programme ' . self::PROGRAMMES[$i % count(self::PROGRAMMES)],
                'chapitre' => 'CH-' . (($i % 5) + 1),
                'nature_ce' => 'CE-' . (($i % 8) + 1),
                'beneficiaire' => self::BENEFICIAIRES[$i % count(self::BENEFICIAIRES)],
                'source_numero_mandat' => sprintf('MDT-%s-%05d', $regionCode, $i),
            ];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildBanques(string $regionCode, int $annee, int $mois): array
    {
        $items = [];
        $comptes = [
            ['num' => '001234567890', 'lib' => 'Compte Trésor Principal'],
            ['num' => '001234567891', 'lib' => 'Compte Dépenses Courantes'],
            ['num' => '001234567892', 'lib' => 'Compte Recettes Fiscales'],
        ];
        $daysInMonth = Carbon::create($annee, $mois, 1)->daysInMonth;
        $solde = 50_000_000;

        for ($i = 1; $i <= 36; $i++) {
            $compte = $comptes[$i % count($comptes)];
            $debit = $i % 3 === 0 ? rand(500_000, 8_000_000) : 0;
            $credit = $i % 3 !== 0 ? rand(500_000, 8_000_000) : 0;
            $solde += $credit - $debit;
            $day = ($i % $daysInMonth) + 1;

            $items[] = [
                'regional_id' => sprintf('%s-B-%04d-%02d-%04d', $regionCode, $annee, $mois, $i),
                'numero_compte' => $compte['num'],
                'libelle' => $compte['lib'],
                'date_mouvement' => sprintf('%04d-%02d-%02d', $annee, $mois, min($day, $daysInMonth)),
                'debit' => $debit,
                'credit' => $credit,
                'solde' => $solde,
                'reference' => 'REF-' . $regionCode . '-' . $i,
                'type_document' => $i % 2 === 0 ? 'Virement' : 'Chèque',
                'description' => "Opération bancaire {$i} — {$regionCode}",
                'exercice' => $annee,
            ];
        }

        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    private function buildRecettesClients(string $regionCode, int $annee, int $mois): array
    {
        $items = [];
        $daysInMonth = Carbon::create($annee, $mois, 1)->daysInMonth;

        for ($i = 1; $i <= 24; $i++) {
            $client = self::CLIENTS[$i % count(self::CLIENTS)];
            $day = ($i % $daysInMonth) + 1;

            $items[] = [
                'regional_id' => sprintf('%s-R-%04d-%02d-%04d', $regionCode, $annee, $mois, $i),
                'client_no' => $client['no'],
                'client_name' => $client['name'],
                'gl_account' => '701' . ($i % 5),
                'date_posting' => sprintf('%04d-%02d-%02d', $annee, $mois, min($day, $daysInMonth)),
                'montant' => rand(250_000, 15_000_000),
                'description' => "Recette client {$client['name']} — {$regionCode}",
                'source_no' => 'SRC-' . $i,
                'exercice' => $annee,
            ];
        }

        return $items;
    }
}
