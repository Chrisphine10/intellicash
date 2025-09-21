<?php

namespace App\Http\Middleware;

use Closure;

class CanInstall {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if (env('APP_INSTALLED', true) == true) {
            if (file_exists('credit-lite-saas-update.zip')) {
                return redirect('system/update');
            }
            return $next($request);
        }
        return redirect('/install');
    }
}
