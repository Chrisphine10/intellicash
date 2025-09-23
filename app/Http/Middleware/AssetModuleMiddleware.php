<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AssetModuleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant = app('tenant');
        
        if (!$tenant || !$tenant->isAssetManagementEnabled()) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => _lang('Asset Management module is not enabled')
                ], 403);
            }
            
            return redirect()->route('dashboard')->with('error', _lang('Asset Management module is not enabled'));
        }

        return $next($request);
    }
}
