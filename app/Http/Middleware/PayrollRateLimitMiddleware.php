<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class PayrollRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $maxAttempts = 10, $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])
                ], 429);
            }
            
            return back()->with('error', _lang('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]));
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }

    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $tenant = app('tenant');
        
        // Create unique key based on user, tenant, and route
        $key = 'payroll:' . ($tenant ? $tenant->id : 'unknown') . ':' . 
               ($user ? $user->id : $request->ip()) . ':' . 
               $request->route()->getName();
        
        return $key;
    }
}
