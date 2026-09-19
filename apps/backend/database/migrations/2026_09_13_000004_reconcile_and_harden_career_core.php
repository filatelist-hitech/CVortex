<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->reconcileSources();
        $this->reconcileFacts();
        $this->reconcileClaims();
        $this->reconcileRuns();

        if (DB::getDriverName() === 'pgsql') {
            $this->hardenPostgresOwnership();
        }
    }

    private function reconcileSources(): void
    {
        Schema::table('career_sources', function (Blueprint $table): void {
            if (! Schema::hasColumn('career_sources', 'source_text')) {
                $table->longText('source_text')->nullable();
            }
            if (! Schema::hasColumn('career_sources', 'extraction_status')) {
                $table->string('extraction_status', 32)->nullable()->default('PENDING');
            }
            if (! Schema::hasColumn('career_sources', 'error_code')) {
                $table->string('error_code', 64)->nullable();
            }
        });

        if (Schema::hasColumn('career_sources', 'content')) {
            DB::statement('UPDATE career_sources SET source_text = content WHERE source_text IS NULL');
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE career_sources DROP CONSTRAINT IF EXISTS career_sources_kind_check');
        }
        DB::table('career_sources')->whereIn('kind', ['pasted_text', 'manual_entry'])->update(['kind' => 'PASTED_TEXT']);
        DB::table('career_sources')->whereNull('source_text')->update(['source_text' => '']);
        DB::table('career_sources')->whereNull('extraction_status')->update(['extraction_status' => 'COMPLETED']);
    }

    private function reconcileFacts(): void
    {
        Schema::table('career_facts', function (Blueprint $table): void {
            if (! Schema::hasColumn('career_facts', 'supersedes_fact_id')) {
                $table->ulid('supersedes_fact_id')->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'provenance_type')) {
                $table->string('provenance_type', 32)->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'fact_type')) {
                $table->string('fact_type', 64)->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'assertion_original')) {
                $table->text('assertion_original')->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'assertion_approved')) {
                $table->text('assertion_approved')->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'extracted_by')) {
                $table->string('extracted_by', 96)->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'extraction_confidence')) {
                $table->decimal('extraction_confidence', 5, 4)->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'candidate_hash')) {
                $table->string('candidate_hash', 64)->nullable();
            }
            if (! Schema::hasColumn('career_facts', 'status')) {
                $table->string('status', 16)->nullable();
            }
        });

        if (Schema::hasColumn('career_facts', 'kind')) {
            DB::statement('UPDATE career_facts SET fact_type = kind WHERE fact_type IS NULL');
        }
        if (Schema::hasColumn('career_facts', 'value')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("UPDATE career_facts SET assertion_original = CASE WHEN json_typeof(value) = 'string' THEN value #>> '{}' ELSE value::text END WHERE assertion_original IS NULL");
            } else {
                foreach (DB::table('career_facts')->whereNull('assertion_original')->get(['id', 'value']) as $row) {
                    $decoded = json_decode((string) $row->value, true);
                    DB::table('career_facts')->where('id', $row->id)->update([
                        'assertion_original' => is_string($decoded) ? $decoded : json_encode($decoded, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        }
        if (Schema::hasColumn('career_facts', 'state')) {
            DB::statement('UPDATE career_facts SET status = state WHERE status IS NULL');
        }
        if (Schema::hasColumn('career_facts', 'supersedes_id')) {
            DB::statement('UPDATE career_facts SET supersedes_fact_id = supersedes_id WHERE supersedes_fact_id IS NULL');
        }
        if (Schema::hasColumn('career_facts', 'confidence')) {
            DB::statement('UPDATE career_facts SET extraction_confidence = confidence WHERE extraction_confidence IS NULL');
        }
        if (Schema::hasColumn('career_facts', 'provenance')) {
            DB::statement("UPDATE career_facts SET provenance_type = CASE WHEN provenance = 'user_manual' THEN 'user_manual' ELSE 'paste_extraction' END WHERE provenance_type IS NULL");
        }
        DB::statement("UPDATE career_facts SET fact_type = 'experience' WHERE fact_type IS NULL");
        DB::statement("UPDATE career_facts SET assertion_original = COALESCE(source_excerpt, '') WHERE assertion_original IS NULL");
        DB::statement("UPDATE career_facts SET source_excerpt = assertion_original WHERE source_excerpt IS NULL OR source_excerpt = ''");
        DB::statement("UPDATE career_facts SET status = 'PENDING' WHERE status IS NULL");
        DB::statement("UPDATE career_facts SET provenance_type = CASE WHEN career_source_id IS NULL THEN 'user_manual' ELSE 'paste_extraction' END WHERE provenance_type IS NULL");
        DB::statement("UPDATE career_facts SET extracted_by = CASE WHEN provenance_type = 'user_manual' THEN 'user_manual' ELSE 'legacy_migration' END WHERE extracted_by IS NULL");
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table('career_facts', function (Blueprint $table): void {
                $table->unique(['career_source_id', 'candidate_hash'], 'career_facts_source_candidate_unique');
            });
        }
    }

    private function reconcileClaims(): void
    {
        Schema::table('claims', function (Blueprint $table): void {
            if (! Schema::hasColumn('claims', 'truth_status')) {
                $table->string('truth_status', 32)->nullable()->default('BLOCK');
            }
            if (! Schema::hasColumn('claims', 'resolution_reason')) {
                $table->string('resolution_reason', 64)->nullable();
            }
            if (! Schema::hasColumn('claims', 'resolution_requested_at')) {
                $table->timestampTz('resolution_requested_at')->nullable();
            }
            if (! Schema::hasColumn('claims', 'resolved_by')) {
                $table->foreignUlid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('claims', 'resolved_at')) {
                $table->timestampTz('resolved_at')->nullable();
            }
        });
        DB::table('claims')->whereNull('truth_status')->update(['truth_status' => 'BLOCK']);
    }

    private function reconcileRuns(): void
    {
        Schema::table('llm_runs', function (Blueprint $table): void {
            if (! Schema::hasColumn('llm_runs', 'career_source_id')) {
                $table->foreignUlid('career_source_id')->nullable()->constrained('career_sources')->nullOnDelete();
            }
            if (! Schema::hasColumn('llm_runs', 'workflow')) {
                $table->string('workflow', 64)->nullable();
            }
            if (! Schema::hasColumn('llm_runs', 'skill_id')) {
                $table->string('skill_id', 96)->nullable();
            }
            foreach ([
                'provider_request_id' => 128,
                'validation_result' => 64,
                'error_category' => 64,
            ] as $column => $length) {
                if (! Schema::hasColumn('llm_runs', $column)) {
                    $table->string($column, $length)->nullable();
                }
            }
            if (! Schema::hasColumn('llm_runs', 'input_tokens')) {
                $table->unsignedInteger('input_tokens')->nullable();
            }
            if (! Schema::hasColumn('llm_runs', 'output_tokens')) {
                $table->unsignedInteger('output_tokens')->nullable();
            }
            if (! Schema::hasColumn('llm_runs', 'latency_ms')) {
                $table->unsignedInteger('latency_ms')->nullable();
            }
            if (! Schema::hasColumn('llm_runs', 'retry_count')) {
                $table->unsignedSmallInteger('retry_count')->default(0);
            }
            if (! Schema::hasColumn('llm_runs', 'estimated_cost_micros')) {
                $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            }
        });

        if (Schema::hasColumn('llm_runs', 'skill')) {
            DB::statement('UPDATE llm_runs SET skill_id = skill WHERE skill_id IS NULL');
        }
        DB::statement("UPDATE llm_runs SET workflow = 'career_text_extraction' WHERE workflow IS NULL");
        DB::statement("UPDATE llm_runs SET skill_id = 'career.fact-extraction' WHERE skill_id IS NULL");
    }

    private function hardenPostgresOwnership(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE career_sources ALTER COLUMN source_text SET NOT NULL;
ALTER TABLE career_sources ALTER COLUMN extraction_status SET NOT NULL;
ALTER TABLE career_sources DROP CONSTRAINT IF EXISTS career_sources_kind_check;
UPDATE career_sources SET kind = 'PASTED_TEXT' WHERE kind = 'pasted_text';
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_sources' AND column_name = 'content') THEN
        EXECUTE 'ALTER TABLE career_sources ALTER COLUMN content DROP NOT NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_sources' AND column_name = 'provenance') THEN
        EXECUTE 'ALTER TABLE career_sources ALTER COLUMN provenance DROP NOT NULL';
    END IF;
END $$;
ALTER TABLE career_sources ADD CONSTRAINT career_sources_kind_check CHECK (kind IN ('PASTED_TEXT'));
ALTER TABLE career_sources DROP CONSTRAINT IF EXISTS career_sources_status_check;
ALTER TABLE career_sources ADD CONSTRAINT career_sources_status_check CHECK (extraction_status IN ('PENDING', 'RUNNING', 'COMPLETED', 'FAILED'));

ALTER TABLE career_facts ALTER COLUMN provenance_type SET NOT NULL;
ALTER TABLE career_facts ALTER COLUMN fact_type SET NOT NULL;
ALTER TABLE career_facts ALTER COLUMN assertion_original SET NOT NULL;
ALTER TABLE career_facts ALTER COLUMN source_excerpt SET NOT NULL;
ALTER TABLE career_facts ALTER COLUMN extracted_by SET NOT NULL;
ALTER TABLE career_facts ALTER COLUMN status SET NOT NULL;
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_facts' AND column_name = 'kind') THEN
        EXECUTE 'ALTER TABLE career_facts ALTER COLUMN kind DROP NOT NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_facts' AND column_name = 'value') THEN
        EXECUTE 'ALTER TABLE career_facts ALTER COLUMN value DROP NOT NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_facts' AND column_name = 'state') THEN
        EXECUTE 'ALTER TABLE career_facts ALTER COLUMN state DROP NOT NULL';
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'career_facts' AND column_name = 'provenance') THEN
        EXECUTE 'ALTER TABLE career_facts ALTER COLUMN provenance DROP NOT NULL';
    END IF;
