<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnostic_incidents', function (Blueprint $table): void {
            $table->boolean('retryable')->default(false);
            $table->string('impact', 255)->default('The affected operation did not complete.');
            $table->string('recovery_action', 255)->default('Inspect the sanitized incident details and dependency health.');
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_incidents', function (Blueprint $table): void {
            $table->dropColumn(['retryable', 'impact', 'recovery_action']);
        });
    }
};
