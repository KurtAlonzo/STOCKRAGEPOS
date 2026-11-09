<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reversal_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('idempotency_key');
            $table->json('request_json');
            $table->string('request_hash', 64);
            $table->string('status', 20);
            $table->json('result_json')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'actor_id', 'idempotency_key']);
        });
        Schema::create('sale_reversals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->json('request_json');
            $table->string('request_hash', 64);
            $table->bigInteger('total_minor');
            $table->string('refund_method', 20);
            $table->string('refund_reference')->nullable();
            $table->text('reason');
            $table->boolean('refund_confirmed');
            $table->unsignedBigInteger('actor_id');
            $table->string('actor_name');
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_reversals');
        Schema::dropIfExists('reversal_attempts');
    }
};
