<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Fragment shown in UI (e.g. "ql_9f3..."); the raw key is shown exactly once at creation
            $table->string('key_prefix');
            // sha-256 of the raw key
            $table->string('key_hash')->unique();
            // Fine-grained grants: connection/entity/column scopes and row limits
            $table->json('scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
