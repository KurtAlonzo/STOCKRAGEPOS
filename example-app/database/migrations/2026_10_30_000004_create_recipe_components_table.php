<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $t->bigInteger('quantity_thousandths');
            $t->string('unit', 10);
            $t->unique(['catalog_item_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_components');
    }
};
