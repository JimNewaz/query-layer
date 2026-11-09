<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchemaRegistryEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'connection_id',
        'snapshot_version',
        'entity_name',
        'enabled',
        'description',
        'metadata',
    ];

    protected $casts = [
        'snapshot_version' => 'integer',
        'enabled' => 'boolean',
        'metadata' => 'array',
    ];
}
