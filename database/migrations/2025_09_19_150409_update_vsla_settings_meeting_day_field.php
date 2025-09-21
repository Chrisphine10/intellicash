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
            // Add new meeting_day_of_week field
            $table->enum('meeting_day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->nullable()->after('meeting_frequency');
            
            // Remove old custom_meeting_days field
            $table->dropColumn('custom_meeting_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_settings', function (Blueprint $table) {
            // Add back custom_meeting_days field
            $table->integer('custom_meeting_days')->nullable()->after('meeting_frequency');
            
            // Remove meeting_day_of_week field
            $table->dropColumn('meeting_day_of_week');
        });
    }
};