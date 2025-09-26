<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureTenantAdmin {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $tenant = app('tenant');
        if (Auth::check() && Auth::user()->tenant_id == $tenant->id && Auth::user()->user_type === 'admin') {
            return $next($request);
        }
        return redirect()->route('tenant.login', ['tenant' => app('tenant')->slug]);
    }
}
