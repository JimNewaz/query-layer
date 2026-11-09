<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schema_registry_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->unsignedInteger('snapshot_version');
            $table->string('from_entity');
            $table->string('from_column')->nullable();
            $table->string('to_entity');
            $table->string('to_column')->nullable();
            // 'inferred' relationships are hints only; 'manual' entries win over them (PLAN §5.2)
            $table->string('origin', 16);
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique([
                'connection_id',
                'snapshot_version',
                'from_entity',
                'from_column',
                'to_entity',
                'to_column',
                'origin',
            ], 'schema_registry_relationships_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schema_registry_relationships');
    }
};
