<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityService;

class EnhancedCsrfProtection
{
    protected $securityService;

    public function __construct(SecurityService $securityService)
    {
        $this->securityService = $securityService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip CSRF for API routes and certain callbacks
        if ($this->shouldSkipCsrf($request)) {
            return $next($request);
        }

        // Check for CSRF token
        if (!$request->has('_token') && !$request->header('X-CSRF-TOKEN')) {
            $this->securityService->monitorEvent('csrf_token_missing', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'CSRF token missing'], 419);
            }

            return redirect()->back()->with('error', 'Security token missing. Please try again.');
        }

        // Validate CSRF token
        if (!hash_equals(session()->token(), $request->input('_token'))) {
            $this->securityService->monitorEvent('csrf_token_mismatch', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'expected_token' => substr(session()->token(), 0, 10) . '...',
                'received_token' => substr($request->input('_token'), 0, 10) . '...',
            ]);

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Invalid security token'], 419);
            }

            return redirect()->back()->with('error', 'Invalid security token. Please try again.');
        }

        return $next($request);
    }

    /**
     * Determine if CSRF protection should be skipped
     */
    protected function shouldSkipCsrf(Request $request): bool
    {
        $skipPaths = [
            'api/',
            'callback/',
            'webhook/',
            'payment/',
        ];

        $path = $request->path();
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }

        return false;
    }
}