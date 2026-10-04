<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatgpt_connections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('client_id', 255);
            $table->string('subject', 255)->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->text('id_token')->nullable();
            $table->json('scopes');
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('earliest_refresh_at')->nullable();
            $table->string('status', 48)->default('NOT_CONNECTED');
            $table->timestamps();
            $table->unique(['owner_id', 'client_id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE chatgpt_connections ENABLE ROW LEVEL SECURITY;
ALTER TABLE chatgpt_connections FORCE ROW LEVEL SECURITY;
CREATE POLICY chatgpt_connections_owner_isolation ON chatgpt_connections
USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));
ALTER TABLE chatgpt_connections ADD CONSTRAINT chatgpt_connections_status_check
CHECK (status IN ('NOT_CONNECTED', 'CONNECTED', 'PLAN_PERMISSION_MISSING', 'REAUTHENTICATION_REQUIRED'));
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('chatgpt_connections');
    }
};
