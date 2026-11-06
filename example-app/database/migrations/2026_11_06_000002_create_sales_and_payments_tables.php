<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->string('reference')->unique();
            $table->string('status', 20);
            $table->timestamp('occurred_at');
            $table->string('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('total_minor');
            $table->json('lines_json');
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->string('method', 20);
            $table->string('reference')->nullable();
            $table->json('input_json');
            $table->bigInteger('received_minor');
            $table->bigInteger('change_minor');
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['method', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('sales');
    }
};
