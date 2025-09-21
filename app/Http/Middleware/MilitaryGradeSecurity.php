<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MilitaryGradeSecurity
{
    /**
     * Military-grade security middleware implementing banking-level protection
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        $userId = auth()->id();
        
        // 1. Advanced Rate Limiting (Banking-grade)
        $this->implementAdvancedRateLimiting($request, $ip, $userId);
        
        // 2. Request Validation & Sanitization
        $this->validateAndSanitizeRequest($request);
        
        // 3. Security Headers Implementation
        $response = $next($request);
        $this->addSecurityHeaders($response);
        
        // 4. Threat Detection & Logging
        $this->logSecurityEvent($request, $response, $startTime, $ip, $userId);
        
        // 5. Response Time Analysis (DDoS Detection)
        $this->analyzeResponseTime($startTime, $ip, $userId);
        
        return $response;
    }
    
    /**
     * Advanced rate limiting with multiple layers
     */
    private function implementAdvancedRateLimiting(Request $request, string $ip, $userId)
    {
        // Layer 1: IP-based rate limiting
        $ipKey = 'rate_limit_ip_' . $ip;
        if (RateLimiter::tooManyAttempts($ipKey, 100)) { // 100 requests per minute
            Log::critical('Rate limit exceeded - IP', [
                'ip' => $ip,
                'user_agent' => $request->userAgent(),
                'attempts' => RateLimiter::attempts($ipKey)
            ]);
            abort(429, 'Too many requests from this IP');
        }
        RateLimiter::hit($ipKey, 60);
        
        // Layer 2: User-based rate limiting (if authenticated)
        if ($userId) {
            $userKey = 'rate_limit_user_' . $userId;
            if (RateLimiter::tooManyAttempts($userKey, 200)) { // 200 requests per minute
                Log::warning('Rate limit exceeded - User', [
                    'user_id' => $userId,
                    'ip' => $ip,
                    'attempts' => RateLimiter::attempts($userKey)
                ]);
                abort(429, 'Too many requests from this user');
            }
            RateLimiter::hit($userKey, 60);
        }
        
        // Layer 3: Endpoint-specific rate limiting
        $endpointKey = 'rate_limit_endpoint_' . $request->path() . '_' . $ip;
        $maxAttempts = $this->getEndpointMaxAttempts($request->path());
        if (RateLimiter::tooManyAttempts($endpointKey, $maxAttempts)) {
            Log::warning('Rate limit exceeded - Endpoint', [
                'endpoint' => $request->path(),
                'ip' => $ip,
                'user_id' => $userId,
                'max_attempts' => $maxAttempts
            ]);
            abort(429, 'Too many requests to this endpoint');
        }
        RateLimiter::hit($endpointKey, 60);
    }
    
    /**
     * Get maximum attempts for specific endpoints
     */
    private function getEndpointMaxAttempts(string $path): int
    {
        $limits = [
            'login' => 5,           // 5 login attempts per minute
            'password/reset' => 3,  // 3 password reset attempts per minute
            'api/' => 50,           // 50 API calls per minute
            'admin/' => 30,         // 30 admin requests per minute
            'upload' => 10,         // 10 file uploads per minute
        ];
        
        foreach ($limits as $pattern => $limit) {
            if (Str::contains($path, $pattern)) {
                return $limit;
            }
        }
        
        return 100; // Default limit
    }
    
    /**
     * Validate and sanitize incoming requests
     */
    private function validateAndSanitizeRequest(Request $request)
    {
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/proc_open/i',
            '/popen/i',
        ];
        
        $allInput = $request->all();
        $inputString = json_encode($allInput);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $inputString)) {
                Log::critical('Suspicious input detected', [
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                    'pattern' => $pattern,
                    'input' => $allInput,
                    'user_agent' => $request->userAgent()
                ]);
                abort(400, 'Invalid request detected');
            }
        }
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/\'\s*or\s*1\s*=\s*1/i',
            '/\'\s*or\s*\'1\'\s*=\s*\'1/i',
            '/\'\s*union\s+select/i',
            '/\'\s*drop\s+table/i',
            '/\'\s*insert\s+into/i',
            '/\'\s*delete\s+from/i',
            '/\'\s*update\s+set/i',
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $inputString)) {
                Log::critical('SQL injection attempt detected', [
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                    'pattern' => $pattern,
                    'input' => $allInput
                ]);
                abort(400, 'Invalid request detected');
            }
        }
    }
    
    /**
     * Add comprehensive security headers
     */
    private function addSecurityHeaders($response)
    {
        // Content Security Policy (CSP) - Banking grade
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
               "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self' https:; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'; " .
               "object-src 'none'; " .
               "media-src 'self'; " .
               "manifest-src 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Additional security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // HSTS (HTTP Strict Transport Security) - Banking grade
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // Cache control for sensitive pages
        if (request()->is('admin/*') || request()->is('*portal/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
        
        // Remove server information
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');
    }
    
    /**
     * Log security events for monitoring
     */
    private function logSecurityEvent(Request $request, $response, float $startTime, string $ip, $userId)
    {
        $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        
        $logData = [
            'timestamp' => now()->toISOString(),
            'ip' => $ip,
            'user_id' => $userId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'response_code' => $response->getStatusCode(),
            'response_time_ms' => round($responseTime, 2),
            'memory_usage' => memory_get_usage(true),
            'request_size' => strlen($request->getContent()),
        ];
        
        // Log based on response code
        if ($response->getStatusCode() >= 400) {
            Log::warning('HTTP Error Response', $logData);
        } else {
            Log::info('Request Processed', $logData);
        }
        
        // Log slow requests
        if ($responseTime > 5000) { // 5 seconds
            Log::warning('Slow Request Detected', array_merge($logData, [
                'warning' => 'Response time exceeded 5 seconds'
            ]));
        }
    }
    
    /**
     * Analyze response time for DDoS detection
     */
    private function analyzeResponseTime(float $startTime, string $ip, $userId)
    {
        $responseTime = (microtime(true) - $startTime) * 1000;
        
        // Track response times per IP
        $responseTimeKey = 'response_times_' . $ip;
        $responseTimes = Cache::get($responseTimeKey, []);
        $responseTimes[] = $responseTime;
        
        // Keep only last 100 response times
        if (count($responseTimes) > 100) {
            $responseTimes = array_slice($responseTimes, -100);
        }
        
        Cache::put($responseTimeKey, $responseTimes, 300); // 5 minutes
        
        // Check for suspicious patterns
        if (count($responseTimes) >= 10) {
            $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
            
            // If average response time is very low, might be automated requests
            if ($avgResponseTime < 100) { // Less than 100ms average
                Log::warning('Potential automated requests detected', [
                    'ip' => $ip,
                    'user_id' => $userId,
                    'avg_response_time' => $avgResponseTime,
                    'request_count' => count($responseTimes)
                ]);
            }
        }
    }
}
