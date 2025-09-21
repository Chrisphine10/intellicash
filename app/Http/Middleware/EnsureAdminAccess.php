<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureAdminAccess
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
        $user = Auth::user();
        
        // Super admin has access to everything
        if ($user && $user->user_type === 'superadmin') {
            return $next($request);
        }
        
        // Tenant admin has access to everything within their tenant
        if ($user && $user->user_type === 'admin') {
            return $next($request);
        }
        
        // For other users, check specific permissions
        $routeName = $request->route()->getName();
        
        if ($routeName && !has_permission($routeName)) {
            if ($request->ajax()) {
                return response()->json(['result' => 'error', 'message' => _lang('Permission denied!')]);
            }
            
            return back()->with('error', _lang('Permission denied!'));
        }
        
        return $next($request);
    }
}
