<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>{{ !isset($page_title) ? get_tenant_option('business_name', get_option('site_title', config('app.name'))) : $page_title }}</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @if(get_option('pwa_enabled', 1))
        <!-- PWA Meta Tags -->
        <meta name="theme-color" content="{{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}">
        
        <!-- PWA Manifest -->
        <link rel="manifest" href="{{ route('pwa.manifest') }}">
        
        <!-- PWA Icons -->
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('public/uploads/media/pwa-icon-180x180.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('public/uploads/media/pwa-icon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('public/uploads/media/pwa-icon-16x16.png') }}">
        @endif

        <!-- App favicon -->
        <link rel="shortcut icon" href="{{ get_favicon() }}">
        
        <!-- App Css -->
        <link rel="stylesheet" href="{{ asset('public/backend/plugins/bootstrap/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('public/backend/assets/css/fontawesome.css') }}">
        <link rel="stylesheet" href="{{ asset('public/backend/assets/css/themify-icons.css') }}">
        <link rel="stylesheet" href="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.css') }}" />
        
        <!-- Mobile App Styles -->
        <style>
            :root {
                --primary-color: {{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }};
                --primary-light: {{ get_option('pwa_theme_color', '#007bff') }}20;
                --primary-dark: {{ get_option('pwa_theme_color', '#007bff') }}dd;
                --text-primary: #1a1a1a;
                --text-secondary: #6b7280;
                --text-muted: #9ca3af;
                --border-color: #e5e7eb;
                --bg-light: #f9fafb;
                --bg-white: #ffffff;
                --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
                --shadow-lg: 0 4px 6px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
                --shadow-xl: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.05);
                --border-radius: 16px;
                --border-radius-sm: 12px;
                --border-radius-lg: 20px;
                --safe-area-inset-bottom: env(safe-area-inset-bottom);
                --safe-area-inset-top: env(safe-area-inset-top);
                --haptic-feedback: #007bff;
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: var(--bg-light);
                color: var(--text-primary);
                line-height: 1.5;
                margin: 0;
                padding: 0;
                overflow-x: hidden;
                padding-bottom: calc(80px + var(--safe-area-inset-bottom));
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: none;
                user-select: none;
                -webkit-user-select: none;
                -webkit-tap-highlight-color: transparent;
                -webkit-touch-callout: none;
            }

            /* Mobile App Header */
            .mobile-header {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
                color: white;
                padding: 16px 20px;
                padding-top: calc(16px + var(--safe-area-inset-top));
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                box-shadow: var(--shadow-lg);
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 64px;
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .mobile-header .header-left {
                display: flex;
                align-items: center;
                flex: 1;
            }

            .mobile-header .app-logo {
                width: 40px;
                height: 40px;
                border-radius: var(--border-radius-sm);
                margin-right: 12px;
                background: rgba(255,255,255,0.15);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                font-weight: 700;
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .mobile-header .app-logo img {
                width: 100%;
                height: 100%;
                border-radius: var(--border-radius-sm);
                object-fit: cover;
            }

            .mobile-header .app-title {
                font-size: 20px;
                font-weight: 700;
                margin: 0;
                letter-spacing: -0.5px;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }

            .mobile-header .header-right {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .mobile-header .notification-btn,
            .mobile-header .menu-btn {
                background: rgba(255,255,255,0.15);
                border: none;
                color: white;
                width: 44px;
                height: 44px;
                border-radius: var(--border-radius-sm);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                cursor: pointer;
                transition: all 0.2s ease;
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.2);
                position: relative;
                overflow: hidden;
            }

            .mobile-header .notification-btn:hover,
            .mobile-header .menu-btn:hover {
                background: rgba(255,255,255,0.25);
                transform: scale(1.05);
            }

            .mobile-header .notification-btn:active,
            .mobile-header .menu-btn:active {
                transform: scale(0.95);
                background: rgba(255,255,255,0.3);
            }

            /* Main Content */
            .mobile-main-content {
                margin-top: calc(64px + var(--safe-area-inset-top));
                padding: 20px;
                min-height: calc(100vh - 144px - var(--safe-area-inset-top) - var(--safe-area-inset-bottom));
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: smooth;
            }

            /* Pull to Refresh */
            .pull-to-refresh {
                position: absolute;
                top: -60px;
                left: 50%;
                transform: translateX(-50%);
                transition: all 0.3s ease;
                opacity: 0;
                pointer-events: none;
            }

            .pull-to-refresh.active {
                opacity: 1;
                top: 20px;
            }

            .pull-to-refresh .refresh-icon {
                width: 24px;
                height: 24px;
                border: 2px solid var(--primary-color);
                border-top: 2px solid transparent;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Bottom Navigation */
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(255,255,255,0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-top: 1px solid var(--border-color);
                padding: 8px 0;
                padding-bottom: calc(8px + var(--safe-area-inset-bottom));
                z-index: 1000;
                display: flex;
                justify-content: space-around;
                box-shadow: 0 -4px 6px rgba(0,0,0,0.1), 0 -2px 4px rgba(0,0,0,0.06);
            }

            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 8px 12px;
                text-decoration: none;
                color: var(--text-muted);
                transition: all 0.2s ease;
                border-radius: var(--border-radius-sm);
                min-width: 60px;
                position: relative;
                overflow: hidden;
            }

            .nav-item.active {
                color: var(--primary-color);
                background: var(--primary-light);
                transform: translateY(-2px);
            }

            .nav-item .nav-icon {
                font-size: 22px;
                margin-bottom: 4px;
                transition: all 0.2s ease;
            }

            .nav-item .nav-label {
                font-size: 11px;
                font-weight: 600;
                text-align: center;
                line-height: 1.2;
                letter-spacing: 0.3px;
            }

            .nav-item.active .nav-icon {
                transform: scale(1.1);
            }

            .nav-item:active {
                transform: scale(0.95);
            }

            /* Cards */
            .mobile-card {
                background: var(--bg-white);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                margin-bottom: 20px;
                overflow: hidden;
                transition: all 0.2s ease;
                border: 1px solid rgba(0,0,0,0.05);
            }

            .mobile-card:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .mobile-card .card-header {
                padding: 24px;
                border-bottom: 1px solid var(--border-color);
                background: var(--bg-white);
            }

            .mobile-card .card-body {
                padding: 24px;
            }

            .mobile-card .card-title {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 8px 0;
                color: var(--text-primary);
                letter-spacing: -0.3px;
            }

            .mobile-card .card-subtitle {
                font-size: 15px;
                color: var(--text-secondary);
                margin: 0;
                line-height: 1.4;
            }

            /* Buttons */
            .mobile-btn {
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: var(--border-radius-sm);
                padding: 16px 24px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                margin-bottom: 12px;
                position: relative;
                overflow: hidden;
                box-shadow: var(--shadow);
                letter-spacing: 0.3px;
            }

            .mobile-btn:hover {
                transform: translateY(-1px);
                box-shadow: var(--shadow-lg);
                color: white;
                text-decoration: none;
            }

            .mobile-btn:active {
                transform: translateY(0);
                box-shadow: var(--shadow);
            }

            .mobile-btn-secondary {
                background: var(--bg-white);
                color: var(--text-primary);
                border: 1px solid var(--border-color);
                box-shadow: none;
            }

            .mobile-btn-secondary:hover {
                background: var(--bg-light);
                color: var(--text-primary);
                box-shadow: var(--shadow);
            }

            /* List Items */
            .mobile-list {
                list-style: none;
                padding: 0;
                margin: 0;
            }

            .mobile-list-item {
                display: flex;
                align-items: center;
                padding: 20px 24px;
                border-bottom: 1px solid var(--border-color);
                text-decoration: none;
                color: var(--text-primary);
                transition: all 0.2s ease;
                background: var(--bg-white);
                position: relative;
                overflow: hidden;
            }

            .mobile-list-item:last-child {
                border-bottom: none;
            }

            .mobile-list-item:hover {
                background: var(--bg-light);
                color: var(--text-primary);
                text-decoration: none;
                transform: translateX(4px);
            }

            .mobile-list-item:active {
                transform: scale(0.98);
                background: var(--primary-light);
            }

            .mobile-list-item .item-icon {
                width: 48px;
                height: 48px;
                border-radius: var(--border-radius-sm);
                background: var(--primary-light);
                color: var(--primary-color);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 16px;
                font-size: 20px;
                box-shadow: var(--shadow);
            }

            .mobile-list-item .item-content {
                flex: 1;
            }

            .mobile-list-item .item-title {
                font-size: 17px;
                font-weight: 600;
                margin: 0 0 4px 0;
                letter-spacing: -0.2px;
            }

            .mobile-list-item .item-subtitle {
                font-size: 14px;
                color: var(--text-secondary);
                margin: 0;
                line-height: 1.3;
            }

            .mobile-list-item .item-arrow {
                color: var(--text-muted);
                font-size: 16px;
                transition: all 0.2s ease;
            }

            .mobile-list-item:hover .item-arrow {
                transform: translateX(4px);
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 16px;
                margin-bottom: 24px;
            }

            .stat-card {
                background: var(--bg-white);
                border-radius: var(--border-radius);
                padding: 24px;
                text-align: center;
                box-shadow: var(--shadow);
                transition: all 0.2s ease;
                border: 1px solid rgba(0,0,0,0.05);
                position: relative;
                overflow: hidden;
            }

            .stat-card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-xl);
            }

            .stat-card .stat-icon {
                width: 56px;
                height: 56px;
                border-radius: var(--border-radius-sm);
                background: var(--primary-light);
                color: var(--primary-color);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 16px;
                font-size: 28px;
                box-shadow: var(--shadow);
            }

            .stat-card .stat-value {
                font-size: 28px;
                font-weight: 800;
                color: var(--text-primary);
                margin: 0 0 8px 0;
                letter-spacing: -0.5px;
            }

            .stat-card .stat-label {
                font-size: 13px;
                color: var(--text-secondary);
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 0.8px;
                font-weight: 600;
            }

            /* Native App Animations */
            @keyframes slideInUp {
                from { 
                    opacity: 0; 
                    transform: translateY(30px); 
                }
                to { 
                    opacity: 1; 
                    transform: translateY(0); 
                }
            }

            @keyframes slideInLeft {
                from { 
                    opacity: 0; 
                    transform: translateX(-30px); 
                }
                to { 
                    opacity: 1; 
                    transform: translateX(0); 
                }
            }

            @keyframes fadeInScale {
                from { 
                    opacity: 0; 
                    transform: scale(0.9); 
                }
                to { 
                    opacity: 1; 
                    transform: scale(1); 
                }
            }

            .fade-in-up {
                animation: slideInUp 0.4s ease-out;
            }

            .fade-in-left {
                animation: slideInLeft 0.4s ease-out;
            }

            .fade-in-scale {
                animation: fadeInScale 0.3s ease-out;
            }

            /* Ripple Effect */
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255,255,255,0.6);
                transform: scale(0);
                animation: ripple-animation 0.6s linear;
                pointer-events: none;
            }

            @keyframes ripple-animation {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }

            /* Responsive */
            @media (max-width: 480px) {
                .mobile-main-content {
                    padding: 15px;
                }
                
                .mobile-card .card-header,
                .mobile-card .card-body {
                    padding: 15px;
                }
                
                .stats-grid {
                    gap: 10px;
                }
                
                .stat-card {
                    padding: 15px;
                }
            }

            /* Loading States */
            .loading {
                opacity: 0.6;
                pointer-events: none;
            }

            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .fade-in {
                animation: fadeIn 0.3s ease-out;
            }

            /* Hide scrollbar but keep functionality */
            .mobile-main-content::-webkit-scrollbar {
                display: none;
            }
            
            .mobile-main-content {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
        </style>

        @yield('styles')
    </head>
    <body>
        <!-- Mobile Header -->
        <header class="mobile-header">
            <div class="header-left">
                <div class="app-logo">
                    @if(file_exists(public_path('uploads/media/pwa-icon-192x192.png')))
                        <img src="{{ asset('public/uploads/media/pwa-icon-192x192.png') }}" alt="App Logo">
                    @else
                        {{ strtoupper(substr(get_option('pwa_short_name', 'IC'), 0, 2)) }}
                    @endif
                </div>
                <h1 class="app-title">{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}</h1>
            </div>
            <div class="header-right">
                <button class="notification-btn" onclick="showNotifications()">
                    <i class="fas fa-bell"></i>
                    @if(request_count('messages') > 0)
                        <span class="badge badge-danger" style="position: absolute; top: -5px; right: -5px; font-size: 10px;">{{ request_count('messages') }}</span>
                    @endif
                </button>
                <button class="menu-btn" onclick="showMenu()">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mobile-main-content">
            @yield('content')
        </main>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <a href="{{ route('dashboard.index') }}" class="nav-item {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
                <i class="fas fa-home nav-icon"></i>
                <span class="nav-label">{{ _lang('Home') }}</span>
            </a>
            <a href="{{ route('loans.my_loans') }}" class="nav-item {{ request()->routeIs('loans.*') ? 'active' : '' }}">
                <i class="fas fa-hand-holding-usd nav-icon"></i>
                <span class="nav-label">{{ _lang('Loans') }}</span>
            </a>
            <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
                <i class="fas fa-exchange-alt nav-icon"></i>
                <span class="nav-label">{{ _lang('Transactions') }}</span>
            </a>
            <a href="{{ route('deposit.automatic_methods') }}" class="nav-item {{ request()->routeIs('deposit.*') || request()->routeIs('withdraw.*') ? 'active' : '' }}">
                <i class="fas fa-credit-card nav-icon"></i>
                <span class="nav-label">{{ _lang('Payments') }}</span>
            </a>
            <a href="{{ route('profile.edit') }}" class="nav-item {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                <i class="fas fa-user nav-icon"></i>
                <span class="nav-label">{{ _lang('Profile') }}</span>
            </a>
        </nav>

        <!-- Scripts -->
        <script src="{{ asset('public/backend/plugins/jquery/jquery.min.js') }}"></script>
        <script src="{{ asset('public/backend/plugins/bootstrap/js/bootstrap.min.js') }}"></script>
        <script src="{{ asset('public/backend/plugins/jquery-toast-plugin/jquery.toast.min.js') }}"></script>
        
        <!-- PWA Service Worker -->
        @if(get_option('pwa_enabled', 1))
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('/sw.js')
                        .then(function(registration) {
                            console.log('ServiceWorker registration successful');
                        })
                        .catch(function(err) {
                            console.log('ServiceWorker registration failed');
                        });
                });
            }
        </script>
        @endif

        <script>
            // Enhanced Mobile App JavaScript with Native Behaviors
            class MobileApp {
                constructor() {
                    this.isRefreshing = false;
                    this.startY = 0;
                    this.currentY = 0;
                    this.lastTouchEnd = 0;
                    this.init();
                }

                init() {
                    this.setupAnimations();
                    this.setupNavigation();
                    this.setupPullToRefresh();
                    this.setupHapticFeedback();
                    this.setupNativeGestures();
                    this.setupOfflineDetection();
                    this.setupPushNotifications();
                }

                setupAnimations() {
                    // Add staggered animations to cards
                    $('.mobile-card').each(function(index) {
                        $(this).css('animation-delay', (index * 0.1) + 's');
                        $(this).addClass('fade-in-up');
                    });

                    // Add animations to stats cards
                    $('.stat-card').each(function(index) {
                        $(this).css('animation-delay', (index * 0.15) + 's');
                        $(this).addClass('fade-in-scale');
                    });

                    // Add animations to list items
                    $('.mobile-list-item').each(function(index) {
                        $(this).css('animation-delay', (index * 0.1) + 's');
                        $(this).addClass('fade-in-left');
                    });
                }

                setupNavigation() {
                    $('.nav-item').on('click', (e) => {
                        this.hapticFeedback('light');
                        if (!$(e.currentTarget).hasClass('active')) {
                            $('.nav-item').removeClass('active');
                            $(e.currentTarget).addClass('active');
                        }
                    });

                    // Add ripple effect to buttons
                    $('.mobile-btn, .mobile-list-item').on('click', function(e) {
                        const $this = $(this);
                        const ripple = $('<span class="ripple"></span>');
                        const rect = this.getBoundingClientRect();
                        const size = Math.max(rect.width, rect.height);
                        const x = e.clientX - rect.left - size / 2;
                        const y = e.clientY - rect.top - size / 2;
                        
                        ripple.css({
                            width: size,
                            height: size,
                            left: x,
                            top: y
                        });
                        
                        $this.append(ripple);
                        setTimeout(() => ripple.remove(), 600);
                    });
                }

                setupPullToRefresh() {
                    const pullToRefresh = $('<div class="pull-to-refresh"><div class="refresh-icon"></div></div>');
                    $('.mobile-main-content').prepend(pullToRefresh);

                    document.addEventListener('touchstart', (e) => {
                        this.startY = e.touches[0].clientY;
                    }, { passive: true });

                    document.addEventListener('touchmove', (e) => {
                        this.currentY = e.touches[0].clientY;
                        const pullDistance = this.currentY - this.startY;
                        
                        if (pullDistance > 50 && window.scrollY === 0 && !this.isRefreshing) {
                            pullToRefresh.addClass('active');
                        }
                        
                        if (pullDistance > 100 && window.scrollY === 0 && !this.isRefreshing) {
                            this.refreshData();
                        }
                    }, { passive: true });

                    document.addEventListener('touchend', () => {
                        pullToRefresh.removeClass('active');
                    }, { passive: true });
                }

                setupHapticFeedback() {
                    // Add haptic feedback for supported devices
                    if ('vibrate' in navigator) {
                        this.hapticFeedback = (type = 'light') => {
                            const patterns = {
                                light: [10],
                                medium: [20],
                                heavy: [30],
                                success: [10, 10, 10],
                                error: [50, 50, 50]
                            };
                            navigator.vibrate(patterns[type] || patterns.light);
                        };
                    } else {
                        this.hapticFeedback = () => {}; // No-op for unsupported devices
                    }
                }

                setupNativeGestures() {
                    // Prevent zoom on double tap
                    document.addEventListener('touchend', (event) => {
                        const now = (new Date()).getTime();
                        if (now - this.lastTouchEnd <= 300) {
                            event.preventDefault();
                        }
                        this.lastTouchEnd = now;
                    }, false);

                    // Add swipe gestures
                    let startX = 0;
                    let startY = 0;
                    
                    document.addEventListener('touchstart', (e) => {
                        startX = e.touches[0].clientX;
                        startY = e.touches[0].clientY;
                    }, { passive: true });

                    document.addEventListener('touchend', (e) => {
                        const endX = e.changedTouches[0].clientX;
                        const endY = e.changedTouches[0].clientY;
                        const diffX = startX - endX;
                        const diffY = startY - endY;

                        // Horizontal swipe detection
                        if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                            if (diffX > 0) {
                                this.handleSwipeLeft();
                            } else {
                                this.handleSwipeRight();
                            }
                        }
                    }, { passive: true });
                }

                setupOfflineDetection() {
                    window.addEventListener('online', () => {
                        this.showToast('{{ _lang("Connection restored") }}', 'success');
                        this.hapticFeedback('success');
                    });

                    window.addEventListener('offline', () => {
                        this.showToast('{{ _lang("You are offline") }}', 'warning');
                    });
                }

                setupPushNotifications() {
                    // Check if push notifications are supported
                    if ('serviceWorker' in navigator && 'PushManager' in window) {
                        this.registerForPushNotifications();
                    }
                }

                async registerForPushNotifications() {
                    try {
                        const registration = await navigator.serviceWorker.ready;
                        const subscription = await registration.pushManager.getSubscription();
                        
                        if (!subscription) {
                            // Request permission and subscribe
                            const permission = await Notification.requestPermission();
                            if (permission === 'granted') {
                                const newSubscription = await registration.pushManager.subscribe({
                                    userVisibleOnly: true,
                                    applicationServerKey: this.urlBase64ToUint8Array('{{ config("app.vapid_public_key", "BEl62iUYgUivxIkv69yViEuiBIa40HI8Qy8yW9BFZJpyBZxP0QfX0Q8R5C0lS3qS0") }}')
                                });
                                
                                await this.saveSubscription(newSubscription);
                                this.showToast('{{ _lang("Notifications enabled") }}', 'success');
                            }
                        } else {
                            // Update existing subscription
                            await this.saveSubscription(subscription);
                        }
                    } catch (error) {
                        console.log('Push notification registration failed:', error);
                    }
                }

                async saveSubscription(subscription) {
                    try {
                        const response = await fetch('/push/register', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(subscription)
                        });
                        
                        const result = await response.json();
                        if (result.success) {
                            console.log('Push subscription saved successfully');
                        }
                    } catch (error) {
                        console.log('Failed to save push subscription:', error);
                    }
                }

                urlBase64ToUint8Array(base64String) {
                    const padding = '='.repeat((4 - base64String.length % 4) % 4);
                    const base64 = (base64String + padding)
                        .replace(/-/g, '+')
                        .replace(/_/g, '/');
                    
                    const rawData = window.atob(base64);
                    const outputArray = new Uint8Array(rawData.length);
                    
                    for (let i = 0; i < rawData.length; ++i) {
                        outputArray[i] = rawData.charCodeAt(i);
                    }
                    return outputArray;
                }

                handleSwipeLeft() {
                    // Navigate to next tab
                    const activeTab = $('.nav-item.active');
                    const nextTab = activeTab.next('.nav-item');
                    if (nextTab.length) {
                        nextTab.click();
                        this.hapticFeedback('light');
                    }
                }

                handleSwipeRight() {
                    // Navigate to previous tab
                    const activeTab = $('.nav-item.active');
                    const prevTab = activeTab.prev('.nav-item');
                    if (prevTab.length) {
                        prevTab.click();
                        this.hapticFeedback('light');
                    }
                }

                refreshData() {
                    if (this.isRefreshing) return;
                    
                    this.isRefreshing = true;
                    this.hapticFeedback('medium');
                    
                    // Show loading state
                    $('.mobile-main-content').addClass('loading');
                    
                    // Simulate refresh delay
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }

                showToast(message, type = 'info') {
                    $.toast({
                        heading: message,
                        text: '',
                        position: 'top-center',
                        loaderBg: type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : '#007bff',
                        icon: type,
                        hideAfter: 3000,
                        stack: 6
                    });
                }
            }

            // Initialize the mobile app
            $(document).ready(() => {
                new MobileApp();
            });

            // Global functions for backward compatibility
            function showNotifications() {
                $.toast({
                    heading: '{{ _lang("Notifications") }}',
                    text: '{{ _lang("You have") }} {{ request_count("messages") }} {{ _lang("new notifications") }}',
                    position: 'top-right',
                    loaderBg: '#ff6849',
                    icon: 'info',
                    hideAfter: 3000,
                    stack: 6
                });
            }
            
            function showMenu() {
                window.location.href = '{{ route("profile.edit") }}';
            }
        </script>

        @yield('scripts')
    </body>
</html>
