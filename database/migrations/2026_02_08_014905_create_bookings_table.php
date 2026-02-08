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
           Schema::create('bookings', function (Blueprint $table) {
        $table->id();

        $table->foreignId('organisation_id')
            ->constrained('organisations')
            ->cascadeOnDelete();

        $table->string('reference')->nullable();
        $table->dateTime('start_at');
        $table->dateTime('end_at');
        $table->string('status')->default('confirmed');
        $table->text('notes')->nullable();

        $table->timestamps();

        $table->index(['organisation_id', 'start_at', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
