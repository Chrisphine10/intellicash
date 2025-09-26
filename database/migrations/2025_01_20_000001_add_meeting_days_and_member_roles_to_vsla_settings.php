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
        if (Schema::hasTable('vsla_settings')) {
            Schema::table('vsla_settings', function (Blueprint $table) {
                // Add meeting_days field to store multiple days of the week
                if (!Schema::hasColumn('vsla_settings', 'meeting_days')) {
                    $table->json('meeting_days')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_settings', function (Blueprint $table) {
            $table->dropColumn('meeting_days');
        });
    }
};
