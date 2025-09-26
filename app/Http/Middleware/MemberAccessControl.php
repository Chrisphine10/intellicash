<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\SecurityService;

class MemberAccessControl
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
        // Only apply to member-related routes
        if (!$this->isMemberRoute($request)) {
            return $next($request);
        }

        // Check user permissions
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Validate user has permission to access member data
        if (!$this->hasMemberAccess($user)) {
            $this->securityService->monitorEvent('unauthorized_member_access', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized access to member data'], 403);
        }

        // Validate member ID parameter if present
        $memberId = $request->route('id') ?? $request->input('member_id');
        if ($memberId && !$this->validateMemberAccess($memberId, $tenantId)) {
            $this->securityService->monitorEvent('cross_tenant_member_access', [
                'user_id' => $user->id,
                'member_id' => $memberId,
                'tenant_id' => $tenantId,
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
            ]);

            return response()->json(['error' => 'Access denied to this member'], 403);
        }

        return $next($request);
    }

    /**
     * Check if the request is for member-related routes
     */
    private function isMemberRoute(Request $request): bool
    {
        $memberRoutes = [
            'members',
            'member',
            'savings_accounts',
            'loans',
            'transactions',
        ];

        $path = $request->path();
        foreach ($memberRoutes as $route) {
            if (strpos($path, $route) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has permission to access member data
     */
    private function hasMemberAccess($user): bool
    {
        $allowedTypes = ['admin', 'superadmin'];
        return in_array($user->user_type, $allowedTypes);
    }

    /**
     * Validate that the member belongs to the user's tenant
     */
    private function validateMemberAccess($memberId, $tenantId): bool
    {
        try {
            $member = \App\Models\Member::where('id', $memberId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            return $member !== null;
        } catch (\Exception $e) {
            Log::error('Error validating member access', [
                'member_id' => $memberId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
