<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MouvementQueryService;
use App\Support\CsvExporter;
use Illuminate\Http\Request;

class MouvementController extends Controller
{
    public function __construct(private readonly MouvementQueryService $service)
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

    public function show(Request $request, int $id)
    {
        $filters = $request->validate([
            'region_code' => 'nullable|string|max:50',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut',
            'annee' => 'nullable|integer|min:2000|max:2100',
            'mois' => 'nullable|integer|min:1|max:12',
        ]);

        $mouvement = $this->service->find($id, $filters);

        if (!$mouvement) {
            return response()->json(['message' => 'Mandat introuvable.'], 404);
        }

        $region = $mouvement->dashboard?->region;

        return response()->json([
            'status' => 'OK',
            'data' => $mouvement,
            'related' => $this->service->related($mouvement, $filters),
            'context' => [
                'region_code' => $region?->code,
                'region_nom' => $region?->nom,
                'annee' => $mouvement->annee,
                'mois' => $mouvement->mois,
                'date_debut' => $filters['date_debut'] ?? null,
                'date_fin' => $filters['date_fin'] ?? null,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->filters($request);
        $rows = $this->service->exportRows($filters);

        return CsvExporter::download(
            'mandats.csv',
            ['Date', 'Type de mandat', 'Libellé', 'Statut', 'Code programme', 'Bénéficiaire', 'N° mandat', 'Nature CE', 'Montant (FCFA)'],
            $rows->map(fn ($m) => [
                $m->date_mouvement?->format('d/m/Y') ?? '',
                $m->type_mandat_libelle ?? $m->type_mandat ?? '',
                $m->libelle ?? '',
                $m->statut ?? '',
                $m->code_programme ?? '',
                $m->beneficiaire ?? '',
                $m->source_numero_mandat ?? '',
                $m->nature_ce ?? '',
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
            'type' => 'nullable|in:depense,recette',
            'statut' => 'nullable|string|max:100',
            'type_mandat' => 'nullable|string|max:5',
            'programme' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);
    }
}
