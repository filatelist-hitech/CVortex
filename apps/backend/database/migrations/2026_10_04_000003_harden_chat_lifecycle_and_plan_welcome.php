<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatgpt_connections', fn (Blueprint $table) => $table->timestampTz('welcome_acknowledged_at')->nullable());
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE vacancy_chat_threads ADD CONSTRAINT chat_thread_status_check CHECK (status IN ('IDLE','STREAMING','COMPLETED','INTERRUPTED'));
ALTER TABLE vacancy_chat_messages ADD CONSTRAINT chat_message_status_check CHECK (status IN ('STREAMING','COMPLETED','INTERRUPTED'));
ALTER TABLE vacancy_analysis_drafts ADD CONSTRAINT analysis_draft_origin_check CHECK (origin = 'AI_GENERATED' AND source_channel IN ('MCP','EMBEDDED_CHAT'));
ALTER TABLE vacancy_analysis_draft_requirements ADD CONSTRAINT draft_requirement_dimension_check CHECK (dimension IN ('TECHNICAL','EXPERIENCE','DOMAIN','LANGUAGE','LOCATION','WORK_FORMAT','SALARY'));
ALTER TABLE vacancy_analysis_draft_requirements ADD CONSTRAINT draft_requirement_importance_check CHECK (importance IN ('MANDATORY','PREFERRED','UNCERTAIN'));
ALTER TABLE vacancy_analysis_draft_requirements ADD CONSTRAINT draft_requirement_confidence_check CHECK (confidence >= 0 AND confidence <= 1);
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE vacancy_chat_threads DROP CONSTRAINT chat_thread_status_check;
ALTER TABLE vacancy_chat_messages DROP CONSTRAINT chat_message_status_check;
ALTER TABLE vacancy_analysis_drafts DROP CONSTRAINT analysis_draft_origin_check;
ALTER TABLE vacancy_analysis_draft_requirements DROP CONSTRAINT draft_requirement_dimension_check;
ALTER TABLE vacancy_analysis_draft_requirements DROP CONSTRAINT draft_requirement_importance_check;
ALTER TABLE vacancy_analysis_draft_requirements DROP CONSTRAINT draft_requirement_confidence_check;
SQL);
        }
        Schema::table('chatgpt_connections', fn (Blueprint $table) => $table->dropColumn('welcome_acknowledged_at'));
    }
};
