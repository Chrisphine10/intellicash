<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// Military-grade debug endpoint with comprehensive security
Route::get('/debug-users', function () {
    // Multi-layer security validation
    if (!Auth::check()) {
        Log::warning('Debug endpoint: Unauthenticated access attempt', [
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
        abort(401, 'Unauthorized');
    }

    $user = Auth::user();
    
    // Only super admins can access debug information
    if ($user->user_type !== 'superadmin') {
        Log::warning('Debug endpoint: Unauthorized access attempt', [
            'ip' => request()->ip(),
            'user_id' => $user->id,
            'user_type' => $user->user_type
        ]);
        abort(403, 'Forbidden');
    }

    // Additional security: IP whitelist (configure in .env)
    $allowedIPs = explode(',', env('DEBUG_ALLOWED_IPS', '127.0.0.1,::1'));
    if (!in_array(request()->ip(), $allowedIPs)) {
        Log::critical('Debug endpoint: Unauthorized IP access attempt', [
            'ip' => request()->ip(),
            'user_id' => $user->id,
            'allowed_ips' => $allowedIPs
        ]);
        abort(403, 'IP not allowed');
    }

    // Rate limiting check
    $cacheKey = 'debug_access_' . $user->id . '_' . request()->ip();
    if (cache()->has($cacheKey)) {
        Log::warning('Debug endpoint: Rate limit exceeded', [
            'ip' => request()->ip(),
            'user_id' => $user->id
        ]);
        abort(429, 'Too many requests');
    }
    cache()->put($cacheKey, true, 300); // 5 minute rate limit

    // Log access for audit
    Log::info('Debug endpoint: Authorized access', [
        'ip' => request()->ip(),
        'user_id' => $user->id,
        'user_type' => $user->user_type
    ]);

    try {
        $output = "<h1>System Debug Information</h1>";
        $output .= "<p><strong>Access Time:</strong> " . now()->toDateTimeString() . "</p>";
        $output .= "<p><strong>Accessing User:</strong> " . $user->name . " (" . $user->email . ")</p>";
        
        // Current tenant
        $tenant = app('tenant');
        $output .= "<h2>Current Tenant:</h2>";
        $output .= "<p>ID: " . $tenant->id . ", Name: " . $tenant->name . ", Slug: " . $tenant->slug . "</p>";
        
        // System status
        $output .= "<h2>System Status:</h2>";
        $output .= "<p>Laravel Version: " . app()->version() . "</p>";
        $output .= "<p>PHP Version: " . PHP_VERSION . "</p>";
        $output .= "<p>Environment: " . app()->environment() . "</p>";
        
        // Database connection status
        try {
            \DB::connection()->getPdo();
            $output .= "<p>Database: <span style='color: green;'>Connected</span></p>";
        } catch (\Exception $e) {
            $output .= "<p>Database: <span style='color: red;'>Error - " . $e->getMessage() . "</span></p>";
        }
        
        // Memory usage
        $output .= "<p>Memory Usage: " . number_format(memory_get_usage(true) / 1024 / 1024, 2) . " MB</p>";
        
        return $output;
        
    } catch (\Exception $e) {
        Log::error('Debug endpoint: Error generating output', [
            'ip' => request()->ip(),
            'user_id' => $user->id,
            'error' => $e->getMessage()
        ]);
        return response('Debug information temporarily unavailable', 500);
    }
})->middleware(['auth', 'throttle:5,1']); // 5 requests per minute
