<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityService;

class PreventGlobalScopeBypass
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
        // Monitor for suspicious patterns that might indicate global scope bypass attempts
        $suspiciousPatterns = [
            'withoutGlobalScopes',
            'withoutGlobalScope',
            'unscoped',
            'bypass',
        ];

        $requestData = $request->all();
        $requestString = json_encode($requestData);

        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($requestString, $pattern) !== false) {
                $this->securityService->monitorEvent('global_scope_bypass_attempt', [
                    'user_id' => Auth::id(),
                    'pattern' => $pattern,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'request_data' => $requestData,
                ]);

                Log::warning('Potential global scope bypass attempt detected', [
                    'user_id' => Auth::id(),
                    'pattern' => $pattern,
                    'url' => $request->fullUrl(),
                    'ip_address' => $request->ip(),
                ]);
            }
        }

        return $next($request);
    }
}