<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayrollModuleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app('tenant');
        
        if (!$tenant || !$tenant->isPayrollEnabled()) {
            if ($request->ajax()) {
                return response()->json([
                    'result' => 'error',
                    'message' => _lang('Payroll module is not enabled for this organization')
                ], 403);
            }
            
            return redirect()->route('modules.index')
                ->with('error', _lang('Payroll module is not enabled for this organization'));
        }

        return $next($request);
    }
}