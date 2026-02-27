<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('booking_id')
                ->constrained('bookings')
                ->cascadeOnDelete();

            $table->foreignId('addon_id')
                ->constrained('addons')
                ->cascadeOnDelete();

            $table->unsignedInteger('quantity')->default(1);

            $table->unsignedInteger('unit_price_cents');
            $table->unsignedInteger('line_total_cents');

            $table->timestamps();

            $table->unique(['booking_id', 'addon_id']);

            $table->index('booking_id');
            $table->index('addon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_addons');
    }
};