<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ai_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('selected_provider', 32)->nullable();
            $table->text('gemini_api_key')->nullable();
            $table->string('gemini_model', 128)->nullable();
            $table->text('openai_api_key')->nullable();
            $table->string('openai_model', 128)->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE user_ai_settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE user_ai_settings FORCE ROW LEVEL SECURITY;
CREATE POLICY user_ai_settings_owner_isolation ON user_ai_settings
USING (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''))
WITH CHECK (owner_id::text = NULLIF(current_setting('cvortex.owner_id', true), ''));
ALTER TABLE user_ai_settings ADD CONSTRAINT user_ai_settings_selected_provider_check
CHECK (selected_provider IS NULL OR selected_provider IN ('openai', 'gemini'));
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_settings');
    }
};
