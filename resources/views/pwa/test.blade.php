@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h4 class="panel-title">{{ _lang('PWA Test & Debug') }}</h4>
            </div>
            <div class="card-body">
                
                <!-- PWA Status -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>{{ _lang('PWA Status') }}</h5>
                        <div id="pwa-status" class="alert alert-info">
                            <i class="fas fa-spinner fa-spin"></i> {{ _lang('Checking PWA status...') }}
                        </div>
                    </div>
                </div>

                <!-- Installation Status -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title">{{ _lang('Installation Status') }}</h6>
                                <div id="install-status">
                                    <p><strong>{{ _lang('Service Worker:') }}</strong> <span id="sw-status">{{ _lang('Checking...') }}</span></p>
                                    <p><strong>{{ _lang('Manifest:') }}</strong> <span id="manifest-status">{{ _lang('Checking...') }}</span></p>
                                    <p><strong>{{ _lang('Installable:') }}</strong> <span id="installable-status">{{ _lang('Checking...') }}</span></p>
                                    <p><strong>{{ _lang('Installed:') }}</strong> <span id="installed-status">{{ _lang('Checking...') }}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-success">
                            <div class="card-body">
                                <h6 class="card-title">{{ _lang('Install Actions') }}</h6>
                                <button class="btn btn-primary btn-block mb-2" id="install-btn" style="display: none;">
                                    {{ _lang('Install PWA') }}
                                </button>
                                <button class="btn btn-info btn-block mb-2" id="test-offline">
                                    {{ _lang('Test Offline Mode') }}
                                </button>
                                <button class="btn btn-warning btn-block" id="clear-cache">
                                    {{ _lang('Clear Cache') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PWA Features Test -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h5>{{ _lang('PWA Features Test') }}</h5>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-download fa-2x text-primary mb-2"></i>
                                        <h6>{{ _lang('Install Prompt') }}</h6>
                                        <button class="btn btn-sm btn-outline-primary" id="show-install-prompt">
                                            {{ _lang('Show Prompt') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-wifi fa-2x text-success mb-2"></i>
                                        <h6>{{ _lang('Online Status') }}</h6>
                                        <span id="online-status" class="badge badge-success">{{ _lang('Online') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-database fa-2x text-info mb-2"></i>
                                        <h6>{{ _lang('Cache Status') }}</h6>
                                        <span id="cache-status" class="badge badge-info">{{ _lang('Checking...') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body text-center">
                                        <i class="fas fa-bell fa-2x text-warning mb-2"></i>
                                        <h6>{{ _lang('Notifications') }}</h6>
                                        <button class="btn btn-sm btn-outline-warning" id="test-notification">
                                            {{ _lang('Test') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PWA Settings -->
                <div class="row">
                    <div class="col-md-12">
                        <h5>{{ _lang('Current PWA Settings') }}</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>{{ _lang('Setting') }}</th>
                                        <th>{{ _lang('Value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ _lang('PWA Enabled') }}</td>
                                        <td><span class="badge badge-{{ get_option('pwa_enabled', 1) ? 'success' : 'danger' }}">{{ get_option('pwa_enabled', 1) ? _lang('Yes') : _lang('No') }}</span></td>
                                    </tr>
                                    <tr>
                                        <td>{{ _lang('App Name') }}</td>
                                        <td>{{ get_option('pwa_app_name', get_option('site_title', 'IntelliCash')) }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ _lang('Short Name') }}</td>
                                        <td>{{ get_option('pwa_short_name', get_option('company_name', 'IntelliCash')) }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ _lang('Theme Color') }}</td>
                                        <td><span class="badge" style="background-color: {{ get_option('pwa_theme_color', '#007bff') }};">{{ get_option('pwa_theme_color', '#007bff') }}</span></td>
                                    </tr>
                                    <tr>
                                        <td>{{ _lang('Display Mode') }}</td>
                                        <td>{{ get_option('pwa_display_mode', 'standalone') }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ _lang('Offline Support') }}</td>
                                        <td><span class="badge badge-{{ get_option('pwa_offline_support', 1) ? 'success' : 'danger' }}">{{ get_option('pwa_offline_support', 1) ? _lang('Enabled') : _lang('Disabled') }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let deferredPrompt;
    
    // Check PWA status
    checkPWAStatus();
    
    // Listen for beforeinstallprompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        document.getElementById('install-btn').style.display = 'block';
        updateStatus('installable-status', '{{ _lang("Yes") }}', 'success');
    });
    
    // Check if already installed
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) {
        updateStatus('installed-status', '{{ _lang("Yes") }}', 'success');
        document.getElementById('install-btn').style.display = 'none';
    } else {
        updateStatus('installed-status', '{{ _lang("No") }}', 'warning');
    }
    
    // Install button click
    document.getElementById('install-btn').addEventListener('click', async () => {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response: ${outcome}`);
            deferredPrompt = null;
            document.getElementById('install-btn').style.display = 'none';
        }
    });
    
    // Test offline mode
    document.getElementById('test-offline').addEventListener('click', () => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(registration => {
                registration.update();
                $.toast({
                    heading: '{{ _lang("Offline Test") }}',
                    text: '{{ _lang("Service worker updated. Try going offline and refreshing the page.") }}',
                    position: 'top-right',
                    icon: 'info'
                });
            });
        }
    });
    
    // Clear cache
    document.getElementById('clear-cache').addEventListener('click', () => {
        if ('caches' in window) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
            $.toast({
                heading: '{{ _lang("Cache Cleared") }}',
                text: '{{ _lang("All caches have been cleared.") }}',
                position: 'top-right',
                icon: 'success'
            });
        }
    });
    
    // Show install prompt
    document.getElementById('show-install-prompt').addEventListener('click', () => {
        const prompt = document.getElementById('pwa-install-prompt');
        if (prompt) {
            prompt.style.display = 'block';
        }
    });
    
    // Test notification
    document.getElementById('test-notification').addEventListener('click', () => {
        if ('Notification' in window) {
            if (Notification.permission === 'granted') {
                new Notification('IntelliCash PWA', {
                    body: '{{ _lang("This is a test notification from your PWA!") }}',
                    icon: '/public/uploads/media/pwa-icon-192x192.png'
                });
            } else if (Notification.permission !== 'denied') {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification('IntelliCash PWA', {
                            body: '{{ _lang("This is a test notification from your PWA!") }}',
                            icon: '/public/uploads/media/pwa-icon-192x192.png'
                        });
                    }
                });
            }
        }
    });
    
    // Monitor online status
    function updateOnlineStatus() {
        const status = navigator.onLine ? '{{ _lang("Online") }}' : '{{ _lang("Offline") }}';
        const badgeClass = navigator.onLine ? 'badge-success' : 'badge-danger';
        document.getElementById('online-status').textContent = status;
        document.getElementById('online-status').className = `badge ${badgeClass}`;
    }
    
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    updateOnlineStatus();
    
    function checkPWAStatus() {
        // Check Service Worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistration().then(registration => {
                if (registration) {
                    updateStatus('sw-status', '{{ _lang("Registered") }}', 'success');
                } else {
                    updateStatus('sw-status', '{{ _lang("Not Registered") }}', 'danger');
                }
            });
        } else {
            updateStatus('sw-status', '{{ _lang("Not Supported") }}', 'danger');
        }
        
        // Check Manifest
        fetch('/manifest.json')
            .then(response => response.json())
            .then(manifest => {
                updateStatus('manifest-status', '{{ _lang("Loaded") }}', 'success');
                updateStatus('pwa-status', '{{ _lang("PWA is properly configured and ready!") }}', 'success');
            })
            .catch(error => {
                updateStatus('manifest-status', '{{ _lang("Error Loading") }}', 'danger');
                updateStatus('pwa-status', '{{ _lang("PWA configuration has issues.") }}', 'danger');
            });
        
        // Check Cache
        if ('caches' in window) {
            caches.keys().then(names => {
                const cacheCount = names.length;
                updateStatus('cache-status', `${cacheCount} {{ _lang("caches") }}`, 'info');
            });
        } else {
            updateStatus('cache-status', '{{ _lang("Not Supported") }}', 'warning');
        }
    }
    
    function updateStatus(elementId, text, type) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = text;
            element.className = element.className.replace(/badge-\w+/, `badge-${type}`);
        }
    }
});
</script>
@endsection
