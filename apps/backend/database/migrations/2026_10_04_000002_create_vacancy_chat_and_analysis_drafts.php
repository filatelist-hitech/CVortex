<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['vacancy_chat_threads', 'vacancy_chat_messages', 'vacancy_analysis_drafts', 'vacancy_analysis_draft_requirements'];

    public function up(): void
    {
        Schema::create('vacancy_chat_threads', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignUlid('connection_id')->nullable()->constrained('chatgpt_connections')->restrictOnDelete();
            $table->string('provider', 64)->default('openai_chatgpt_plan');
            $table->string('model', 128)->nullable();
            $table->string('status', 32)->default('IDLE');
            $table->timestamps();
            $table->unique(['owner_id', 'vacancy_id']);
        });
        Schema::create('vacancy_chat_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('thread_id')->constrained('vacancy_chat_threads')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->restrictOnDelete();
            $table->foreignUlid('run_id')->nullable()->constrained('vacancy_llm_runs')->restrictOnDelete();
            $table->string('career_signature', 64);
            $table->string('client_request_id', 128)->nullable();
            $table->string('role', 16);
            $table->text('content');
            $table->string('status', 32);
            $table->string('error_code', 96)->nullable();
            $table->timestamps();
            $table->unique(['thread_id', 'client_request_id']);
        });
        Schema::create('vacancy_analysis_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('vacancy_id')->constrained('vacancies')->cascadeOnDelete();
            $table->foreignUlid('vacancy_snapshot_id')->constrained('vacancy_snapshots')->restrictOnDelete();
            $table->foreignUlid('message_id')->nullable()->constrained('vacancy_chat_messages')->restrictOnDelete();
            $table->foreignUlid('run_id')->nullable()->constrained('vacancy_llm_runs')->restrictOnDelete();
            $table->foreignUlid('approved_analysis_id')->nullable()->constrained('vacancy_analyses')->restrictOnDelete();
            $table->string('career_signature', 64);
            $table->string('client_request_id', 128);
            $table->string('payload_hash', 64);
            $table->string('status', 32)->default('DRAFT');
            $table->string('origin', 32)->default('AI_GENERATED');
            $table->string('source_channel', 32);
            $table->string('provider', 64);
            $table->string('model', 128)->nullable();
            $table->json('proposed_matches');
            $table->json('gaps');
            $table->json('risks');
            $table->json('questions');
            $table->json('recommendations');
            $table->timestampTz('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['owner_id', 'vacancy_id', 'client_request_id'], 'analysis_draft_request_unique');
        });
        Schema::create('vacancy_analysis_draft_requirements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('draft_id')->constrained('vacancy_analysis_drafts')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('dimension', 32);
            $table->string('importance', 32);
            $table->string('label', 255);
            $table->text('normalized_value')->nullable();
            $table->text('source_excerpt');
            $table->decimal('confidence', 5, 4);
            $table->timestamps();
            $table->unique(['draft_id', 'position']);
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::TABLES as $table) {
                DB::unprepared("CREATE UNIQUE INDEX {$table}_id_owner_unique ON {$table} (id, owner_id);
ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;
ALTER TABLE {$table} FORCE ROW LEVEL SECURITY;
CREATE POLICY {$table}_owner_isolation ON {$table}
USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));");
            }
            DB::unprepared(<<<'SQL'
CREATE UNIQUE INDEX chatgpt_connections_id_owner_unique ON chatgpt_connections (id, owner_id);
CREATE UNIQUE INDEX vacancy_llm_runs_id_owner_unique ON vacancy_llm_runs (id, owner_id);
ALTER TABLE vacancy_chat_threads ADD CONSTRAINT chat_thread_vacancy_owner_fk FOREIGN KEY (vacancy_id, owner_id) REFERENCES vacancies (id, owner_id);
ALTER TABLE vacancy_chat_threads ADD CONSTRAINT chat_thread_connection_owner_fk FOREIGN KEY (connection_id, owner_id) REFERENCES chatgpt_connections (id, owner_id);
ALTER TABLE vacancy_chat_messages ADD CONSTRAINT chat_message_thread_owner_fk FOREIGN KEY (thread_id, owner_id) REFERENCES vacancy_chat_threads (id, owner_id);
ALTER TABLE vacancy_chat_messages ADD CONSTRAINT chat_message_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id);
ALTER TABLE vacancy_chat_messages ADD CONSTRAINT chat_message_run_owner_fk FOREIGN KEY (run_id, owner_id) REFERENCES vacancy_llm_runs (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_vacancy_owner_fk FOREIGN KEY (vacancy_id, owner_id) REFERENCES vacancies (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_snapshot_owner_fk FOREIGN KEY (vacancy_snapshot_id, owner_id) REFERENCES vacancy_snapshots (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_message_owner_fk FOREIGN KEY (message_id, owner_id) REFERENCES vacancy_chat_messages (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_run_owner_fk FOREIGN KEY (run_id, owner_id) REFERENCES vacancy_llm_runs (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_analysis_owner_fk FOREIGN KEY (approved_analysis_id, owner_id) REFERENCES vacancy_analyses (id, owner_id);
ALTER TABLE vacancy_analysis_draft_requirements ADD CONSTRAINT analysis_draft_requirement_owner_fk FOREIGN KEY (draft_id, owner_id) REFERENCES vacancy_analysis_drafts (id, owner_id);
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_status_check CHECK (status IN ('DRAFT','APPROVED'));
ALTER TABLE vacancy_chat_messages ADD CONSTRAINT chat_message_role_check CHECK (role IN ('user','assistant'));
SQL);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $table) {
            Schema::dropIfExists($table);
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP INDEX IF EXISTS chatgpt_connections_id_owner_unique; DROP INDEX IF EXISTS vacancy_llm_runs_id_owner_unique;');
        }
    }
};
