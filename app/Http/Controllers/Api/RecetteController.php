<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecetteQueryService;
use App\Support\CsvExporter;
use Illuminate\Http\Request;

class RecetteController extends Controller
{
    public function __construct(private readonly RecetteQueryService $service)
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
            'recettes-clients.csv',
            ['Date', 'N° client', 'Client', 'Compte GL', 'Description', 'Montant (FCFA)'],
            $rows->map(fn (array $r) => [
                isset($r['date_posting']) ? date('d/m/Y', strtotime((string) $r['date_posting'])) : '',
                $r['client_no'] ?? '',
                $r['client_name'] ?? '',
                $r['gl_account'] ?? '',
                $r['description'] ?? '',
                number_format((float) ($r['montant'] ?? 0), 0, ',', ' '),
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
            'client_no' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
    }
}
