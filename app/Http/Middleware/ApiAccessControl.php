<?php

namespace App\Http\Middleware;

use App\Services\AccessControlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiAccessControl
{
    protected $accessControlService;

    public function __construct(AccessControlService $accessControlService)
    {
        $this->accessControlService = $accessControlService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $permission = null)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required'
            ], 401);
        }

        // Check tenant access
        $tenant = app('tenant');
        if (!$this->accessControlService->canAccessTenant($user, $tenant->id)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Access denied for this tenant'
            ], 403);
        }

        // Check specific permission if provided
        if ($permission && !$this->accessControlService->hasPermission($user, $permission)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Insufficient permissions'
            ], 403);
        }

        return $next($request);
    }
}
