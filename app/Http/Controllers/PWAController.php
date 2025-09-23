<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PWAController extends Controller
{
    /**
     * Generate dynamic PWA manifest
     */
    public function manifest()
    {
        // Check if user is authenticated and redirect to member dashboard
        $startUrl = '/';
        if (auth()->check() && auth()->user()->user_type == 'customer') {
            $startUrl = '/dashboard?mobile=1';
        }

        $manifest = [
            'name' => get_tenant_option('pwa_app_name', get_tenant_option('business_name', get_option('site_title', config('app.name')))),
            'short_name' => get_tenant_option('pwa_short_name', get_tenant_option('business_name', 'IntelliCash')),
            'description' => get_tenant_option('pwa_description', 'Progressive Web App for IntelliCash - Manage your finances efficiently'),
            'start_url' => $startUrl,
            'display' => get_tenant_option('pwa_display_mode', 'standalone'),
            'background_color' => get_tenant_option('pwa_background_color', '#ffffff'),
            'theme_color' => get_tenant_option('pwa_theme_color', get_tenant_option('primary_color', '#007bff')),
            'orientation' => get_tenant_option('pwa_orientation', 'portrait-primary'),
            'scope' => '/',
            'lang' => get_option('language', 'en'),
            'categories' => ['finance', 'business', 'productivity'],
            'icons' => $this->getIcons(),
            'shortcuts' => $this->getShortcuts(),
            'screenshots' => $this->getScreenshots(),
        ];

        // Add service worker if enabled
        if (get_tenant_option('pwa_enabled', 1)) {
            $manifest['service_worker'] = '/sw.js';
        }

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json');
    }

    /**
     * Get PWA icons configuration
     */
    private function getIcons()
    {
        $basePath = '/public/uploads/media/';
        $icons = [];

        // Default icon sizes for PWA
        $iconSizes = [
            ['size' => '72x72', 'purpose' => 'any'],
            ['size' => '96x96', 'purpose' => 'any'],
            ['size' => '128x128', 'purpose' => 'any'],
            ['size' => '144x144', 'purpose' => 'any'],
            ['size' => '152x152', 'purpose' => 'any'],
            ['size' => '192x192', 'purpose' => 'any maskable'],
            ['size' => '384x384', 'purpose' => 'any'],
            ['size' => '512x512', 'purpose' => 'any maskable'],
        ];

        foreach ($iconSizes as $icon) {
            $filename = "pwa-icon-{$icon['size']}.png";
            $icons[] = [
                'src' => $basePath . $filename,
                'sizes' => $icon['size'],
                'type' => 'image/png',
                'purpose' => $icon['purpose']
            ];
        }

        return $icons;
    }

    /**
     * Get PWA shortcuts configuration
     */
    private function getShortcuts()
    {
        $shortcuts = [];

        // Only show shortcuts for authenticated members
        if (auth()->check() && auth()->user()->user_type == 'customer') {
            if (get_tenant_option('pwa_shortcut_dashboard', 1)) {
                $shortcuts[] = [
                    'name' => 'Dashboard',
                    'short_name' => 'Dashboard',
                    'description' => 'View your dashboard',
                    'url' => '/dashboard?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_transactions', 1)) {
                $shortcuts[] = [
                    'name' => 'Transactions',
                    'short_name' => 'Transactions',
                    'description' => 'View transactions',
                    'url' => '/transactions?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_loans', 1)) {
                $shortcuts[] = [
                    'name' => 'My Loans',
                    'short_name' => 'Loans',
                    'description' => 'View your loans',
                    'url' => '/loans/my_loans?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_deposit', 1)) {
                $shortcuts[] = [
                    'name' => 'Deposit',
                    'short_name' => 'Deposit',
                    'description' => 'Make a deposit',
                    'url' => '/deposit/automatic_methods?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_profile', 1)) {
                $shortcuts[] = [
                    'name' => 'Profile',
                    'short_name' => 'Profile',
                    'description' => 'View your profile',
                    'url' => '/profile/edit?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96'
                        ]
                    ]
                ];
            }
        }

        return $shortcuts;
    }

    /**
     * Get PWA screenshots configuration
     */
    private function getScreenshots()
    {
        $screenshots = [];

        // Desktop screenshot
        if (file_exists(public_path('uploads/media/pwa-screenshot-desktop.png'))) {
            $screenshots[] = [
                'src' => '/public/uploads/media/pwa-screenshot-desktop.png',
                'sizes' => '1280x720',
                'type' => 'image/png',
                'form_factor' => 'wide'
            ];
        }

        // Mobile screenshot
        if (file_exists(public_path('uploads/media/pwa-screenshot-mobile.png'))) {
            $screenshots[] = [
                'src' => '/public/uploads/media/pwa-screenshot-mobile.png',
                'sizes' => '750x1334',
                'type' => 'image/png',
                'form_factor' => 'narrow'
            ];
        }

        return $screenshots;
    }

    /**
     * Show PWA installation prompt
     */
    public function showInstallPrompt()
    {
        return view('pwa.install-prompt');
    }

    /**
     * Get PWA status for API
     */
    public function getStatus()
    {
        return response()->json([
            'enabled' => get_option('pwa_enabled', 1),
            'installable' => $this->isInstallable(),
            'offline_support' => get_option('pwa_offline_support', 1),
            'cache_strategy' => get_option('pwa_cache_strategy', 'cache-first')
        ]);
    }

    /**
     * Check if PWA is installable
     */
    private function isInstallable()
    {
        return get_option('pwa_enabled', 1) && 
               file_exists(public_path('uploads/media/pwa-icon-192x192.png')) &&
               file_exists(public_path('uploads/media/pwa-icon-512x512.png'));
    }
}
