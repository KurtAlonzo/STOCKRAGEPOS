<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('actor_id');
            $table->string('idempotency_key', 100);
            $table->json('request_json');
            $table->string('request_hash', 64);
            $table->string('status', 20);
            $table->json('result_json')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'actor_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
