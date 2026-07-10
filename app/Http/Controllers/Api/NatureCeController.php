<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NatureCeQueryService;
use App\Support\CsvExporter;
use Illuminate\Http\Request;

class NatureCeController extends Controller
{
    public function __construct(private readonly NatureCeQueryService $service)
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
            'mandats-par-nature-ce.csv',
            ['Date', 'Nature CE', 'Chapitre', 'Libellé', 'Statut', 'Bénéficiaire', 'N° mandat', 'Montant (FCFA)'],
            $rows->map(fn ($m) => [
                $m->date_mouvement?->format('d/m/Y') ?? '',
                $m->nature_ce ?: ($m->nature ?? ''),
                $m->chapitre ?? '',
                $m->libelle ?? '',
                $m->statut ?? '',
                $m->beneficiaire ?? '',
                $m->source_numero_mandat ?? '',
                number_format((float) $m->montant, 0, ',', ' '),
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
            'nature_ce' => 'nullable|string|max:100',
            'programme' => 'nullable|string|max:50',
            'statut' => 'nullable|string|max:100',
            'chapitre' => 'nullable|string|max:100',
            'type' => 'nullable|in:depense,recette',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
    }
}
