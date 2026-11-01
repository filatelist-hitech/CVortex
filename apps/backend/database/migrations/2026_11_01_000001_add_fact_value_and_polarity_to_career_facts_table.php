<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_facts', function (Blueprint $table): void {
            if (! Schema::hasColumn('career_facts', 'fact_value')) {
                $table->string('fact_value', 255)->nullable()->after('fact_type');
            }
            if (! Schema::hasColumn('career_facts', 'polarity')) {
                $table->string('polarity', 32)->default('positive')->after('fact_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('career_facts', function (Blueprint $table): void {
            if (Schema::hasColumn('career_facts', 'polarity')) {
                $table->dropColumn('polarity');
            }
            if (Schema::hasColumn('career_facts', 'fact_value')) {
                $table->dropColumn('fact_value');
            }
        });
    }
};
