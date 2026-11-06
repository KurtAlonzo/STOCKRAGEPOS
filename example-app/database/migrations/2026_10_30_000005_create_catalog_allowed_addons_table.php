<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_allowed_addons', function (Blueprint $t) {
            $t->foreignId('catalog_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('addon_id')->constrained('catalog_items')->restrictOnDelete();
            $t->primary(['catalog_item_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_allowed_addons');
    }
};
