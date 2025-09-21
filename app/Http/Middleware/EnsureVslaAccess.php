<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class EnsureVslaAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $tenant = app('tenant');
        
        // Check if VSLA module is enabled for this tenant
        if (!$tenant->isVslaEnabled()) {
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('VSLA module is not enabled for this tenant.')
                ], 403);
            }
            
            return redirect()->route('dashboard.index')
                ->with('error', _lang('VSLA module is not enabled for this tenant.'));
        }
        
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login');
        }
        
        $user = Auth::user();
        
        // Super admin and tenant admin always have access
        if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
            return $next($request);
        }
        
        // For VSLA User role, check if they have the appropriate permissions
        if ($user->role && $user->role->name === 'VSLA User') {
            $route_name = Request::route()->getName();
            
            if ($route_name != '') {
                // Handle route name variations for update/store actions
                if (explode(".", $route_name)[1] == "update") {
                    $route_name = explode(".", $route_name)[0] . ".edit";
                } else if (explode(".", $route_name)[1] == "store") {
                    $route_name = explode(".", $route_name)[0] . ".create";
                }
                
                // Check if user has permission for this route
                if (!has_permission($route_name)) {
                    if ($request->ajax()) {
                        return response()->json([
                            'result' => 'error',
                            'message' => _lang('Permission denied! You do not have access to this VSLA feature.')
                        ], 403);
                    } else {
                        return back()->with('error', _lang('Permission denied! You do not have access to this VSLA feature.'));
                    }
                }
            }
            
            return $next($request);
        }
        
        // For other roles, check if they have VSLA permissions
        $route_name = Request::route()->getName();
        
        if ($route_name != '') {
            // Handle route name variations for update/store actions
            if (explode(".", $route_name)[1] == "update") {
                $route_name = explode(".", $route_name)[0] . ".edit";
            } else if (explode(".", $route_name)[1] == "store") {
                $route_name = explode(".", $route_name)[0] . ".create";
            }
            
            // Check if user has permission for this route
            if (!has_permission($route_name)) {
                if ($request->ajax()) {
                    return response()->json([
                        'result' => 'error',
                        'message' => _lang('Permission denied! You do not have access to this VSLA feature.')
                    ], 403);
                } else {
                    return back()->with('error', _lang('Permission denied! You do not have access to this VSLA feature.'));
                }
            }
        }
        
        return $next($request);
    }
}
