<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type', 32)->default('PASTED_TEXT');
            $table->text('source_url')->nullable();
            $table->string('title', 255)->nullable();
            $table->string('company', 255)->nullable();
            $table->string('analysis_status', 32)->default('PENDING');
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'analysis_status']);
            $table->index(['owner_id', 'source_url']);
        });

        Schema::create('vacancy_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('raw_text');
            $table->text('source_url')->nullable();
            $table->string('content_hash', 64);
            $table->timestampTz('imported_at');
            $table->timestamps();
            $table->unique(['vacancy_id', 'version']);
        });

        Schema::create('vacancy_requirements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->cascadeOnDelete();
            $table->string('dimension', 32);
            $table->string('importance', 16);
            $table->string('label', 255);
            $table->text('normalized_value')->nullable();
            $table->text('source_excerpt');
            $table->decimal('confidence', 5, 4);
            $table->string('extracted_by', 128);
            $table->string('candidate_hash', 64);
            $table->timestamps();
            $table->unique(['vacancy_snapshot_id', 'candidate_hash'], 'vacancy_requirements_snapshot_candidate_unique');
            $table->index(['owner_id', 'dimension']);
        });

        Schema::create('vacancy_analyses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->cascadeOnDelete();
            $table->string('career_signature', 64);
            $table->string('recommendation', 32);
            $table->json('key_reasons');
            $table->json('material_gaps');
            $table->json('uncertainties');
            $table->string('analysis_version', 32);
            $table->timestamps();
            $table->unique(['vacancy_snapshot_id', 'career_signature']);
            $table->index(['owner_id', 'vacancy_id']);
        });

        Schema::create('vacancy_match_dimensions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_analysis_id')->constrained('vacancy_analyses')->cascadeOnDelete();
            $table->string('dimension', 32);
            $table->string('result', 32);
            $table->text('explanation');
            $table->string('origin', 32)->default('DETERMINISTIC');
            $table->json('vacancy_requirement_ids');
            $table->timestamps();
            $table->unique(['vacancy_analysis_id', 'dimension']);
        });

        Schema::create('vacancy_match_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_match_dimension_id')->constrained('vacancy_match_dimensions')->cascadeOnDelete();
            $table->foreignUlid('career_fact_id')->nullable()->constrained('career_facts')->restrictOnDelete();
            $table->foreignUlid('claim_id')->nullable()->constrained('claims')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['vacancy_match_dimension_id', 'career_fact_id'], 'vacancy_match_fact_unique');
            $table->unique(['vacancy_match_dimension_id', 'claim_id'], 'vacancy_match_claim_unique');
        });

        Schema::create('vacancy_llm_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->cascadeOnDelete();
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
            $table->string('validation_result', 64)->nullable();
            $table->string('error_category', 64)->nullable();
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'vacancy_snapshot_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->hardenPostgresOwnership();
        }
    }

    private function hardenPostgresOwnership(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE vacancies ADD CONSTRAINT vacancies_source_type_check CHECK (source_type IN ('PASTED_TEXT'));
ALTER TABLE vacancies ADD CONSTRAINT vacancies_analysis_status_check CHECK (analysis_status IN ('PENDING', 'RUNNING', 'COMPLETED', 'FAILED'));
ALTER TABLE vacancy_requirements ADD CONSTRAINT vacancy_requirements_dimension_check CHECK (dimension IN ('TECHNICAL', 'EXPERIENCE', 'DOMAIN', 'LANGUAGE', 'LOCATION', 'WORK_FORMAT', 'SALARY'));
ALTER TABLE vacancy_requirements ADD CONSTRAINT vacancy_requirements_importance_check CHECK (importance IN ('MANDATORY', 'PREFERRED', 'UNCERTAIN'));
ALTER TABLE vacancy_analyses ADD CONSTRAINT vacancy_analyses_recommendation_check CHECK (recommendation IN ('STRONGLY_APPLY', 'APPLY', 'MAYBE', 'LOW_PRIORITY', 'SKIP'));
ALTER TABLE vacancy_match_dimensions ADD CONSTRAINT vacancy_match_dimensions_dimension_check CHECK (dimension IN ('TECHNICAL', 'EXPERIENCE', 'DOMAIN', 'LANGUAGE', 'LOCATION', 'WORK_FORMAT', 'SALARY'));
ALTER TABLE vacancy_match_dimensions ADD CONSTRAINT vacancy_match_dimensions_result_check CHECK (result IN ('MATCH', 'ADJACENT', 'GAP', 'UNKNOWN', 'NOT_APPLICABLE', 'BLOCKER'));
ALTER TABLE vacancy_match_evidence ADD CONSTRAINT vacancy_match_evidence_one_source_check CHECK ((career_fact_id IS NOT NULL)::int + (claim_id IS NOT NULL)::int = 1);

CREATE UNIQUE INDEX vacancies_id_owner_unique ON vacancies (id, owner_id);
CREATE UNIQUE INDEX vacancy_snapshots_id_owner_unique ON vacancy_snapshots (id, owner_id);
CREATE UNIQUE INDEX vacancy_analyses_id_owner_unique ON vacancy_analyses (id, owner_id);
CREATE UNIQUE INDEX vacancy_match_dimensions_id_owner_unique ON vacancy_match_dimensions (id, owner_id);

ALTER TABLE vacancy_snapshots ADD CONSTRAINT vacancy_snapshots_vacancy_owner_fk FOREIGN KEY (vacancy_id, owner_id) REFERENCES vacancies (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_requirements ADD CONSTRAINT vacancy_requirements_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_analyses ADD CONSTRAINT vacancy_analyses_vacancy_owner_fk FOREIGN KEY (vacancy_id, owner_id) REFERENCES vacancies (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_analyses ADD CONSTRAINT vacancy_analyses_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_match_dimensions ADD CONSTRAINT vacancy_match_dimensions_analysis_owner_fk FOREIGN KEY (vacancy_analysis_id, owner_id) REFERENCES vacancy_analyses (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_match_evidence ADD CONSTRAINT vacancy_match_evidence_dimension_owner_fk FOREIGN KEY (vacancy_match_dimension_id, owner_id) REFERENCES vacancy_match_dimensions (id, owner_id) ON DELETE CASCADE;
ALTER TABLE vacancy_match_evidence ADD CONSTRAINT vacancy_match_evidence_fact_owner_fk FOREIGN KEY (career_fact_id, owner_id) REFERENCES career_facts (id, owner_id) ON DELETE RESTRICT;
ALTER TABLE vacancy_match_evidence ADD CONSTRAINT vacancy_match_evidence_claim_owner_fk FOREIGN KEY (claim_id, owner_id) REFERENCES claims (id, owner_id) ON DELETE RESTRICT;
ALTER TABLE vacancy_llm_runs ADD CONSTRAINT vacancy_llm_runs_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id) ON DELETE CASCADE;
SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_llm_runs');
        Schema::dropIfExists('vacancy_match_evidence');
        Schema::dropIfExists('vacancy_match_dimensions');
        Schema::dropIfExists('vacancy_analyses');
        Schema::dropIfExists('vacancy_requirements');
        Schema::dropIfExists('vacancy_snapshots');
        Schema::dropIfExists('vacancies');
    }
};
