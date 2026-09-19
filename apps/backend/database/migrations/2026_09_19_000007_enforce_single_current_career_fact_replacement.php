<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'career_facts_one_confirmed_replacement_unique';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX '.self::INDEX." ON career_facts (supersedes_fact_id) WHERE supersedes_fact_id IS NOT NULL AND status = 'CONFIRMED'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql' || DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        }
    }
};
