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
        Schema::table('vsla_settings', function (Blueprint $table) {
            // Change meeting_time from datetime to time
            $table->time('meeting_time')->default('10:00:00')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_settings', function (Blueprint $table) {
            // Revert back to datetime
            $table->datetime('meeting_time')->default('2025-01-01 10:00:00')->change();
        });
    }
};
