<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Access\AuthorizationException;

class TransactionAuthorization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $permission = null): mixed
    {
        if (!Auth::check()) {
            throw new AuthorizationException('You are not authenticated to access this resource.');
        }

        $user = Auth::user();

        // Super admin and tenant admin always have access
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return $next($request);
        }

        // For other users, check specific permissions if provided
        if ($permission && !has_permission($permission)) {
            throw new AuthorizationException('You do not have the required permission to perform this action: ' . $permission);
        }

        return $next($request);
    }
}