END $$;
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_provenance_check;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_provenance_check CHECK (provenance_type IN ('paste_extraction', 'user_manual'));
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_status_check;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_status_check CHECK (status IN ('PENDING', 'CONFIRMED', 'REJECTED', 'DEPRECATED'));
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_fact_type_check;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_fact_type_check CHECK (fact_type IN ('skill','experience','achievement','education','language','certification','employment_period','seniority','leadership','responsibility','technology_depth'));

ALTER TABLE claims ALTER COLUMN truth_status SET NOT NULL;
ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_truth_status_check;
ALTER TABLE claims ADD CONSTRAINT claims_truth_status_check CHECK (truth_status IN ('PASS', 'BLOCK', 'USER_RESOLUTION_REQUIRED'));
DO $$ BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'llm_runs' AND column_name = 'skill') THEN
        EXECUTE 'ALTER TABLE llm_runs ALTER COLUMN skill DROP NOT NULL';
    END IF;
END $$;
ALTER TABLE llm_runs ALTER COLUMN provider DROP NOT NULL;
ALTER TABLE llm_runs ALTER COLUMN model DROP NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS career_profiles_id_owner_unique ON career_profiles (id, owner_id);
CREATE UNIQUE INDEX IF NOT EXISTS career_sources_id_owner_unique ON career_sources (id, owner_id);
CREATE UNIQUE INDEX IF NOT EXISTS career_facts_id_owner_unique ON career_facts (id, owner_id);
CREATE UNIQUE INDEX IF NOT EXISTS claims_id_owner_unique ON claims (id, owner_id);
CREATE UNIQUE INDEX IF NOT EXISTS career_facts_source_candidate_unique ON career_facts (career_source_id, candidate_hash) WHERE candidate_hash IS NOT NULL;

