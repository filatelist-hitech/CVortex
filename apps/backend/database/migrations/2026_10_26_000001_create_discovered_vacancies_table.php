<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovered_vacancies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('portal', 32);
            $table->string('external_id', 255)->nullable();
            $table->string('title', 255);
            $table->string('company', 255);
            $table->string('location', 255)->nullable();
            $table->text('source_url');
            $table->string('salary_text', 255)->nullable();
            $table->text('snippet')->nullable();
            $table->text('description')->nullable();
            $table->smallInteger('fit_score')->default(0);
            $table->string('fit_level', 16)->default('UNKNOWN');
            $table->json('fit_reasons')->nullable();
            $table->string('status', 32)->default('INBOX');
            $table->string('security_threat_level', 32)->default('CLEAN');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'fit_score']);
            $table->unique(['owner_id', 'source_url']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE discovered_vacancies ENABLE ROW LEVEL SECURITY;
ALTER TABLE discovered_vacancies FORCE ROW LEVEL SECURITY;
CREATE POLICY discovered_vacancies_owner_isolation ON discovered_vacancies
USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));
SQL);

            $role = (string) config('database.runtime_role', 'cvortex_app');
            if (preg_match('/^[a-z_][a-z0-9_]*$/', $role) === 1) {
                DB::unprepared("GRANT SELECT, INSERT, UPDATE, DELETE ON TABLE discovered_vacancies TO \"{$role}\"");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discovered_vacancies');
    }
};
