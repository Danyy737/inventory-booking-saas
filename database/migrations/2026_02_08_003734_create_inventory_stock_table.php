<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
          Schema::create('inventory_stock', function (Blueprint $table) {
        $table->id();

        $table->foreignId('organisation_id')
            ->constrained('organisations')
            ->cascadeOnDelete();

        $table->foreignId('inventory_item_id')
            ->constrained('inventory_items')
            ->cascadeOnDelete();

        $table->unsignedInteger('total_quantity')->default(0);

        $table->timestamps();

        // 1 stock row per item per org
        $table->unique(['organisation_id', 'inventory_item_id']);

        // Helpful for joins by tenant
        $table->index(['organisation_id', 'inventory_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stock');
    }
};
