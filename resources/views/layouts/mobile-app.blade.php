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
                --text-primary: #333;
                --text-secondary: #666;
                --text-muted: #999;
                --border-color: #e9ecef;
                --bg-light: #f8f9fa;
                --bg-white: #ffffff;
                --shadow: 0 2px 8px rgba(0,0,0,0.1);
                --shadow-lg: 0 4px 16px rgba(0,0,0,0.15);
                --border-radius: 12px;
                --border-radius-sm: 8px;
                --safe-area-inset-bottom: env(safe-area-inset-bottom);
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background-color: var(--bg-light);
                color: var(--text-primary);
                line-height: 1.6;
                margin: 0;
                padding: 0;
                overflow-x: hidden;
                padding-bottom: calc(80px + var(--safe-area-inset-bottom));
            }

            /* Mobile App Header */
            .mobile-header {
                background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
                color: white;
                padding: 15px 20px;
                padding-top: calc(15px + env(safe-area-inset-top));
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                box-shadow: var(--shadow);
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 60px;
            }

            .mobile-header .header-left {
                display: flex;
                align-items: center;
                flex: 1;
            }

            .mobile-header .app-logo {
                width: 32px;
                height: 32px;
                border-radius: 8px;
                margin-right: 12px;
                background: rgba(255,255,255,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                font-weight: bold;
            }

            .mobile-header .app-logo img {
                width: 100%;
                height: 100%;
                border-radius: 8px;
            }

            .mobile-header .app-title {
                font-size: 18px;
                font-weight: 600;
                margin: 0;
            }

            .mobile-header .header-right {
                display: flex;
                align-items: center;
                gap: 15px;
            }

            .mobile-header .notification-btn,
            .mobile-header .menu-btn {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 36px;
                height: 36px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 16px;
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .mobile-header .notification-btn:hover,
            .mobile-header .menu-btn:hover {
                background: rgba(255,255,255,0.3);
            }

            /* Main Content */
            .mobile-main-content {
                margin-top: calc(60px + env(safe-area-inset-top));
                padding: 20px;
                min-height: calc(100vh - 140px - env(safe-area-inset-top) - var(--safe-area-inset-bottom));
            }

            /* Bottom Navigation */
            .bottom-nav {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: var(--bg-white);
                border-top: 1px solid var(--border-color);
                padding: 8px 0;
                padding-bottom: calc(8px + var(--safe-area-inset-bottom));
                z-index: 1000;
                display: flex;
                justify-content: space-around;
                box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
            }

            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 8px 12px;
                text-decoration: none;
                color: var(--text-muted);
                transition: all 0.3s ease;
                border-radius: var(--border-radius-sm);
                min-width: 60px;
            }

            .nav-item.active {
                color: var(--primary-color);
                background: var(--primary-light);
            }

            .nav-item .nav-icon {
                font-size: 20px;
                margin-bottom: 4px;
                transition: all 0.3s ease;
            }

            .nav-item .nav-label {
                font-size: 11px;
                font-weight: 500;
                text-align: center;
                line-height: 1.2;
            }

            .nav-item.active .nav-icon {
                transform: scale(1.1);
            }

            /* Cards */
            .mobile-card {
                background: var(--bg-white);
                border-radius: var(--border-radius);
                box-shadow: var(--shadow);
                margin-bottom: 20px;
                overflow: hidden;
            }

            .mobile-card .card-header {
                padding: 20px;
                border-bottom: 1px solid var(--border-color);
                background: var(--bg-white);
            }

            .mobile-card .card-body {
                padding: 20px;
            }

            .mobile-card .card-title {
                font-size: 18px;
                font-weight: 600;
                margin: 0 0 8px 0;
                color: var(--text-primary);
            }

            .mobile-card .card-subtitle {
                font-size: 14px;
                color: var(--text-secondary);
                margin: 0;
            }

            /* Buttons */
            .mobile-btn {
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: var(--border-radius-sm);
                padding: 12px 20px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                width: 100%;
                margin-bottom: 10px;
            }

            .mobile-btn:hover {
                transform: translateY(-1px);
                box-shadow: var(--shadow-lg);
                color: white;
                text-decoration: none;
            }

            .mobile-btn-secondary {
                background: var(--bg-white);
                color: var(--text-primary);
                border: 1px solid var(--border-color);
            }

            .mobile-btn-secondary:hover {
                background: var(--bg-light);
                color: var(--text-primary);
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
                padding: 16px 20px;
                border-bottom: 1px solid var(--border-color);
                text-decoration: none;
                color: var(--text-primary);
                transition: all 0.3s ease;
            }

            .mobile-list-item:last-child {
                border-bottom: none;
            }

            .mobile-list-item:hover {
                background: var(--bg-light);
                color: var(--text-primary);
                text-decoration: none;
            }

            .mobile-list-item .item-icon {
                width: 40px;
                height: 40px;
                border-radius: 8px;
                background: var(--primary-light);
                color: var(--primary-color);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
                font-size: 18px;
            }

            .mobile-list-item .item-content {
                flex: 1;
            }

            .mobile-list-item .item-title {
                font-size: 16px;
                font-weight: 500;
                margin: 0 0 4px 0;
            }

            .mobile-list-item .item-subtitle {
                font-size: 14px;
                color: var(--text-secondary);
                margin: 0;
            }

            .mobile-list-item .item-arrow {
                color: var(--text-muted);
                font-size: 16px;
            }

            /* Stats Cards */
            .stats-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .stat-card {
                background: var(--bg-white);
                border-radius: var(--border-radius);
                padding: 20px;
                text-align: center;
                box-shadow: var(--shadow);
            }

            .stat-card .stat-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
                background: var(--primary-light);
                color: var(--primary-color);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 12px;
                font-size: 24px;
            }

            .stat-card .stat-value {
                font-size: 24px;
                font-weight: 700;
                color: var(--text-primary);
                margin: 0 0 4px 0;
            }

            .stat-card .stat-label {
                font-size: 12px;
                color: var(--text-secondary);
                margin: 0;
                text-transform: uppercase;
                letter-spacing: 0.5px;
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
            // Mobile App JavaScript
            $(document).ready(function() {
                // Add fade-in animation to content
                $('.mobile-main-content').addClass('fade-in');
                
                // Handle navigation clicks
                $('.nav-item').on('click', function(e) {
                    if (!$(this).hasClass('active')) {
                        $('.nav-item').removeClass('active');
                        $(this).addClass('active');
                    }
                });
                
                // Handle pull-to-refresh (simple implementation)
                let startY = 0;
                let currentY = 0;
                let isRefreshing = false;
                
                document.addEventListener('touchstart', function(e) {
                    startY = e.touches[0].clientY;
                }, { passive: true });
                
                document.addEventListener('touchmove', function(e) {
                    currentY = e.touches[0].clientY;
                    if (currentY - startY > 100 && window.scrollY === 0 && !isRefreshing) {
                        refreshData();
                    }
                }, { passive: true });
            });
            
            function showNotifications() {
                // Implementation for notifications modal
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
                // Implementation for user menu
                window.location.href = '{{ route("profile.edit") }}';
            }
            
            function refreshData() {
                // Simple refresh implementation
                location.reload();
            }
            
            // Handle back button
            window.addEventListener('popstate', function(e) {
                // Custom back button handling if needed
            });
            
            // Prevent zoom on double tap
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function (event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
        </script>

        @yield('scripts')
    </body>
</html>
