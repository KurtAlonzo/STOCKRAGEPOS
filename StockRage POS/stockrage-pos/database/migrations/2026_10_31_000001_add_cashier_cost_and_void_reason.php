<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What one piece costs you, so reports can show profit.
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 10, 2)->default(0)->after('price');
        });

        // The cost is copied at the time of sale (like price), so old profit never changes.
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0)->after('price');
        });

        // Who sold it, who voided it, and why.
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable()->after('voided_by');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('void_reason');
        });
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('cost'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('cost_price'));
    }
};
