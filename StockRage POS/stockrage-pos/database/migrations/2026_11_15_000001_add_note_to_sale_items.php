<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // e.g. "Bundle · Family Tray (25 pcs): 10 pcs" for a line that came from a bundle.
        Schema::table('sale_items', function (Blueprint $table) {
            $table->string('note')->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
