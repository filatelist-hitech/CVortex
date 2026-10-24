<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 128)->unique();
            $table->json('value')->nullable();
            $table->text('encrypted_value')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            $role = config('database.connections.pgsql.runtime_role', env('DB_RUNTIME_ROLE', 'cvortex_app'));
            if ($role && preg_match('/^[a-z_][a-z0-9_]*$/', (string) $role)) {
                DB::unprepared("GRANT SELECT, INSERT, UPDATE, DELETE ON system_settings TO {$role}");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
