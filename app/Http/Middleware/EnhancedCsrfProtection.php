<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;

class EnhancedCsrfProtection extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '*/callback/instamojo',
        'subscription_callback/instamojo',
        'api/*',
    ];

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @param  \Illuminate\Contracts\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct($app, $encrypter)
    {
        parent::__construct($app, $encrypter);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        // Skip CSRF for API routes
        if ($request->is('api/*')) {
            return $next($request);
        }

        // Skip CSRF for excluded URIs
        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        // Enhanced CSRF validation
        if (!$this->tokensMatch($request)) {
            $this->logCsrfViolation($request);
            throw new TokenMismatchException('CSRF token mismatch.');
        }

        // Regenerate CSRF token for additional security
        $this->regenerateToken($request);

        return $next($request);
    }

    /**
     * Check if the request should pass through CSRF verification
     */
    protected function shouldPassThrough($request): bool
    {
        foreach ($this->except as $except) {
            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the CSRF tokens match
     */
    protected function tokensMatch($request): bool
    {
        $token = $this->getTokenFromRequest($request);
        
        return is_string($request->session()->token()) &&
               is_string($token) &&
               hash_equals($request->session()->token(), $token);
    }

    /**
     * Get the CSRF token from the request
     */
    protected function getTokenFromRequest($request): ?string
    {
        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (!$token && $header = $request->header('X-XSRF-TOKEN')) {
            $token = $this->encrypter->decrypt($header, false);
        }

        return $token;
    }

    /**
     * Regenerate CSRF token for additional security
     */
    protected function regenerateToken($request): void
    {
        if ($request->session()->has('_token')) {
            $request->session()->regenerateToken();
        }
    }

    /**
     * Log CSRF violation attempts
     */
    protected function logCsrfViolation($request): void
    {
        \Log::warning('CSRF token mismatch detected', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'timestamp' => now(),
        ]);
    }

    /**
     * Add CSRF token to response
     */
    public function addCookieToResponse($request, $response)
    {
        $response->headers->setCookie(
            $this->newCookie($request, $this->getCookieConfig())
        );

        return $response;
    }

    /**
     * Create a new CSRF cookie
     */
    protected function newCookie($request, $config)
    {
        return cookie(
            $config['name'] ?? 'XSRF-TOKEN',
            $request->session()->token(),
            $config['expires'] ?? 0,
            $config['path'] ?? '/',
            $config['domain'] ?? null,
            $request->secure(),
            $config['http_only'] ?? true,
            $config['raw'] ?? false,
            $config['same_site'] ?? 'strict'
        );
    }

    /**
     * Get cookie configuration
     */
    protected function getCookieConfig()
    {
        return [
            'name' => 'XSRF-TOKEN',
            'expires' => 0,
            'path' => '/',
            'domain' => null,
            'http_only' => true,
            'raw' => false,
            'same_site' => 'strict'
        ];
    }
}
