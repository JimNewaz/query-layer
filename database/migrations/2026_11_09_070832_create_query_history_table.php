<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->nullable()->constrained('api_keys')->nullOnDelete();
            $table->foreignUuid('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('clarification_answers')->nullable();
            $table->json('llm_response')->nullable();
            $table->string('request_id');
            $table->string('status', 32);
            $table->string('error_code', 48)->nullable();
            $table->json('error_detail')->nullable();
            $table->unsignedInteger('llm_input_tokens')->nullable();
            $table->unsignedInteger('llm_output_tokens')->nullable();
            $table->unsignedInteger('llm_duration_ms')->nullable();
            $table->unsignedInteger('db_duration_ms')->nullable();
            $table->unsignedInteger('returned_row_count')->default(0);
            // Result rows are intentionally NEVER persisted here (PLAN §11)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_history');
    }
};
