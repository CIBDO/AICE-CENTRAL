<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecetteClientPush extends Model
{
    use HasFactory;

    /**
     * Nom de la table
     */
    protected $table = 'recettes_clients_push';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dashboard_id',
        'regional_id',
        'client_no',
        'client_name',
        'gl_account',
        'date_posting',
        'montant',
        'description',
        'source_no',
        'exercice',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_posting' => 'date',
        'montant' => 'decimal:2',
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
