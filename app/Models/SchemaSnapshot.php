<?php

namespace App\Models;

use Database\Factories\SchemaSnapshotFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchemaSnapshot extends Model
{
    /** @use HasFactory<SchemaSnapshotFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'connection_id',
        'version',
        'captured_at',
        'fingerprint',
        'raw_json',
    ];

    protected $casts = [
        'version' => 'integer',
        'captured_at' => 'datetime',
        'raw_json' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
