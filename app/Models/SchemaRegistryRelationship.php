<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchemaRegistryRelationship extends Model
{
    use HasUuids;

    public const ORIGIN_INFERRED = 'inferred';

    public const ORIGIN_MANUAL = 'manual';

    protected $fillable = [
        'connection_id',
        'snapshot_version',
        'from_entity',
        'from_column',
        'to_entity',
        'to_column',
        'origin',
        'enabled',
        'description',
    ];

    protected $casts = [
        'snapshot_version' => 'integer',
        'enabled' => 'boolean',
    ];
}
