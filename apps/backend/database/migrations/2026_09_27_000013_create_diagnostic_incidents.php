<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnostic_incidents', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('fingerprint', 64)->unique();
            $table->string('status', 16)->default('OPEN');
            $table->string('severity', 16);
            $table->string('error_code', 96);
            $table->string('service', 32);
            $table->string('component', 96);
            $table->string('environment', 32);
            $table->string('message', 255);
            $table->string('exception_class', 255)->nullable();
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->timestamps();
            $table->index(['status', 'last_seen_at']);
            $table->index(['error_code', 'last_seen_at']);
        });

        Schema::create('diagnostic_occurrences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('incident_id')->constrained('diagnostic_incidents')->cascadeOnDelete();
            $table->string('request_id', 128)->nullable()->index();
            $table->string('job_id', 128)->nullable()->index();
            $table->ulid('llm_run_id')->nullable()->index();
            $table->ulid('application_id')->nullable()->index();
            $table->foreignUlid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route', 128)->nullable();
            $table->string('operation', 128)->nullable();
            $table->string('provider', 64)->nullable();
            $table->unsignedSmallInteger('attempt')->nullable();
            $table->text('safe_stack')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['incident_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_occurrences');
        Schema::dropIfExists('diagnostic_incidents');
    }
};
