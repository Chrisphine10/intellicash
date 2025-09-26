<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserOperationRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $operation
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $operation = 'default')
    {
        $user = Auth::user();
        $tenantId = app('tenant')->id ?? 'global';
        
        // Create unique key for rate limiting
        $key = "user_operation:{$operation}:{$user->id}:{$tenantId}";
        
        // Define rate limits based on operation type
        $limits = [
            'user_creation' => ['max_attempts' => 5, 'decay_minutes' => 60], // 5 users per hour
            'user_update' => ['max_attempts' => 20, 'decay_minutes' => 60], // 20 updates per hour
            'user_delete' => ['max_attempts' => 10, 'decay_minutes' => 60], // 10 deletions per hour
            'role_assignment' => ['max_attempts' => 15, 'decay_minutes' => 60], // 15 role changes per hour
            'permission_change' => ['max_attempts' => 10, 'decay_minutes' => 60], // 10 permission changes per hour
            'default' => ['max_attempts' => 30, 'decay_minutes' => 60], // 30 operations per hour
        ];
        
        $limit = $limits[$operation] ?? $limits['default'];
        
        // Check if rate limit is exceeded
        if (RateLimiter::tooManyAttempts($key, $limit['max_attempts'])) {
            $retryAfter = RateLimiter::availableIn($key);
            
            Log::warning('User operation rate limit exceeded', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'operation' => $operation,
                'tenant_id' => $tenantId,
                'retry_after' => $retryAfter,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('Too many attempts. Please try again in ' . ceil($retryAfter / 60) . ' minutes.'),
                    'retry_after' => $retryAfter
                ], 429);
            }
            
            return back()->with('error', _lang('Too many attempts. Please try again in ' . ceil($retryAfter / 60) . ' minutes.'));
        }
        
        // Hit the rate limiter
        RateLimiter::hit($key, $limit['decay_minutes'] * 60);
        
        // Log the operation for audit purposes
        Log::info('User operation performed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'operation' => $operation,
            'tenant_id' => $tenantId,
            'route' => $request->route()->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return $next($request);
    }
}