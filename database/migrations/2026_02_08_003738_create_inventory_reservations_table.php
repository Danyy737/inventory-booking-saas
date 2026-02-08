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
      Schema::create('inventory_reservations', function (Blueprint $table) {
        $table->id();

        $table->foreignId('organisation_id')
            ->constrained('organisations')
            ->cascadeOnDelete();

        $table->foreignId('inventory_item_id')
            ->constrained('inventory_items')
            ->cascadeOnDelete();

        // We'll add bookings later; keep it nullable now
        $table->unsignedBigInteger('booking_id')->nullable();

        $table->unsignedInteger('reserved_quantity');
        $table->dateTime('start_at');
        $table->dateTime('end_at');

        $table->string('status')->default('active'); // active | cancelled (for now)

        $table->timestamps();

        // For overlap queries per tenant + item
     $table->index(
    ['organisation_id', 'inventory_item_id', 'start_at', 'end_at'],
    'inv_res_org_item_window_idx'
);

$table->index(['booking_id'], 'inv_res_booking_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
    }
};
