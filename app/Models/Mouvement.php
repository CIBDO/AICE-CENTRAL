<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mouvement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dashboard_id',
        'regional_id', // ID unique côté régional pour upsert
        'libelle',
        'montant',
        'montant_paye',
        'solde_a_payer',
        'type',
        'date_mouvement',
        'annee',
        'mois',
        'programme',
        'nature',
        // Champs analytiques enrichis
        'code_programme',
        'chapitre',
        'nature_ce',
        'statut',
        'statut_code',
        'beneficiaire',
        'source_numero_mandat',
        'source_id',
        'type_mandat',
        'type_mandat_libelle',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'solde_a_payer' => 'decimal:2',
        'date_mouvement' => 'date',
        'annee' => 'integer',
        'mois' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec le dashboard
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }

    /**
     * Scope pour filtrer par type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope pour filtrer par période (année et mois)
     */
    public function scopeForPeriod($query, int $annee, ?int $mois = null)
    {
        $query->where('annee', $annee);
        if ($mois !== null) {
            $query->where('mois', $mois);
        }
        return $query;
    }

    /**
     * Scope pour filtrer par plage de dates
     */
    public function scopeForDateRange($query, ?string $dateDebut = null, ?string $dateFin = null)
    {
        if ($dateDebut) {
            $query->whereBetween('date_mouvement', [
                $dateDebut,
                $dateFin ?? now()->format('Y-m-d')
            ]);
        }
        return $query;
    }

    /**
     * Scope pour filtrer par programme
     */
    public function scopeForProgramme($query, ?string $programme = null)
    {
        if ($programme) {
            $query->where('programme', 'LIKE', "%{$programme}%");
        }
        return $query;
    }

    /**
     * Scope pour filtrer par nature
     */
    public function scopeForNature($query, ?string $nature = null)
    {
        if ($nature) {
            $query->where('nature', 'LIKE', "%{$nature}%");
        }
        return $query;
    }

    /**
     * Scope pour filtrer par code programme
     */
    public function scopeForCodeProgramme($query, ?string $codeProgramme = null)
    {
        if ($codeProgramme) {
            $query->where('code_programme', 'LIKE', "%{$codeProgramme}%");
        }
        return $query;
    }

    /**
     * Scope pour filtrer par nature CE
     */
    public function scopeForNatureCE($query, ?string $natureCE = null)
    {
        if ($natureCE) {
            $query->where('nature_ce', 'LIKE', "%{$natureCE}%");
        }
        return $query;
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeForStatut($query, ?string $statut = null)
    {
        if ($statut) {
            $query->where('statut', $statut);
        }
        return $query;
    }
}
