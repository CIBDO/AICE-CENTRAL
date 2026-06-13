<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BanquePush extends Model
{
    use HasFactory;

    /**
     * Nom de la table
     */
    protected $table = 'banques_push';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dashboard_id',
        'regional_id',
        'numero_compte',
        'libelle',
        'date_mouvement',
        'debit',
        'credit',
        'solde',
        'reference',
        'entry_no',
        'exercice',
        'type_document',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_mouvement' => 'date',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'solde' => 'decimal:2',
        'exercice' => 'integer',
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
}
