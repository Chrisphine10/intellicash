<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantAccess
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
        $tenant = app('tenant');
        
        if (!$tenant) {
            abort(403, 'Tenant not identified');
        }
        
        // Tenant status is already checked by IdentifyTenant middleware
        // Just ensure tenant exists and is accessible
        return $next($request);
    }
}
