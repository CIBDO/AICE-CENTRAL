<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PushEvent extends Model
{
    use HasFactory;

    protected $table = 'push_events';

    public $timestamps = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'received_at',
        'region_code',
        'endpoint',
        'method',
        'status',
        'http_status',
        'duration_ms',
        'correlation_id',
        'mandats_count',
        'recettes_count',
        'banques_count',
        'message',
        'payload_hash',
        'remote_ip',
        'user_agent',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'received_at' => 'datetime',
        'created_at' => 'datetime',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'mandats_count' => 'integer',
        'recettes_count' => 'integer',
        'banques_count' => 'integer',
    ];
}
