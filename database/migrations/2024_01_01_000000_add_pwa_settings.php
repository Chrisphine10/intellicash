<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

class AddPwaSettings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ensure settings table exists before adding PWA settings
        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('name', 191);
                $table->text('value');
                $table->timestamps();
            });
        }

        // Add default PWA settings
        $pwaSettings = [
            'pwa_enabled' => '1',
            'pwa_app_name' => 'IntelliCash',
            'pwa_short_name' => 'IntelliCash',
            'pwa_description' => 'Progressive Web App for IntelliCash - Manage your finances efficiently',
            'pwa_theme_color' => '#007bff',
            'pwa_background_color' => '#ffffff',
            'pwa_display_mode' => 'standalone',
            'pwa_orientation' => 'portrait-primary',
            'pwa_icon_192' => 'pwa-icon-192x192.png',
            'pwa_icon_512' => 'pwa-icon-512x512.png',
            'pwa_shortcut_dashboard' => '1',
            'pwa_shortcut_transactions' => '1',
            'pwa_shortcut_profile' => '1',
            'pwa_offline_support' => '1',
            'pwa_cache_strategy' => 'cache-first',
        ];

        foreach ($pwaSettings as $key => $value) {
            Setting::updateOrInsert(
                ['name' => $key],
                [
                    'value' => $value,
                    'updated_at' => now()
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove PWA settings
        $pwaSettings = [
            'pwa_enabled',
            'pwa_app_name',
            'pwa_short_name',
            'pwa_description',
            'pwa_theme_color',
            'pwa_background_color',
            'pwa_display_mode',
            'pwa_orientation',
            'pwa_icon_192',
            'pwa_icon_512',
            'pwa_shortcut_dashboard',
            'pwa_shortcut_transactions',
            'pwa_shortcut_profile',
            'pwa_offline_support',
            'pwa_cache_strategy',
        ];

        Setting::whereIn('name', $pwaSettings)->delete();
    }
}
