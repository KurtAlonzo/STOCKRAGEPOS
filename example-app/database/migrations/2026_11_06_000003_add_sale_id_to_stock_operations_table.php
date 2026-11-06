<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_operations', fn (Blueprint $table) => $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('stock_operations', fn (Blueprint $table) => $table->dropConstrainedForeignId('sale_id'));
    }
};
