<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;

class EnsureTenantUser {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $tenant = app('tenant');
        
        if (Auth::check() && Auth::user()->tenant_id == $tenant->id) {
            $user = Auth::user();
            
            // Super admin and tenant admin always have access
            if ($user->user_type === 'superadmin' || $user->user_type === 'admin') {
                return $next($request);
            }
            
            // For regular users, check role-based permissions
            if ($user->user_type === 'user') {
                $route_name = Request::route()->getName();

                if ($route_name != '') {
                    if (explode(".", $route_name)[1] == "update") {
                        $route_name = explode(".", $route_name)[0] . ".edit";
                    } else if (explode(".", $route_name)[1] == "store") {
                        $route_name = explode(".", $route_name)[0] . ".create";
                    }
                    
                    if (! has_permission($route_name)) {
                        if (! $request->ajax()) {
                            return back()->with('error', _lang('Permission denied !'));
                        } else {
                            return new Response('<h4 class="text-center text-danger">' . _lang('Permission denied !') . '</h4>');
                        }
                    }
                }
            }
        }

        // Redirect super admin and employee to tenant login
        if(Auth::check() && (Auth::user()->user_type === 'superadmin' || Auth::user()->user_type === 'employee')){
            return redirect()->route('tenant.login', ['tenant' => app('tenant')->slug]);
        }

        return $next($request);
    }
}
