<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->index(['team_id', 'occurred_at', 'id']));
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex(['team_id', 'occurred_at', 'id']));
    }
};
