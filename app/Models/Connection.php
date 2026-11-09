<?php

namespace App\Models;

use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'driver',
        'host',
        'port',
        'database_name',
        'username',
        'password',
        'ssl_mode',
        'options',
        'status',
        'read_only_verified_at',
    ];

    protected $casts = [
        'port' => 'integer',
        'options' => 'array',
        'read_only_verified_at' => 'datetime',
    ];

    /**
     * The database password is stored recoverable-encrypted (AES-256-GCM via Laravel Crypt,
     * APP_KEY-derived key) because adapters need it to dial; it is never exposed outside
     * the server process (API responses and logs must not include it).
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_ciphertext'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute(): ?string
    {
        $ciphertext = $this->attributes['password_ciphertext'] ?? null;

        if ($ciphertext === null) {
            return null;
        }

        return Crypt::decryptString($ciphertext);
    }
}
