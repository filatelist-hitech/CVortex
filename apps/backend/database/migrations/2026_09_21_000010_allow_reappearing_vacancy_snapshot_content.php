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
        // Migration 000008 did not create an owner-wide content-hash unique
        // constraint. Its preceding schema therefore needs no restoration.
    }
};
