<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['career_sources', 'vacancies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->timestampTz('next_attempt_at')->nullable();
                $table->timestampTz('dispatch_recovery_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['career_sources', 'vacancies'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['next_attempt_at', 'dispatch_recovery_at']);
            });
        }
    }
};
