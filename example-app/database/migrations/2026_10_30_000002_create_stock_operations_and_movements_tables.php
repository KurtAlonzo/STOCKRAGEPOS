<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_operation_id')->unique()->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('unit', 10);
            $table->string('item_name');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->text('reason');
            $table->bigInteger('before_thousandths');
            $table->bigInteger('delta_thousandths');
            $table->bigInteger('after_thousandths');
            $table->timestamps();
            $table->index(['team_id', 'inventory_item_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_operations');
    }
};
