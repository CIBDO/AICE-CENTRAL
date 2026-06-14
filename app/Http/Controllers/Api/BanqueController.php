<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BanqueQueryService;
use App\Support\CsvExporter;
use Illuminate\Http\Request;

class BanqueController extends Controller
{
    public function __construct(private readonly BanqueQueryService $service)
    {
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $paginator = $this->service->paginate($filters);

        return response()->json([
            'status' => 'OK',
            'stats' => $this->service->stats($filters),
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $rows = $this->service->exportRows($filters);

        return CsvExporter::download(
            'banques.csv',
            ['Date', 'Compte', 'Libellé', 'Référence', 'Type', 'Débit', 'Crédit', 'Solde'],
            $rows->map(fn ($b) => [
                $b->date_mouvement?->format('d/m/Y') ?? '',
                $b->numero_compte ?? '',
                $b->libelle ?? '',
                $b->reference ?? '',
                $b->type_document ?? '',
                number_format((float) $b->debit, 0, ',', ' '),
                number_format((float) $b->credit, 0, ',', ' '),
                number_format((float) $b->solde, 0, ',', ' '),
            ]),
        );
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'region_code' => 'nullable|string|max:50',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
            'numero_compte' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
    }
}