ALTER TABLE career_sources DROP CONSTRAINT IF EXISTS career_sources_profile_owner_fk;
ALTER TABLE career_sources ADD CONSTRAINT career_sources_profile_owner_fk FOREIGN KEY (career_profile_id, owner_id) REFERENCES career_profiles (id, owner_id) ON DELETE CASCADE;
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_profile_owner_fk;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_profile_owner_fk FOREIGN KEY (career_profile_id, owner_id) REFERENCES career_profiles (id, owner_id) ON DELETE CASCADE;
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_source_owner_fk;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_source_owner_fk FOREIGN KEY (career_source_id, owner_id) REFERENCES career_sources (id, owner_id) ON DELETE RESTRICT;
ALTER TABLE career_facts DROP CONSTRAINT IF EXISTS career_facts_supersedes_owner_fk;
ALTER TABLE career_facts ADD CONSTRAINT career_facts_supersedes_owner_fk FOREIGN KEY (supersedes_fact_id, owner_id) REFERENCES career_facts (id, owner_id) ON DELETE SET NULL (supersedes_fact_id);
ALTER TABLE llm_runs DROP CONSTRAINT IF EXISTS llm_runs_source_owner_fk;
ALTER TABLE llm_runs ADD CONSTRAINT llm_runs_source_owner_fk FOREIGN KEY (career_source_id, owner_id) REFERENCES career_sources (id, owner_id) ON DELETE SET NULL (career_source_id);
ALTER TABLE claim_evidence DROP CONSTRAINT IF EXISTS claim_evidence_claim_owner_fk;
ALTER TABLE claim_evidence ADD CONSTRAINT claim_evidence_claim_owner_fk FOREIGN KEY (claim_id, owner_id) REFERENCES claims (id, owner_id) ON DELETE CASCADE;
ALTER TABLE claim_evidence DROP CONSTRAINT IF EXISTS claim_evidence_fact_owner_fk;
ALTER TABLE claim_evidence ADD CONSTRAINT claim_evidence_fact_owner_fk FOREIGN KEY (career_fact_id, owner_id) REFERENCES career_facts (id, owner_id) ON DELETE RESTRICT;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ([
                'career_sources_profile_owner_fk' => 'career_sources',
                'career_facts_profile_owner_fk' => 'career_facts',
                'career_facts_source_owner_fk' => 'career_facts',
                'career_facts_supersedes_owner_fk' => 'career_facts',
                'llm_runs_source_owner_fk' => 'llm_runs',
                'claim_evidence_claim_owner_fk' => 'claim_evidence',
                'claim_evidence_fact_owner_fk' => 'claim_evidence',
            ] as $constraint => $table) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }

        // Reconciliation is deliberately non-destructive: rolling back this migration removes
        // hardening constraints only and preserves both legacy and current Career data columns.
    }
};
