<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PayrollCSRFProtection
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip CSRF for AJAX requests with proper headers
        if ($request->ajax() && $request->hasHeader('X-CSRF-TOKEN')) {
            return $next($request);
        }

        // For non-AJAX requests, ensure CSRF token is present
        if (!$request->ajax() && !$request->hasValidSignature()) {
            Log::warning('Payroll CSRF violation attempt', [
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'tenant_id' => app('tenant')->id ?? null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('Invalid request signature. Please refresh the page and try again.')
                ], 419);
            }

            return back()->with('error', _lang('Invalid request signature. Please refresh the page and try again.'));
        }

        return $next($request);
    }
}
