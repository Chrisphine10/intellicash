<?php
namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Facades\URL;

class IdentifyTenant {
    public function handle($request, Closure $next) {
        $slug   = $request->segment(1);
        $tenant = Tenant::with('package')->where('slug', $slug)->firstOrFail();

        if($tenant->status == 0){
            abort(403, _lang('Tenant is not active. Contact with administator for more information.'));
        }

        app()->instance('tenant', $tenant);

        URL::defaults(['tenant' => $tenant->slug]);
        $request->merge(['tenant' => $tenant]);

        return $next($request);
    }
}