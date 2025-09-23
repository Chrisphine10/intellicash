<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectMobilePWA
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
        // Check if this is a mobile PWA request
        $isMobilePWA = false;
        
        // Check for mobile parameter
        if ($request->get('mobile') == '1') {
            $isMobilePWA = true;
        }
        
        // Check for mobile app header
        if ($request->header('X-Mobile-App') == '1') {
            $isMobilePWA = true;
        }
        
        // Check if running in standalone mode (PWA)
        if ($request->header('User-Agent') && 
            (strpos($request->header('User-Agent'), 'Mobile') !== false || 
             strpos($request->header('User-Agent'), 'Android') !== false ||
             strpos($request->header('User-Agent'), 'iPhone') !== false)) {
            
            // Additional check for PWA display mode
            if ($request->header('X-Display-Mode') == 'standalone' || 
                $request->header('X-Requested-With') == 'PWA') {
                $isMobilePWA = true;
            }
        }
        
        // Set mobile PWA flag
        $request->attributes->set('is_mobile_pwa', $isMobilePWA);
        
        // Add mobile PWA header to response if needed
        $response = $next($request);
        
        if ($isMobilePWA) {
            $response->header('X-Mobile-App', '1');
        }
        
        return $response;
    }
}
