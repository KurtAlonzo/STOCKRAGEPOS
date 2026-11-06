<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('unit', 10);
            $table->boolean('active')->default(true);
            $table->bigInteger('threshold_thousandths')->nullable();
            $table->bigInteger('balance_thousandths')->default(0);
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamps();
            $table->index(['team_id', 'kind', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
