<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('addon_id')
                ->constrained('addons')
                ->cascadeOnDelete();

            $table->foreignId('inventory_item_id')
                ->constrained('inventory_items')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity_per_unit');

            $table->timestamps();

            $table->unique(['addon_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_items');
    }
};