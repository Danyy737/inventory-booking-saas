<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organisation_id')
                ->constrained('organisations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->enum('pricing_type', ['fixed', 'per_unit'])->default('fixed');
            $table->unsignedInteger('price_cents')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['organisation_id', 'is_active']);
            $table->index(['organisation_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};