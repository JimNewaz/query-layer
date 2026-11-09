<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SchemaRegistryColumn extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'connection_id',
        'snapshot_version',
        'entity_name',
        'column_name',
        'enabled',
        'description',
    ];

    protected $casts = [
        'snapshot_version' => 'integer',
        'enabled' => 'boolean',
    ];
}
