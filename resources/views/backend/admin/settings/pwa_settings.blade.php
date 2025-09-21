@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-sm-3">
        <ul class="nav flex-column nav-tabs settings-tab mb-4" role="tablist">
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#general"><i class="fas fa-cog"></i><span>{{ _lang('General Settings') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#currency_settings"><i class="fas fa-coins"></i><span>{{ _lang('Currency Settings') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="far fa-envelope"></i><span>{{ _lang('Email Settings') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sms_gateway"><i class="ti-comment"></i>{{ _lang('SMS Gateways') }}</a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#recaptcha"><i class="far fa-check-circle"></i><span>{{ _lang('Google Recaptcha V3') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#logo"><i class="fas fa-tint"></i><span>{{ _lang('Logo and Favicon') }}</span></a></li>
            <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#pwa_settings"><i class="fas fa-mobile-alt"></i><span>{{ _lang('PWA Settings') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cron_jobs"><i class="far fa-clock"></i><span>{{ _lang('Cron Jobs') }}</span></a></li>
            <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cache"><i class="fas fa-server"></i><span>{{ _lang('Cache Control') }}</span></a></li>
        </ul>
    </div>

    @php $settings = \App\Models\Setting::all(); @endphp

    <div class="col-sm-9">
        <div class="tab-content">
            <!-- PWA Settings Tab -->
            <div id="pwa_settings" class="tab-pane fade show active">
                <div class="card">
                    <div class="card-header">
                        <span class="panel-title">{{ _lang('Progressive Web App (PWA) Settings') }}</span>
                    </div>

                    <div class="card-body">
                        <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
                            @csrf
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="pwa_enabled" name="pwa_enabled" value="1" {{ get_option($settings, 'pwa_enabled') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="pwa_enabled">{{ _lang('Enable PWA') }}</label>
                                        </div>
                                        <small class="form-text text-muted">{{ _lang('Enable Progressive Web App functionality for mobile users') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('App Name') }}</label>
                                        <input type="text" class="form-control" name="pwa_app_name" value="{{ get_option($settings, 'pwa_app_name', get_option($settings, 'site_title', 'IntelliCash')) }}">
                                        <small class="form-text text-muted">{{ _lang('Full name of your PWA app') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Short Name') }}</label>
                                        <input type="text" class="form-control" name="pwa_short_name" value="{{ get_option($settings, 'pwa_short_name', get_option($settings, 'company_name', 'IntelliCash')) }}">
                                        <small class="form-text text-muted">{{ _lang('Short name displayed on home screen') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('App Description') }}</label>
                                        <textarea class="form-control" name="pwa_description" rows="3">{{ get_option($settings, 'pwa_description', 'Progressive Web App for IntelliCash - Manage your finances efficiently') }}</textarea>
                                        <small class="form-text text-muted">{{ _lang('Description of your PWA app') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Theme Color') }}</label>
                                        <input type="color" class="form-control" name="pwa_theme_color" value="{{ get_option($settings, 'pwa_theme_color', get_option($settings, 'primary_color', '#007bff')) }}">
                                        <small class="form-text text-muted">{{ _lang('Theme color for the PWA') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Background Color') }}</label>
                                        <input type="color" class="form-control" name="pwa_background_color" value="{{ get_option($settings, 'pwa_background_color', '#ffffff') }}">
                                        <small class="form-text text-muted">{{ _lang('Background color for the PWA') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Display Mode') }}</label>
                                        <select class="form-control select2" name="pwa_display_mode">
                                            <option value="standalone" {{ get_option($settings, 'pwa_display_mode') == 'standalone' ? 'selected' : '' }}>{{ _lang('Standalone') }}</option>
                                            <option value="fullscreen" {{ get_option($settings, 'pwa_display_mode') == 'fullscreen' ? 'selected' : '' }}>{{ _lang('Fullscreen') }}</option>
                                            <option value="minimal-ui" {{ get_option($settings, 'pwa_display_mode') == 'minimal-ui' ? 'selected' : '' }}>{{ _lang('Minimal UI') }}</option>
                                            <option value="browser" {{ get_option($settings, 'pwa_display_mode') == 'browser' ? 'selected' : '' }}>{{ _lang('Browser') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('How the app is displayed when launched') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Orientation') }}</label>
                                        <select class="form-control select2" name="pwa_orientation">
                                            <option value="portrait-primary" {{ get_option($settings, 'pwa_orientation') == 'portrait-primary' ? 'selected' : '' }}>{{ _lang('Portrait Primary') }}</option>
                                            <option value="landscape-primary" {{ get_option($settings, 'pwa_orientation') == 'landscape-primary' ? 'selected' : '' }}>{{ _lang('Landscape Primary') }}</option>
                                            <option value="any" {{ get_option($settings, 'pwa_orientation') == 'any' ? 'selected' : '' }}>{{ _lang('Any') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Preferred orientation for the app') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <h6 class="mt-4 mb-3">{{ _lang('App Icons') }}</h6>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('App Icon (192x192)') }}</label>
                                        <input type="file" class="dropify" name="pwa_icon_192" data-default-file="{{ get_option($settings, 'pwa_icon_192') ? asset('public/uploads/media/'.get_option($settings, 'pwa_icon_192')) : '' }}">
                                        <small class="form-text text-muted">{{ _lang('192x192 PNG icon for Android devices') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('App Icon (512x512)') }}</label>
                                        <input type="file" class="dropify" name="pwa_icon_512" data-default-file="{{ get_option($settings, 'pwa_icon_512') ? asset('public/uploads/media/'.get_option($settings, 'pwa_icon_512')) : '' }}">
                                        <small class="form-text text-muted">{{ _lang('512x512 PNG icon for high-resolution displays') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <h6 class="mt-4 mb-3">{{ _lang('Shortcuts') }}</h6>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="pwa_shortcut_dashboard" name="pwa_shortcut_dashboard" value="1" {{ get_option($settings, 'pwa_shortcut_dashboard') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="pwa_shortcut_dashboard">{{ _lang('Dashboard Shortcut') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="pwa_shortcut_transactions" name="pwa_shortcut_transactions" value="1" {{ get_option($settings, 'pwa_shortcut_transactions') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="pwa_shortcut_transactions">{{ _lang('Transactions Shortcut') }}</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="pwa_shortcut_profile" name="pwa_shortcut_profile" value="1" {{ get_option($settings, 'pwa_shortcut_profile') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="pwa_shortcut_profile">{{ _lang('Profile Shortcut') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <h6 class="mt-4 mb-3">{{ _lang('Advanced Settings') }}</h6>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="pwa_offline_support" name="pwa_offline_support" value="1" {{ get_option($settings, 'pwa_offline_support') == '1' ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="pwa_offline_support">{{ _lang('Offline Support') }}</label>
                                        </div>
                                        <small class="form-text text-muted">{{ _lang('Enable offline functionality and caching') }}</small>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="control-label">{{ _lang('Cache Strategy') }}</label>
                                        <select class="form-control select2" name="pwa_cache_strategy">
                                            <option value="cache-first" {{ get_option($settings, 'pwa_cache_strategy') == 'cache-first' ? 'selected' : '' }}>{{ _lang('Cache First') }}</option>
                                            <option value="network-first" {{ get_option($settings, 'pwa_cache_strategy') == 'network-first' ? 'selected' : '' }}>{{ _lang('Network First') }}</option>
                                            <option value="stale-while-revalidate" {{ get_option($settings, 'pwa_cache_strategy') == 'stale-while-revalidate' ? 'selected' : '' }}>{{ _lang('Stale While Revalidate') }}</option>
                                        </select>
                                        <small class="form-text text-muted">{{ _lang('Strategy for caching resources') }}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
                                        <button type="button" class="btn btn-info ml-2" onclick="testPWA()">{{ _lang('Test PWA') }}</button>
                                        <button type="button" class="btn btn-success ml-2" onclick="generateIcons()">{{ _lang('Generate Icons') }}</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testPWA() {
    // Check if PWA is installable
    if ('serviceWorker' in navigator && 'PushManager' in window) {
        $.toast({
            heading: '{{ _lang("PWA Status") }}',
            text: '{{ _lang("PWA is supported and ready for installation") }}',
            position: 'top-right',
            loaderBg: '#ff6849',
            icon: 'success',
            hideAfter: 3000,
            stack: 6
        });
    } else {
        $.toast({
            heading: '{{ _lang("PWA Status") }}',
            text: '{{ _lang("PWA features are not fully supported in this browser") }}',
            position: 'top-right',
            loaderBg: '#ff6849',
            icon: 'error',
            hideAfter: 3000,
            stack: 6
        });
    }
}

function generateIcons() {
    // This would typically call an API endpoint to generate icons from a master image
    $.toast({
        heading: '{{ _lang("Icon Generation") }}',
        text: '{{ _lang("Please upload a high-resolution logo (512x512 or larger) to generate PWA icons automatically") }}',
        position: 'top-right',
        loaderBg: '#ff6849',
        icon: 'info',
        hideAfter: 5000,
        stack: 6
    });
}

$(document).ready(function() {
    $('.dropify').dropify();
    $('.select2').select2();
});
</script>
@endsection
