<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SMSSecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting for SMS endpoints
        $key = 'sms_security_' . $request->ip() . '_' . (auth()->id() ?? 'guest');
        
        if (RateLimiter::tooManyAttempts($key, 10)) { // 10 attempts per minute
            Log::warning('SMS rate limit exceeded', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'endpoint' => $request->path(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json([
                'result' => 'error',
                'message' => 'Too many SMS requests. Please try again later.'
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 1 minute decay
        
        // Validate phone number format
        if ($request->has('phone')) {
            $phone = $request->input('phone');
            if (!$this->isValidPhoneNumber($phone)) {
                Log::warning('Invalid phone number format', [
                    'phone' => $phone,
                    'ip' => $request->ip(),
                    'user_id' => auth()->id()
                ]);
                
                return response()->json([
                    'result' => 'error',
                    'message' => 'Invalid phone number format.'
                ], 422);
            }
        }
        
        // Validate message content
        if ($request->has('message')) {
            $message = $request->input('message');
            if (!$this->isValidMessage($message)) {
                Log::warning('Invalid SMS message content', [
                    'message_length' => strlen($message),
                    'ip' => $request->ip(),
                    'user_id' => auth()->id()
                ]);
                
                return response()->json([
                    'result' => 'error',
                    'message' => 'Invalid message content.'
                ], 422);
            }
        }
        
        return $next($request);
    }
    
    /**
     * Validate phone number format and security
     */
    private function isValidPhoneNumber($phone): bool
    {
        if (empty($phone) || strlen($phone) < 8 || strlen($phone) > 20) {
            return false;
        }
        
        // Remove any non-numeric characters except + at the beginning
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        
        // Check for valid international format
        if (preg_match('/^(\+?[1-9][0-9]{7,14})$/', $cleaned)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Validate message content
     */
    private function isValidMessage($message): bool
    {
        if (empty($message) || strlen($message) > 160) {
            return false;
        }
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return false;
            }
        }
        
        return true;
    }
}
