<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityService;

class EnsureTenantIsolation
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
        // Skip tenant isolation for superadmins and system operations
        if (Auth::check() && Auth::user()->user_type === 'superadmin') {
            return $next($request);
        }

        // Ensure user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Validate tenant_id exists and is valid
        if (!$tenantId) {
            $this->securityService->monitorEvent('tenant_isolation_violation', [
                'user_id' => $user->id,
                'reason' => 'missing_tenant_id',
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
            ]);
            
            Auth::logout();
            return redirect()->route('login')->with('error', 'Invalid tenant access. Please contact support.');
        }

        // Set tenant context for the request
        $request->attributes->set('tenant_id', $tenantId);
        app()->instance('tenant_id', $tenantId);

        // Log tenant access for audit trail
        $this->securityService->monitorEvent('tenant_access', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
        ]);

        return $next($request);
    }
}
