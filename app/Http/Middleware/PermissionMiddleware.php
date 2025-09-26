<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission)
    {
        if (!auth()->check()) {
            abort(401, 'Authentication required');
        }
        
        $user = auth()->user();
        
        // Check if user has the required permission
        if (!$user->can($permission)) {
            abort(403, 'Insufficient permissions');
        }
        
        return $next($request);
    }
}
