<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chatgpt_connections', fn (Blueprint $table) => $table->unsignedBigInteger('oauth_generation')->default(0));
    }

    public function down(): void
    {
        Schema::table('chatgpt_connections', fn (Blueprint $table) => $table->dropColumn('oauth_generation'));
    }
};
