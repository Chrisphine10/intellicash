<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        if (!$user) {
            return redirect()->route('login')->with('error', 'Authentication required');
        }

        // Check tenant access
        $tenant = app('tenant');
        if (!$tenant || $user->tenant_id !== $tenant->id) {
            Log::warning('Cross-tenant access attempt blocked', [
                'user_id' => $user->id,
                'user_tenant_id' => $user->tenant_id,
                'requested_tenant_id' => $tenant->id ?? 'null',
                'url' => $request->fullUrl(),
                'ip' => $request->ip()
            ]);
            
            abort(403, 'Access denied: Cross-tenant access not allowed');
        }

        // Check account-specific permissions
        if ($request->route('id')) {
            $this->validateAccountAccess($request, $user, $tenant);
        }

        return $next($request);
    }

    /**
     * Validate access to specific account
     */
    private function validateAccountAccess(Request $request, $user, $tenant)
    {
        $accountId = $request->route('id');
        $model = $this->getModelFromRoute($request);
        
        if (!$model) {
            return;
        }

        // Check if account belongs to user's tenant
        $account = $model::where('tenant_id', $tenant->id)
                        ->where('id', $accountId)
                        ->first();

        if (!$account) {
            Log::warning('Unauthorized account access attempt', [
                'user_id' => $user->id,
                'account_id' => $accountId,
                'model' => $model,
                'tenant_id' => $tenant->id,
                'url' => $request->fullUrl(),
                'ip' => $request->ip()
            ]);
            
            abort(404, 'Account not found');
        }

        // Additional role-based checks
        if (!$this->hasAccountPermission($user, $account, $request->route()->getActionMethod())) {
            Log::warning('Insufficient permissions for account access', [
                'user_id' => $user->id,
                'user_type' => $user->user_type,
                'account_id' => $accountId,
                'action' => $request->route()->getActionMethod(),
                'url' => $request->fullUrl()
            ]);
            
            abort(403, 'Insufficient permissions');
        }
    }

    /**
     * Get model class from route
     */
    private function getModelFromRoute(Request $request)
    {
        $routeName = $request->route()->getName();
        
        $modelMap = [
            'savings_accounts' => \App\Models\SavingsAccount::class,
            'loans' => \App\Models\Loan::class,
            'members' => \App\Models\Member::class,
            'transactions' => \App\Models\Transaction::class,
        ];

        foreach ($modelMap as $route => $model) {
            if (str_contains($routeName, $route)) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Check if user has permission for specific account action
     */
    private function hasAccountPermission($user, $account, $action)
    {
        // Admin and superadmin have full access
        if (in_array($user->user_type, ['admin', 'superadmin'])) {
            return true;
        }

        // Customer users can only access their own accounts
        if ($user->user_type === 'customer') {
            // Check if account belongs to the user
            if (method_exists($account, 'member_id')) {
                return $account->member_id === $user->member?->id;
            }
        }

        // Default deny
        return false;
    }
}
