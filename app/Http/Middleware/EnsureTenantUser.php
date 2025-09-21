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
        
        if (Auth::check() && Auth::user()->tenant_id == $tenant->id && Auth::user()->user_type === 'user') {
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

        if(Auth::user()->user_type === 'superadmin' || Auth::user()->user_type === 'employee' ){
            return redirect()->route('login');
        }

        return $next($request);
    }
}
