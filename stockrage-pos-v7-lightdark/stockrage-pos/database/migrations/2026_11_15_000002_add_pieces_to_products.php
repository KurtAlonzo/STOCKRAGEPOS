<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many pieces one "order" of a single-size flavor has (Classic Takoyaki = 6 pcs, say).
        // It lets that flavor be mixed into bundles. Sized products ("Mix - Family Tray (25 pcs)") don't need it.
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('pieces')->nullable()->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pieces');
        });
    }
};
