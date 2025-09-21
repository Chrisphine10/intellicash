@if(get_option('pwa_enabled', 1))
<!-- PWA Install Prompt -->
<div id="pwa-install-prompt" class="pwa-install-prompt" style="display: none;">
    <div class="pwa-install-content">
        <div class="pwa-install-icon">
            <i class="fas fa-mobile-alt"></i>
        </div>
        <div class="pwa-install-text">
            <h6>{{ _lang('Install App') }}</h6>
            <p>{{ _lang('Install') }} {{ get_option('pwa_app_name', get_option('site_title', 'IntelliCash')) }} {{ _lang('on your device for quick access') }}</p>
        </div>
        <div class="pwa-install-actions">
            <button type="button" class="btn btn-primary btn-sm" id="pwa-install-btn">
                {{ _lang('Install') }}
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="pwa-dismiss-btn">
                {{ _lang('Not Now') }}
            </button>
        </div>
    </div>
</div>

<!-- PWA Install Instructions for iOS -->
<div id="pwa-ios-instructions" class="pwa-instructions" style="display: none;">
    <div class="pwa-instructions-content">
        <div class="pwa-instructions-header">
            <h6>{{ _lang('Install on iOS') }}</h6>
            <button type="button" class="close" id="pwa-ios-close">
                <span>&times;</span>
            </button>
        </div>
        <div class="pwa-instructions-body">
            <ol>
                <li>{{ _lang('Tap the Share button') }} <i class="fas fa-share"></i> {{ _lang('at the bottom of your screen') }}</li>
                <li>{{ _lang('Scroll down and tap') }} "{{ _lang('Add to Home Screen') }}"</li>
                <li>{{ _lang('Tap') }} "{{ _lang('Add') }}" {{ _lang('to confirm') }}</li>
            </ol>
        </div>
    </div>
</div>

<style>
.pwa-install-prompt {
    position: fixed;
    bottom: 20px;
    left: 20px;
    right: 20px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    animation: slideUp 0.3s ease-out;
}

.pwa-install-content {
    display: flex;
    align-items: center;
    padding: 16px;
    gap: 12px;
}

.pwa-install-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, {{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }}, #0056b3);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.pwa-install-text {
    flex: 1;
}

.pwa-install-text h6 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.pwa-install-text p {
    margin: 0;
    font-size: 14px;
    color: #666;
    line-height: 1.4;
}

.pwa-install-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.pwa-install-actions .btn {
    padding: 8px 16px;
    font-size: 14px;
    border-radius: 8px;
}

.pwa-instructions {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.2);
    z-index: 10000;
    max-width: 400px;
    width: 90%;
    animation: fadeIn 0.3s ease-out;
}

.pwa-instructions-content {
    padding: 0;
}

.pwa-instructions-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 20px 0 20px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.pwa-instructions-header h6 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.pwa-instructions-header .close {
    background: none;
    border: none;
    font-size: 24px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.pwa-instructions-body {
    padding: 0 20px 20px 20px;
}

.pwa-instructions-body ol {
    margin: 0;
    padding-left: 20px;
}

.pwa-instructions-body li {
    margin-bottom: 12px;
    font-size: 15px;
    color: #555;
    line-height: 1.5;
}

.pwa-instructions-body i {
    color: {{ get_option('pwa_theme_color', get_option('primary_color', '#007bff')) }};
    margin: 0 4px;
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translate(-50%, -50%) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%) scale(1);
    }
}

/* Hide on desktop */
@media (min-width: 768px) {
    .pwa-install-prompt {
        display: none !important;
    }
}

/* Dark overlay for instructions */
.pwa-instructions-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    animation: fadeIn 0.3s ease-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // PWA Install Prompt Logic
    let deferredPrompt;
    let installPromptShown = localStorage.getItem('pwa-install-prompt-shown');
    let installPromptDismissed = localStorage.getItem('pwa-install-prompt-dismissed');
    
    // Check if PWA is already installed
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        return; // Already installed
    }
    
    // Show install prompt for supported browsers
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        
        // Show prompt if not dismissed and not shown recently
        if (!installPromptDismissed && !installPromptShown) {
            setTimeout(() => {
                showInstallPrompt();
            }, 5000); // Show after 5 seconds
        }
    });
    
    // Handle install button click
    document.getElementById('pwa-install-btn').addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            deferredPrompt = null;
            hideInstallPrompt();
        } else {
            // Show iOS instructions
            showIOSInstructions();
        }
    });
    
    // Handle dismiss button click
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
        hideInstallPrompt();
        localStorage.setItem('pwa-install-prompt-dismissed', 'true');
    });
    
    // Handle iOS instructions close
    document.getElementById('pwa-ios-close').addEventListener('click', () => {
        hideIOSInstructions();
    });
    
    // Close iOS instructions when clicking overlay
    document.addEventListener('click', (e) => {
        if (e.target.classList.contains('pwa-instructions-overlay')) {
            hideIOSInstructions();
        }
    });
    
    function showInstallPrompt() {
        const prompt = document.getElementById('pwa-install-prompt');
        prompt.style.display = 'block';
        localStorage.setItem('pwa-install-prompt-shown', 'true');
    }
    
    function hideInstallPrompt() {
        const prompt = document.getElementById('pwa-install-prompt');
        prompt.style.display = 'none';
    }
    
    function showIOSInstructions() {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'pwa-instructions-overlay';
        document.body.appendChild(overlay);
        
        // Show instructions
        const instructions = document.getElementById('pwa-ios-instructions');
        instructions.style.display = 'block';
        
        hideInstallPrompt();
    }
    
    function hideIOSInstructions() {
        const instructions = document.getElementById('pwa-ios-instructions');
        instructions.style.display = 'none';
        
        const overlay = document.querySelector('.pwa-instructions-overlay');
        if (overlay) {
            overlay.remove();
        }
    }
    
    // Detect iOS
    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent);
    }
    
    // Show iOS-specific prompt if on iOS and no install prompt available
    if (isIOS() && !deferredPrompt && !installPromptDismissed && !installPromptShown) {
        setTimeout(() => {
            showInstallPrompt();
        }, 5000);
    }
    
    // Reset prompt dismissal after 7 days
    const dismissalDate = localStorage.getItem('pwa-install-prompt-dismissed-date');
    if (dismissalDate) {
        const daysSinceDismissal = (Date.now() - parseInt(dismissalDate)) / (1000 * 60 * 60 * 24);
        if (daysSinceDismissal > 7) {
            localStorage.removeItem('pwa-install-prompt-dismissed');
            localStorage.removeItem('pwa-install-prompt-dismissed-date');
        }
    }
    
    // Store dismissal date
    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
        localStorage.setItem('pwa-install-prompt-dismissed-date', Date.now().toString());
    });
});
</script>
@endif
