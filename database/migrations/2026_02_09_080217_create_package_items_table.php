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
    Schema::create('package_items', function (Blueprint $table) {
        $table->id();

        $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
        $table->foreignId('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();

        $table->unsignedInteger('quantity');

        $table->timestamps();

        $table->unique(['package_id', 'inventory_item_id']);
    });
}



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
