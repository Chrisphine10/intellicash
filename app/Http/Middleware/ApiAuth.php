<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ApiAuth
{
    public function handle(Request $request, Closure $next, $type = 'tenant')
    {
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (!$apiKey || !$apiSecret) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'API key and secret are required'
            ], 401);
        }

        // Check cache first
        $cacheKey = "api_key_{$apiKey}";
        $cachedKey = Cache::get($cacheKey);

        if (!$cachedKey) {
            $cachedKey = ApiKey::where('key', $apiKey)
                ->where('type', $type)
                ->active()
                ->first();

            if (!$cachedKey) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => 'Invalid API key'
                ], 401);
            }

            // Cache for 5 minutes
            Cache::put($cacheKey, $cachedKey, 300);
        }

        // Verify secret
        if (!hash_equals($cachedKey->secret, $apiSecret)) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API secret'
            ], 401);
        }

        // Check IP whitelist
        if (!$cachedKey->canAccessIp($request->ip())) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'IP address not allowed'
            ], 403);
        }

        // Check rate limiting
        if (!$this->checkRateLimit($cachedKey, $request)) {
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded'
            ], 429);
        }

        // Set tenant context
        if ($cachedKey->tenant_id) {
            $request->attributes->set('tenant_id', $cachedKey->tenant_id);
            $request->attributes->set('api_key', $cachedKey);
        }

        // Update last used
        $cachedKey->updateLastUsed();

        return $next($request);
    }

    private function checkRateLimit($apiKey, Request $request)
    {
        $rateLimit = $apiKey->rate_limit ?? 1000; // requests per hour
        $key = "rate_limit_{$apiKey->key}_{$request->ip()}";
        
        $current = Cache::get($key, 0);
        
        if ($current >= $rateLimit) {
            return false;
        }

        Cache::put($key, $current + 1, 3600); // 1 hour
        return true;
    }
}
