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
        // Update existing VSLA settings to set custom_meeting_days to null for non-custom frequencies
        \App\Models\VslaSetting::where('meeting_frequency', '!=', 'custom')
            ->update(['custom_meeting_days' => null]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data migration
    }
};