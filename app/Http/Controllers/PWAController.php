<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PWAController extends Controller
{
    /**
     * Generate dynamic PWA manifest for native app experience
     */
    public function manifest()
    {
        // Enhanced member-focused start URL
        $startUrl = '/dashboard?mobile=1';
        if (!auth()->check() || auth()->user()->user_type !== 'customer') {
            $startUrl = '/login?mobile=1';
        }

        $manifest = [
            'name' => get_tenant_option('pwa_app_name', get_tenant_option('business_name', 'IntelliCash Member App')),
            'short_name' => get_tenant_option('pwa_short_name', get_tenant_option('business_name', 'IntelliCash')),
            'description' => get_tenant_option('pwa_description', 'Your personal financial management app - Manage loans, transactions, and savings with ease'),
            'start_url' => $startUrl,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => get_tenant_option('pwa_theme_color', get_tenant_option('primary_color', '#007bff')),
            'background_color' => get_tenant_option('pwa_background_color', '#ffffff'),
            'scope' => '/',
            'lang' => get_option('language', 'en'),
            'categories' => ['finance', 'productivity', 'business'],
            'icons' => $this->getIcons(),
            'shortcuts' => $this->getShortcuts(),
            'screenshots' => $this->getScreenshots(),
            'prefer_related_applications' => false,
            'edge_side_panel' => [
                'preferred_width' => 400
            ],
            'launch_handler' => [
                'client_mode' => 'focus-existing'
            ],
            'handle_links' => 'preferred',
            'protocol_handlers' => [
                [
                    'protocol' => 'web+intellicash',
                    'url' => '/dashboard?mobile=1&action=%s'
                ]
            ]
        ];

        // Add service worker if enabled
        if (get_tenant_option('pwa_enabled', 1)) {
            $manifest['service_worker'] = '/sw.js';
        }

        return response()->json($manifest)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
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
     * Get PWA shortcuts configuration for native app experience
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
                    'description' => 'View your account overview',
                    'url' => '/dashboard?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96',
                            'type' => 'image/png'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_transactions', 1)) {
                $shortcuts[] = [
                    'name' => 'Transactions',
                    'short_name' => 'Transactions',
                    'description' => 'View transaction history',
                    'url' => '/transactions/index?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96',
                            'type' => 'image/png'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_loans', 1)) {
                $shortcuts[] = [
                    'name' => 'My Loans',
                    'short_name' => 'Loans',
                    'description' => 'Check loan status and payments',
                    'url' => '/loans/my_loans?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96',
                            'type' => 'image/png'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_deposit', 1)) {
                $shortcuts[] = [
                    'name' => 'Make Payment',
                    'short_name' => 'Pay',
                    'description' => 'Make deposits and payments',
                    'url' => '/deposit/automatic_methods?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96',
                            'type' => 'image/png'
                        ]
                    ]
                ];
            }

            if (get_tenant_option('pwa_shortcut_profile', 1)) {
                $shortcuts[] = [
                    'name' => 'Profile',
                    'short_name' => 'Profile',
                    'description' => 'Manage your profile settings',
                    'url' => '/profile/edit?mobile=1',
                    'icons' => [
                        [
                            'src' => '/public/uploads/media/pwa-icon-96x96.png',
                            'sizes' => '96x96',
                            'type' => 'image/png'
                        ]
                    ]
                ];
            }
        }

        return $shortcuts;
    }

    /**
     * Get PWA screenshots for app stores
     */
    private function getScreenshots()
    {
        $screenshots = [];
        
        // Add screenshots if they exist
        $screenshotSizes = [
            ['width' => 1280, 'height' => 720, 'form_factor' => 'wide'],
            ['width' => 750, 'height' => 1334, 'form_factor' => 'narrow']
        ];
        
        foreach ($screenshotSizes as $size) {
            $filename = "pwa-screenshot-{$size['width']}x{$size['height']}.png";
            if (file_exists(public_path("uploads/media/{$filename}"))) {
                $screenshots[] = [
                    'src' => "/public/uploads/media/{$filename}",
                    'sizes' => "{$size['width']}x{$size['height']}",
                    'type' => 'image/png',
                    'form_factor' => $size['form_factor']
                ];
            }
        }
        
        return $screenshots;
    }

    /**
     * Show PWA install prompt
     */
    public function showInstallPrompt()
    {
        return view('pwa.install-prompt');
    }

    /**
     * Get PWA status for enhanced native app features
     */
    public function getStatus()
    {
        return response()->json([
            'enabled' => get_tenant_option('pwa_enabled', 1),
            'installed' => request()->header('X-Mobile-App') == '1',
            'version' => '4.0',
            'features' => [
                'offline_support' => true,
                'push_notifications' => true,
                'background_sync' => true,
                'native_gestures' => true,
                'haptic_feedback' => true,
                'pull_to_refresh' => true,
                'swipe_navigation' => true
            ],
            'installable' => $this->isInstallable(),
            'offline_support' => get_tenant_option('pwa_offline_support', 1),
            'cache_strategy' => get_tenant_option('pwa_cache_strategy', 'cache-first')
        ]);
    }

    /**
     * Check if PWA is installable
     */
    private function isInstallable()
    {
        return get_tenant_option('pwa_enabled', 1) && 
               file_exists(public_path('uploads/media/pwa-icon-192x192.png')) &&
               file_exists(public_path('uploads/media/pwa-icon-512x512.png'));
    }
}