<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceMonitoring
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
        // Only monitor in non-production environments or when explicitly enabled
        if (app()->environment('production') && !config('app.debug')) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $queryCount = 0;

        // Enable query logging
        DB::enableQueryLog();

        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Log performance metrics
        $performanceData = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'execution_time_ms' => round($executionTime, 2),
            'memory_used_mb' => round($memoryUsed, 2),
            'query_count' => $queryCount,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Log slow requests
        if ($executionTime > 1000) { // More than 1 second
            Log::warning('Slow request detected', $performanceData);
        }

        // Log high memory usage
        if ($memoryUsed > 50) { // More than 50MB
            Log::warning('High memory usage detected', $performanceData);
        }

        // Log excessive queries (N+1 problem)
        if ($queryCount > 20) {
            Log::warning('Excessive queries detected (possible N+1 problem)', $performanceData);
        }

        // Log all performance data in debug mode
        if (config('app.debug')) {
            Log::info('Request performance metrics', $performanceData);
        }

        // Add performance headers for debugging
        if (config('app.debug')) {
            $response->headers->set('X-Execution-Time', $executionTime . 'ms');
            $response->headers->set('X-Memory-Usage', $memoryUsed . 'MB');
            $response->headers->set('X-Query-Count', $queryCount);
        }

        return $response;
    }
}
