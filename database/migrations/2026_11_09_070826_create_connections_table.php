<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            // 'postgres' | 'mysql' — enforced natively on Postgres
            $table->enum('driver', ['postgres', 'mysql']);
            $table->string('host');
            $table->unsignedInteger('port');
            $table->string('database_name');
            $table->string('username');
            // Recoverable secret, AES-256-GCM via Laravel Crypt (APP_KEY-derived). Never returned via API, never logged.
            $table->text('password_ciphertext');
            $table->string('ssl_mode')->nullable();
            $table->json('options')->nullable();
            $table->timestamp('read_only_verified_at')->nullable();
            $table->string('status', 24)->default('unverified');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
