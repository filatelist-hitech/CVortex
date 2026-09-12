<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('email', 254)->unique();
            $table->string('password');
            $table->string('role', 16)->default('user');
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->string('target_email', 254)->nullable();
            $table->unsignedTinyInteger('max_uses')->default(1);
            $table->unsignedTinyInteger('uses')->default(0);
            $table->timestampTz('expires_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('event_type', 96);
            $table->string('actor_type', 16);
            $table->foreignUlid('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 96)->nullable();
            $table->ulid('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignUlid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'user'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('ACTIVE', 'DISABLED'))");
            DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_max_uses_check CHECK (max_uses = 1)');
            DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_uses_check CHECK (uses BETWEEN 0 AND max_uses)');
            DB::statement("ALTER TABLE audit_events ADD CONSTRAINT audit_events_actor_type_check CHECK (actor_type IN ('USER', 'OPERATOR', 'SYSTEM'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('users');
    }
};
