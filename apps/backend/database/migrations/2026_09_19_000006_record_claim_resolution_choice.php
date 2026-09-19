<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('claims', 'resolved_career_fact_id')) {
            Schema::table('claims', function (Blueprint $table): void {
                $table->foreignUlid('resolved_career_fact_id')->nullable()->constrained('career_facts')->nullOnDelete();
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_resolved_fact_owner_fk');
            DB::statement(<<<'SQL'
ALTER TABLE claims ADD CONSTRAINT claims_resolved_fact_owner_fk
FOREIGN KEY (resolved_career_fact_id, owner_id)
REFERENCES career_facts (id, owner_id)
ON DELETE SET NULL (resolved_career_fact_id)
SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE claims DROP CONSTRAINT IF EXISTS claims_resolved_fact_owner_fk');
        }

        // Resolution choices are history. The column and values remain on rollback.
    }
};
