<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix existing meeting_time data that might be stored as datetime
        $settings = DB::table('vsla_settings')->get();
        
        foreach ($settings as $setting) {
            if ($setting->meeting_time) {
                try {
                    // Try to parse the time and extract just the time portion
                    $time = \Carbon\Carbon::parse($setting->meeting_time)->format('H:i:s');
                    
                    DB::table('vsla_settings')
                        ->where('id', $setting->id)
                        ->update(['meeting_time' => $time]);
                        
                } catch (\Exception $e) {
                    // If parsing fails, set a default time
                    DB::table('vsla_settings')
                        ->where('id', $setting->id)
                        ->update(['meeting_time' => '10:00:00']);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse this data fix
    }
};
