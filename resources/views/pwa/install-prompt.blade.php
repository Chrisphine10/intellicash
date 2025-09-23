<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ _lang('Install App') }} - {{ get_option('pwa_app_name', get_option('site_title', 'IntelliCash')) }}</title>
    <meta name="theme-color" content="{{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }}">
    <link rel="manifest" href="{{ route('pwa.manifest') }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, {{ get_option('pwa_theme_color', '#007bff') }} 0%, #0056b3 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .install-container {
            background: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        
        .app-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 16px;
            background: {{ get_option('pwa_theme_color', '#007bff') }};
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            font-weight: bold;
        }
        
        .app-icon img {
            width: 100%;
            height: 100%;
            border-radius: 16px;
        }
        
        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .app-name {
            color: #666;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .features {
            text-align: left;
            margin: 30px 0;
        }
        
        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            font-size: 14px;
            color: #555;
        }
        
        .feature-icon {
            width: 20px;
            height: 20px;
            margin-right: 12px;
            color: {{ get_option('pwa_theme_color', '#007bff') }};
        }
        
        .install-btn {
            background: {{ get_option('pwa_theme_color', '#007bff') }};
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .install-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 123, 255, 0.3);
        }
        
        .install-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .skip-btn {
            background: none;
            border: none;
            color: #999;
            font-size: 14px;
            cursor: pointer;
            text-decoration: underline;
        }
        
        .instructions {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }
        
        .instructions h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .instructions ol {
            padding-left: 20px;
            font-size: 14px;
            color: #666;
            line-height: 1.6;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .browser-specific {
            display: none;
        }
        
        .browser-specific.show {
            display: block;
        }
        
        @media (max-width: 480px) {
            .install-container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            .app-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="app-icon">
            @if(file_exists(public_path('uploads/media/pwa-icon-192x192.png')))
                <img src="{{ asset('public/uploads/media/pwa-icon-192x192.png') }}" alt="{{ _lang('App Icon') }}">
            @else
                {{ strtoupper(substr(get_option('pwa_short_name', 'IC'), 0, 2)) }}
            @endif
        </div>
        
        <h1>{{ _lang('Install App') }}</h1>
        <p class="app-name">{{ get_option('pwa_app_name', get_option('site_title', 'IntelliCash')) }}</p>
        
        <div class="features">
            <div class="feature">
                <i class="fas fa-mobile-alt feature-icon"></i>
                {{ _lang('Access your account from home screen') }}
            </div>
            <div class="feature">
                <i class="fas fa-bolt feature-icon"></i>
                {{ _lang('Faster loading and offline access') }}
            </div>
            <div class="feature">
                <i class="fas fa-shield-alt feature-icon"></i>
                {{ _lang('Secure and private banking') }}
            </div>
        </div>
        
        <button id="installBtn" class="install-btn">
            {{ _lang('Install App') }}
        </button>
        
        <button id="skipBtn" class="skip-btn">
            {{ _lang('Not now') }}
        </button>
        
        <div id="instructions" class="instructions" style="display: none;">
            <h3>{{ _lang('How to install:') }}</h3>
            <div id="chrome-instructions" class="browser-specific">
                <ol>
                    <li>{{ _lang('Tap the menu button (three dots) in your browser') }}</li>
                    <li>{{ _lang('Select "Add to Home screen" or "Install app"') }}</li>
                    <li>{{ _lang('Tap "Add" or "Install" to confirm') }}</li>
                </ol>
            </div>
            <div id="safari-instructions" class="browser-specific">
                <ol>
                    <li>{{ _lang('Tap the Share button at the bottom of the screen') }}</li>
                    <li>{{ _lang('Scroll down and tap "Add to Home Screen"') }}</li>
                    <li>{{ _lang('Tap "Add" to confirm') }}</li>
                </ol>
            </div>
            <div id="firefox-instructions" class="browser-specific">
                <ol>
                    <li>{{ _lang('Tap the menu button (three lines) in your browser') }}</li>
                    <li>{{ _lang('Select "Install" or "Add to Home screen"') }}</li>
                    <li>{{ _lang('Tap "Add" to confirm') }}</li>
                </ol>
            </div>
        </div>
    </div>

    <script>
        let deferredPrompt;
        const installBtn = document.getElementById('installBtn');
        const skipBtn = document.getElementById('skipBtn');
        const instructions = document.getElementById('instructions');
        
        // Check if app is already installed
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            installBtn.textContent = '{{ _lang("App Already Installed") }}';
            installBtn.disabled = true;
        }
        
        // Listen for the beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'block';
        });
        
        // Handle install button click
        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    console.log('User accepted the install prompt');
                } else {
                    console.log('User dismissed the install prompt');
                    showInstructions();
                }
                
                deferredPrompt = null;
            } else {
                showInstructions();
            }
        });
        
        // Handle skip button
        skipBtn.addEventListener('click', () => {
            window.location.href = '/dashboard';
        });
        
        // Show browser-specific instructions
        function showInstructions() {
            instructions.style.display = 'block';
            
            const userAgent = navigator.userAgent.toLowerCase();
            let browserInstructions = 'chrome-instructions';
            
            if (userAgent.includes('safari') && !userAgent.includes('chrome')) {
                browserInstructions = 'safari-instructions';
            } else if (userAgent.includes('firefox')) {
                browserInstructions = 'firefox-instructions';
            }
            
            document.getElementById(browserInstructions).classList.add('show');
        }
        
        // Listen for app installed event
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            installBtn.textContent = '{{ _lang("App Installed Successfully!") }}';
            installBtn.disabled = true;
            
            setTimeout(() => {
                window.location.href = '/dashboard';
            }, 2000);
        });
        
        // Auto-redirect if no install prompt available after 3 seconds
        setTimeout(() => {
            if (!deferredPrompt && installBtn.style.display !== 'none') {
                showInstructions();
            }
        }, 3000);
    </script>
</body>
</html>
