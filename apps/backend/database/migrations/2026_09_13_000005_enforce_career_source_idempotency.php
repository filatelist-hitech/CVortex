<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
WITH duplicate_sources AS (
    SELECT id, row_number() OVER (PARTITION BY owner_id, content_hash ORDER BY created_at NULLS LAST, id) AS duplicate_number
    FROM career_sources
)
UPDATE career_sources
SET content_hash = md5(career_sources.content_hash || career_sources.id)
FROM duplicate_sources
WHERE duplicate_sources.id = career_sources.id AND duplicate_sources.duplicate_number > 1;
CREATE UNIQUE INDEX IF NOT EXISTS career_sources_owner_content_hash_unique ON career_sources (owner_id, content_hash);
SQL);

            return;
        }

        Schema::table('career_sources', function (Blueprint $table): void {
            $table->unique(['owner_id', 'content_hash'], 'career_sources_owner_content_hash_unique');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS career_sources_owner_content_hash_unique');

            return;
        }

        Schema::table('career_sources', function (Blueprint $table): void {
            $table->dropUnique('career_sources_owner_content_hash_unique');
        });
    }
};
