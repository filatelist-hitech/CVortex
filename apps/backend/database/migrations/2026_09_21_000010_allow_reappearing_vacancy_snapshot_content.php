<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE vacancy_snapshots DROP CONSTRAINT IF EXISTS vacancy_snapshots_owner_id_content_hash_unique');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $hasDuplicates = DB::table('vacancy_snapshots')
                ->select('owner_id', 'content_hash')
                ->groupBy('owner_id', 'content_hash')
                ->havingRaw('COUNT(*) > 1')
                ->exists();
            if ($hasDuplicates) {
                // A valid A -> B -> A history cannot be represented by the old
                // owner-wide unique constraint; keep the compatible schema.
                return;
            }
            DB::statement('ALTER TABLE vacancy_snapshots ADD CONSTRAINT vacancy_snapshots_owner_id_content_hash_unique UNIQUE (owner_id, content_hash)');
        }
    }
};
