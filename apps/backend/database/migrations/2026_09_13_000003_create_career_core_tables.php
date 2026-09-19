<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_profiles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('career_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('career_profile_id')->constrained('career_profiles')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->longText('source_text');
            $table->string('content_hash', 64);
            $table->string('extraction_status', 32)->default('PENDING');
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'content_hash']);
        });

        Schema::create('llm_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('career_source_id')->constrained('career_sources')->cascadeOnDelete();
            $table->string('workflow', 64);
            $table->string('skill_id', 96);
            $table->string('skill_version', 32);
            $table->string('prompt_version', 32);
            $table->string('model_policy', 64);
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('provider_request_id', 128)->nullable();
            $table->string('status', 32);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->string('validation_error', 96)->nullable();
            $table->timestamps();
        });

        Schema::create('career_facts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('career_profile_id')->constrained('career_profiles')->cascadeOnDelete();
            $table->foreignUlid('career_source_id')->nullable()->constrained('career_sources')->restrictOnDelete();
            $table->ulid('supersedes_fact_id')->nullable();
            $table->string('provenance_type', 32);
            $table->string('fact_type', 64);
            $table->text('assertion_original');
            $table->text('assertion_approved')->nullable();
            $table->text('source_excerpt');
            $table->string('extracted_by', 96);
            $table->decimal('extraction_confidence', 5, 4)->nullable();
            $table->string('status', 16);
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });
        Schema::table('career_facts', function (Blueprint $table): void {
            $table->foreign('supersedes_fact_id')->references('id')->on('career_facts')->nullOnDelete();
        });

        Schema::create('claims', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->text('statement');
            $table->string('truth_status', 32)->default('BLOCK');
            $table->timestamps();
        });

        Schema::create('claim_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->foreignUlid('career_fact_id')->constrained('career_facts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['claim_id', 'career_fact_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE career_sources ADD CONSTRAINT career_sources_kind_check CHECK (kind IN ('PASTED_TEXT'))");
            DB::statement("ALTER TABLE career_sources ADD CONSTRAINT career_sources_status_check CHECK (extraction_status IN ('PENDING', 'RUNNING', 'COMPLETED', 'FAILED'))");
            DB::statement("ALTER TABLE career_facts ADD CONSTRAINT career_facts_status_check CHECK (status IN ('PENDING', 'CONFIRMED', 'REJECTED', 'DEPRECATED'))");
            DB::statement("ALTER TABLE career_facts ADD CONSTRAINT career_facts_provenance_check CHECK (provenance_type IN ('paste_extraction', 'user_manual'))");
            DB::statement("ALTER TABLE claims ADD CONSTRAINT claims_truth_status_check CHECK (truth_status IN ('PASS', 'BLOCK', 'USER_RESOLUTION_REQUIRED'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_evidence');
        Schema::dropIfExists('claims');
        Schema::dropIfExists('career_facts');
        Schema::dropIfExists('llm_runs');
        Schema::dropIfExists('career_sources');
        Schema::dropIfExists('career_profiles');
    }
};
