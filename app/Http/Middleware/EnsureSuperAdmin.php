<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureSuperAdmin {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if (Auth::check() && is_null(Auth::user()->tenant_id) && Auth::user()->user_type === 'superadmin') {
            return $next($request);
        }
        return redirect()->route('login');
    }
}
