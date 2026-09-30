<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnostic_occurrences', function (Blueprint $table): void {
            $table->string('error_ref', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_occurrences', function (Blueprint $table): void {
            $table->dropColumn('error_ref');
        });
    }
};
