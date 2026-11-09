<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_registry_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->unsignedInteger('snapshot_version');
            $table->string('entity_name');
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();

            $table->unique(['connection_id', 'snapshot_version', 'entity_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_registry_entries');
    }
};
