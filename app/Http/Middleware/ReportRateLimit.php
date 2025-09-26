<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;

class ReportRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $key = 'reports:' . auth()->id() . ':' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 10)) { // 10 requests per minute
            $seconds = RateLimiter::availableIn($key);
            
            Log::warning('Report rate limit exceeded', [
                'user_id' => auth()->id(),
                'ip_address' => $request->ip(),
                'seconds_remaining' => $seconds,
                'endpoint' => $request->path()
            ]);
            
            return response()->json([
                'error' => 'Too many report requests. Please wait ' . $seconds . ' seconds.',
                'retry_after' => $seconds
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 1 minute decay
        
        return $next($request);
    }
}
