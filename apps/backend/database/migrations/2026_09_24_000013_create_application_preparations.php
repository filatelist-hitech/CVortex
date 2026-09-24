<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OWNER_TABLES = [
        'application_preparations',
        'application_draft_items',
        'application_claim_usages',
        'application_approval_events',
        'application_llm_runs',
        'application_draft_revisions',
    ];

    public function up(): void
    {
        Schema::create('application_preparations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->cascadeOnDelete();
            $table->foreignUlid('vacancy_analysis_id')->constrained('vacancy_analyses')->cascadeOnDelete();
            $table->string('career_signature', 64);
            $table->string('status', 24)->default('DRAFT');
            $table->timestamps();
            $table->unique(['owner_id', 'vacancy_snapshot_id', 'career_signature']);
            $table->index(['owner_id', 'updated_at']);
        });

        Schema::create('application_draft_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('preparation_id')->constrained('application_preparations')->cascadeOnDelete();
            $table->foreignUlid('vacancy_requirement_id')->nullable()->constrained('vacancy_requirements')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('variant', 16)->nullable();
            $table->string('section', 120)->nullable();
            $table->text('before_text')->nullable();
            $table->text('content');
            $table->text('reason')->nullable();
            $table->text('risk')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->string('validation_result', 32)->default('NOT_VALIDATED');
            $table->unsignedInteger('revision_number')->default(1);
            $table->string('validated_content_hash', 64)->nullable();
            $table->timestampTz('validated_at')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'preparation_id', 'kind']);
        });

        Schema::create('application_claim_usages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('draft_item_id')->constrained('application_draft_items')->cascadeOnDelete();
            $table->foreignUlid('claim_id')->constrained('claims')->restrictOnDelete();
            $table->text('assertion_text');
            $table->timestamps();
            $table->unique(['draft_item_id', 'claim_id', 'assertion_text'], 'application_claim_usage_unique');
            $table->index(['owner_id', 'claim_id']);
        });

        Schema::create('application_approval_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('draft_item_id')->constrained('application_draft_items')->cascadeOnDelete();
            $table->foreignUlid('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 16);
            $table->unsignedInteger('revision_number');
            $table->string('content_hash', 64);
            $table->string('validation_result', 32);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['owner_id', 'draft_item_id', 'created_at']);
        });

        Schema::create('application_llm_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('preparation_id')->constrained('application_preparations')->cascadeOnDelete();
            $table->string('workflow', 64);
            $table->string('skill_id', 96);
            $table->string('skill_version', 32);
            $table->string('prompt_version', 32);
            $table->string('model_policy', 64);
            $table->string('provider', 64)->nullable();
            $table->string('model', 128)->nullable();
            $table->string('provider_request_id', 128)->nullable();
            $table->string('status', 24);
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->string('validation_result', 32)->nullable();
            $table->string('error_category', 64)->nullable();
            $table->unsignedBigInteger('estimated_cost_micros')->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'preparation_id', 'created_at']);
        });

        Schema::create('application_draft_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('draft_item_id')->constrained('application_draft_items')->cascadeOnDelete();
            $table->foreignUlid('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('action', 16);
            $table->text('content');
            $table->string('content_hash', 64);
            $table->string('validation_result', 32);
            $table->json('claim_usages');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['draft_item_id', 'owner_id', 'revision_number'], 'application_draft_revision_number_unique');
            $table->index(['owner_id', 'draft_item_id', 'revision_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX application_preparations_id_owner_unique ON application_preparations (id, owner_id);
CREATE UNIQUE INDEX application_draft_items_id_owner_unique ON application_draft_items (id, owner_id);
CREATE UNIQUE INDEX vacancy_requirements_id_owner_unique ON vacancy_requirements (id, owner_id);

ALTER TABLE application_preparations
  ADD CONSTRAINT application_preparations_status_check CHECK (status IN ('DRAFT', 'APPROVED')),
  ADD CONSTRAINT application_preparations_vacancy_owner_fk FOREIGN KEY (vacancy_id, owner_id) REFERENCES vacancies (id, owner_id) ON DELETE CASCADE,
  ADD CONSTRAINT application_preparations_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id) ON DELETE CASCADE,
  ADD CONSTRAINT application_preparations_analysis_owner_fk FOREIGN KEY (vacancy_analysis_id, owner_id) REFERENCES vacancy_analyses (id, owner_id) ON DELETE CASCADE;

ALTER TABLE application_draft_items
  ADD CONSTRAINT application_draft_items_kind_check CHECK (kind IN ('RESUME_RECOMMENDATION', 'COVER_DRAFT')),
  ADD CONSTRAINT application_draft_items_variant_check CHECK (variant IS NULL OR variant IN ('SHORT', 'STANDARD')),
  ADD CONSTRAINT application_draft_items_status_check CHECK (status IN ('DRAFT', 'ACCEPTED', 'REJECTED', 'BLOCKED', 'APPROVED')),
  ADD CONSTRAINT application_draft_items_validation_check CHECK (validation_result IN ('NOT_VALIDATED', 'PASS', 'BLOCK', 'USER_RESOLUTION_REQUIRED', 'FAILED')),
  ADD CONSTRAINT application_draft_items_preparation_owner_fk FOREIGN KEY (preparation_id, owner_id) REFERENCES application_preparations (id, owner_id) ON DELETE CASCADE,
  ADD CONSTRAINT application_draft_items_requirement_owner_fk FOREIGN KEY (vacancy_requirement_id, owner_id) REFERENCES vacancy_requirements (id, owner_id) ON DELETE SET NULL (vacancy_requirement_id);

ALTER TABLE application_claim_usages
  ADD CONSTRAINT application_claim_usages_draft_owner_fk FOREIGN KEY (draft_item_id, owner_id) REFERENCES application_draft_items (id, owner_id) ON DELETE CASCADE,
  ADD CONSTRAINT application_claim_usages_claim_owner_fk FOREIGN KEY (claim_id, owner_id) REFERENCES claims (id, owner_id) ON DELETE RESTRICT;

ALTER TABLE application_approval_events
  ADD CONSTRAINT application_approval_events_action_check CHECK (action IN ('ACCEPTED', 'EDITED', 'REJECTED', 'APPROVED')),
  ADD CONSTRAINT application_approval_events_result_check CHECK (validation_result IN ('PASS', 'BLOCK', 'USER_RESOLUTION_REQUIRED')),
  ADD CONSTRAINT application_approval_events_actor_owner_check CHECK (actor_user_id = owner_id),
  ADD CONSTRAINT application_approval_events_item_owner_fk FOREIGN KEY (draft_item_id, owner_id) REFERENCES application_draft_items (id, owner_id) ON DELETE CASCADE;

ALTER TABLE application_draft_revisions
  ADD CONSTRAINT application_draft_revisions_action_check CHECK (action IN ('GENERATED', 'EDITED')),
  ADD CONSTRAINT application_draft_revisions_validation_check CHECK (validation_result IN ('NOT_VALIDATED', 'PASS', 'BLOCK', 'USER_RESOLUTION_REQUIRED', 'FAILED')),
  ADD CONSTRAINT application_draft_revisions_actor_owner_check CHECK (actor_user_id = owner_id),
  ADD CONSTRAINT application_draft_revisions_item_owner_fk FOREIGN KEY (draft_item_id, owner_id) REFERENCES application_draft_items (id, owner_id) ON DELETE CASCADE;

ALTER TABLE application_approval_events
  ADD CONSTRAINT application_approval_events_revision_fk FOREIGN KEY (draft_item_id, owner_id, revision_number)
    REFERENCES application_draft_revisions (draft_item_id, owner_id, revision_number) ON DELETE CASCADE;

ALTER TABLE application_llm_runs
  ADD CONSTRAINT application_llm_runs_status_check CHECK (status IN ('RUNNING', 'COMPLETED', 'FAILED')),
  ADD CONSTRAINT application_llm_runs_preparation_owner_fk FOREIGN KEY (preparation_id, owner_id) REFERENCES application_preparations (id, owner_id) ON DELETE CASCADE;
SQL);

            foreach (self::OWNER_TABLES as $table) {
                DB::unprepared(<<<SQL
ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
CREATE POLICY {$table}_owner_isolation ON {$table}
    USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
    WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));
SQL);
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::OWNER_TABLES as $table) {
                DB::unprepared("DROP POLICY IF EXISTS {$table}_owner_isolation ON {$table}; ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
            }
        }

        Schema::dropIfExists('application_approval_events');
        Schema::dropIfExists('application_draft_revisions');
        Schema::dropIfExists('application_claim_usages');
        Schema::dropIfExists('application_draft_items');
        Schema::dropIfExists('application_llm_runs');
        Schema::dropIfExists('application_preparations');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS vacancy_requirements_id_owner_unique');
            DB::statement('DROP INDEX IF EXISTS application_draft_items_id_owner_unique');
            DB::statement('DROP INDEX IF EXISTS application_preparations_id_owner_unique');
        }
    }
};
