<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->string('join_code', 12)->unique()->nullable()->after('name');
            $table->boolean('join_enabled')->default(true)->after('join_code');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['join_code']);
            $table->dropColumn(['join_code', 'join_enabled']);
        });
    }
};
