<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->timestamp('captured_at');
            // Cheap drift-detection hash over catalogs (PLAN §5.2); staleness forces refresh
            $table->string('fingerprint');
            // Full normalized introspection output
            $table->json('raw_json');
            $table->timestamps();

            $table->unique(['connection_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_snapshots');
    }
};
