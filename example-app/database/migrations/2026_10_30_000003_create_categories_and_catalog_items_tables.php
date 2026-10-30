<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->string('name', 100);
            $t->string('name_key', 100);
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('revision')->default(1);
            $t->timestamps();
            $t->unique(['team_id', 'name_key']);
        });
        Schema::create('catalog_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('team_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('sku', 64);
            $t->string('kind', 20);
            $t->string('stock_behavior', 20)->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('stock_item_id')->nullable()->unique()->constrained('inventory_items')->restrictOnDelete();
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('price_minor');
            $t->unsignedBigInteger('revision')->default(1);
            $t->unsignedBigInteger('recipe_revision')->default(1);
            $t->timestamps();
            $t->unique(['team_id', 'sku']);
            $t->index(['team_id', 'kind', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('categories');
    }
};
