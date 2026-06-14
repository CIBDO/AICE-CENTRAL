<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dashboard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'region_id',
        'local_id',
        'regional_id', // ID unique côté régional pour upsert
        'total_ordonnance',
        'total_recouvrements_4121',
        'total_montant_paye',
        'solde',
        'tresorerie_reelle',
        'annee',
        'mois',
        'date_debut',
        'date_fin',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_ordonnance' => 'decimal:2',
        'total_recouvrements_4121' => 'decimal:2',
        'total_montant_paye' => 'decimal:2',
        'solde' => 'decimal:2',
        'tresorerie_reelle' => 'decimal:2',
        'annee' => 'integer',
        'mois' => 'integer',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relation avec la région
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Relation avec les mouvements
     */
    public function mouvements(): HasMany
    {
        return $this->hasMany(Mouvement::class);
    }

    /**
     * Relation avec les banques (mode Push)
     */
    public function banquesPush(): HasMany
    {
        return $this->hasMany(BanquePush::class);
    }

    /**
     * Relation avec les recettes clients (mode Push)
     */
    public function recettesClientsPush(): HasMany
    {
        return $this->hasMany(RecetteClientPush::class);
    }

    /**
     * Scope pour filtrer par région
     */
    public function scopeForRegion($query, string $regionCode)
    {
        return $query->whereHas('region', function ($q) use ($regionCode) {
            $q->where('code', $regionCode);
        });
    }

    /**
     * Scope pour les dernières données
     */
    public function scopeLatest($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
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
            $query->where(function($q) use ($dateDebut, $dateFin) {
                // Dashboard dont la période chevauche la plage demandée
                $q->where(function($q2) use ($dateDebut, $dateFin) {
                    $q2->where('date_debut', '<=', $dateFin ?? now())
                       ->where('date_fin', '>=', $dateDebut);
                })
                // OU dashboard créé dans la plage
                ->orWhereBetween('created_at', [
                    $dateDebut . ' 00:00:00',
                    ($dateFin ?? now()->format('Y-m-d')) . ' 23:59:59'
                ]);
            });
        }
        return $query;
    }
}
