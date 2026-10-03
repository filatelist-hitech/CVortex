<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_sources', function (Blueprint $table): void {
            $table->string('active_run_token', 36)->nullable();
        });

        Schema::table('vacancies', function (Blueprint $table): void {
            $table->string('active_run_token', 36)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vacancies', function (Blueprint $table): void {
            $table->dropColumn('active_run_token');
        });

        Schema::table('career_sources', function (Blueprint $table): void {
            $table->dropColumn('active_run_token');
        });
    }
};
