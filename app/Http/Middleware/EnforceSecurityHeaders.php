<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get security configuration
        $securityConfig = config('security', []);

        // Set security headers
        $this->setSecurityHeaders($response, $securityConfig);

        return $response;
    }

    /**
     * Set security headers on the response
     */
    private function setSecurityHeaders(Response $response, array $config): void
    {
        $headers = $config['headers'] ?? [];

        // X-Frame-Options
        if (isset($headers['x_frame_options'])) {
            $response->headers->set('X-Frame-Options', $headers['x_frame_options']);
        }

        // X-Content-Type-Options
        if (isset($headers['x_content_type_options'])) {
            $response->headers->set('X-Content-Type-Options', $headers['x_content_type_options']);
        }

        // X-XSS-Protection
        if (isset($headers['x_xss_protection'])) {
            $response->headers->set('X-XSS-Protection', $headers['x_xss_protection']);
        }

        // Referrer-Policy
        if (isset($headers['referrer_policy'])) {
            $response->headers->set('Referrer-Policy', $headers['referrer_policy']);
        }

        // Content-Security-Policy
        if (isset($headers['content_security_policy'])) {
            $response->headers->set('Content-Security-Policy', $headers['content_security_policy']);
        }

        // Strict-Transport-Security (HSTS)
        if (request()->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Permissions-Policy
        $response->headers->set('Permissions-Policy', 
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        // Cache-Control for sensitive pages
        if ($this->isSensitivePage()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }
    }

    /**
     * Check if the current page is sensitive and should not be cached
     */
    private function isSensitivePage(): bool
    {
        $sensitivePaths = [
            '/admin/security',
            '/admin/dashboard',
            '/admin/users',
            '/admin/settings',
            '/admin/audit',
            '/admin/security/testing',
        ];

        $currentPath = request()->path();

        foreach ($sensitivePaths as $path) {
            if (str_starts_with($currentPath, ltrim($path, '/'))) {
                return true;
            }
        }

        return false;
    }
}
