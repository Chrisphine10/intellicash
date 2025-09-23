<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;

class ESignatureAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = app('tenant');
        
        // Check if E-Signature module is enabled for this tenant
        if (!$this->isESignatureEnabled($tenant)) {
            abort(403, 'E-Signature module is not enabled for this organization.');
        }
        
        return $next($request);
    }

    /**
     * Check if E-Signature is enabled for the tenant
     */
    private function isESignatureEnabled(Tenant $tenant): bool
    {
        // Check tenant settings for E-Signature module
        return $tenant->esignature_enabled ?? false;
    }
}
