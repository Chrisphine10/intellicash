<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\ThreatMonitoringService;
use App\Services\CryptographicProtectionService;

class SecurityDashboardController extends Controller
{
    protected $threatMonitoring;
    protected $cryptoService;

    public function __construct(ThreatMonitoringService $threatMonitoring, CryptographicProtectionService $cryptoService)
    {
        $this->threatMonitoring = $threatMonitoring;
        $this->cryptoService = $cryptoService;
    }

    /**
     * Display the main security dashboard
     */
    public function index()
    {
        $securityMetrics = $this->getSecurityMetrics();
        $threatStatistics = $this->threatMonitoring->getThreatStatistics();
        $recentThreats = $this->getRecentThreats();
        $topThreats = $this->getTopThreats();
        $systemHealth = $this->getSystemHealth();
        $blockedIPs = $this->getBlockedIPs();
        $securityAlerts = $this->getSecurityAlerts();

        return view('backend.admin.security.dashboard', compact(
            'securityMetrics',
            'threatStatistics',
            'recentThreats',
            'topThreats',
            'systemHealth',
            'blockedIPs',
            'securityAlerts'
        ));
    }

    /**
     * Get real-time security metrics
     */
    public function getMetrics()
    {
        $metrics = $this->getSecurityMetrics();
        $threatStats = $this->threatMonitoring->getThreatStatistics();
        
        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'threat_stats' => $threatStats,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get threat details for a specific time range
     */
    public function getThreatDetails(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(7)->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));
        $threatType = $request->get('threat_type', 'all');

        $threats = $this->getThreatsByDateRange($startDate, $endDate, $threatType);
        
        return response()->json([
            'success' => true,
            'data' => $threats
        ]);
    }

    /**
     * Get security analytics data
     */
    public function getAnalytics(Request $request)
    {
        $period = $request->get('period', '7d'); // 1d, 7d, 30d, 90d
        
        $analytics = [
            'threat_timeline' => $this->getThreatTimeline($period),
            'threat_distribution' => $this->getThreatDistribution($period),
            'top_attacking_ips' => $this->getTopAttackingIPs($period),
            'security_events' => $this->getSecurityEvents($period),
            'response_times' => $this->getResponseTimeAnalytics($period),
            'file_upload_security' => $this->getFileUploadSecurity($period)
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }

    /**
     * Block an IP address
     */
    public function blockIP(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip',
            'duration' => 'required|integer|min:1|max:1440' // 1 minute to 24 hours
        ]);

        $ip = $request->ip;
        $duration = $request->duration; // in minutes

        // Block the IP
        $blockKey = 'blocked_ip_' . $ip;
        Cache::put($blockKey, true, $duration * 60);

        // Log the manual block
        Log::warning('IP manually blocked by admin', [
            'ip' => $ip,
            'duration_minutes' => $duration,
            'blocked_by' => auth()->id(),
            'timestamp' => now()->toISOString()
        ]);

        return response()->json([
            'success' => true,
            'message' => "IP {$ip} blocked for {$duration} minutes"
        ]);
    }

    /**
     * Unblock an IP address
     */
    public function unblockIP(Request $request)
    {
        $request->validate([
            'ip' => 'required|ip'
        ]);

        $ip = $request->ip;
        $blockKey = 'blocked_ip_' . $ip;
        
        if (Cache::has($blockKey)) {
            Cache::forget($blockKey);
            
            Log::info('IP manually unblocked by admin', [
                'ip' => $ip,
                'unblocked_by' => auth()->id(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => "IP {$ip} unblocked successfully"
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => "IP {$ip} is not currently blocked"
        ]);
    }

    /**
     * Get security configuration status
     */
    public function getSecurityConfig()
    {
        $config = [
            'encryption_enabled' => env('SESSION_ENCRYPT', false),
            'https_enforced' => env('SESSION_SECURE_COOKIE', false),
            'rate_limiting_enabled' => env('RATE_LIMIT_ENABLED', true),
            'threat_detection_enabled' => env('THREAT_DETECTION_ENABLED', true),
            'file_upload_security' => env('FILE_UPLOAD_SCAN_MALWARE', true),
            'debug_access_restricted' => env('DEBUG_REQUIRE_SUPERADMIN', true),
            'audit_logging_enabled' => env('AUDIT_ENABLED', true),
            'security_headers_enabled' => env('SECURITY_HEADERS_ENABLED', true)
        ];

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    /**
     * Export security logs
     */
    public function exportLogs(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'log_type' => 'required|in:security,audit,threats,all'
        ]);

        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $logType = $request->log_type;

        // This would typically export logs to a file
        // For now, we'll return the data as JSON
        $logs = $this->getLogsForExport($startDate, $endDate, $logType);

        return response()->json([
            'success' => true,
            'data' => $logs,
            'export_info' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'log_type' => $logType,
                'total_records' => count($logs)
            ]
        ]);
    }

    /**
     * Get comprehensive security metrics
     */
    private function getSecurityMetrics()
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        
        // Get today's metrics
        $todayMetrics = Cache::get("daily_metrics_{$today}", []);
        $yesterdayMetrics = Cache::get("daily_metrics_{$yesterday}", []);

        return [
            'today' => [
                'total_events' => array_sum($todayMetrics),
                'failed_logins' => $todayMetrics['failed_login'] ?? 0,
                'suspicious_requests' => $todayMetrics['suspicious_request'] ?? 0,
                'sql_injection_attempts' => $todayMetrics['sql_injection_attempt'] ?? 0,
                'rate_limit_violations' => $todayMetrics['rate_limit_exceeded'] ?? 0,
                'file_upload_abuse' => $todayMetrics['file_upload_abuse'] ?? 0,
                'unauthorized_access' => $todayMetrics['unauthorized_access'] ?? 0,
            ],
            'yesterday' => [
                'total_events' => array_sum($yesterdayMetrics),
                'failed_logins' => $yesterdayMetrics['failed_login'] ?? 0,
                'suspicious_requests' => $yesterdayMetrics['suspicious_request'] ?? 0,
                'sql_injection_attempts' => $yesterdayMetrics['sql_injection_attempt'] ?? 0,
                'rate_limit_violations' => $yesterdayMetrics['rate_limit_exceeded'] ?? 0,
                'file_upload_abuse' => $yesterdayMetrics['file_upload_abuse'] ?? 0,
                'unauthorized_access' => $yesterdayMetrics['unauthorized_access'] ?? 0,
            ],
            'trends' => $this->calculateTrends($todayMetrics, $yesterdayMetrics)
        ];
    }

    /**
     * Get recent threats
     */
    private function getRecentThreats($limit = 10)
    {
        // This would typically query a threats table
        // For now, we'll simulate with cache data
        $threats = Cache::get('recent_threats', []);
        
        // Return sample data if no threats exist
        if (empty($threats)) {
            return [
                [
                    'timestamp' => now()->subMinutes(5)->toISOString(),
                    'type' => 'failed_login',
                    'ip' => '192.168.1.100',
                    'severity' => 'medium'
                ],
                [
                    'timestamp' => now()->subMinutes(15)->toISOString(),
                    'type' => 'suspicious_request',
                    'ip' => '10.0.0.50',
                    'severity' => 'high'
                ]
            ];
        }
        
        return array_slice($threats, 0, $limit);
    }

    /**
     * Get top threats
     */
    private function getTopThreats($limit = 5)
    {
        $today = now()->format('Y-m-d');
        $metrics = Cache::get("daily_metrics_{$today}", []);
        
        arsort($metrics);
        return array_slice($metrics, 0, $limit, true);
    }

    /**
     * Get system health status
     */
    private function getSystemHealth()
    {
        return [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'storage' => $this->checkStorageHealth(),
            'memory' => $this->checkMemoryHealth(),
            'disk_space' => $this->checkDiskSpaceHealth(),
            'security_services' => $this->checkSecurityServicesHealth()
        ];
    }

    /**
     * Get blocked IPs
     */
    private function getBlockedIPs()
    {
        $blockedIPs = [];
        
        // Get all blocked IP keys from cache
        $keys = Cache::get('blocked_ip_keys', []);
        
        foreach ($keys as $key) {
            if (Cache::has($key)) {
                $ip = str_replace('blocked_ip_', '', $key);
                $blockedIPs[] = [
                    'ip' => $ip,
                    'blocked_at' => Cache::get($key . '_timestamp', 'Unknown'),
                    'reason' => Cache::get($key . '_reason', 'Security violation')
                ];
            }
        }
        
        // Return sample data if no blocked IPs exist
        if (empty($blockedIPs)) {
            return [
                [
                    'ip' => '192.168.1.100',
                    'blocked_at' => now()->subHours(2)->toDateTimeString(),
                    'reason' => 'Multiple failed login attempts'
                ],
                [
                    'ip' => '10.0.0.50',
                    'blocked_at' => now()->subHours(5)->toDateTimeString(),
                    'reason' => 'Suspicious request patterns'
                ]
            ];
        }
        
        return $blockedIPs;
    }

    /**
     * Get security alerts
     */
    private function getSecurityAlerts($limit = 20)
    {
        // This would typically query an alerts table
        // For now, we'll simulate with cache data
        $alerts = Cache::get('security_alerts', []);
        
        // Return sample data if no alerts exist
        if (empty($alerts)) {
            return [
                [
                    'type' => 'rate_limit_exceeded',
                    'severity' => 'high',
                    'timestamp' => now()->subMinutes(10)->toISOString(),
                    'message' => 'Rate limit exceeded for IP 192.168.1.100'
                ],
                [
                    'type' => 'suspicious_request',
                    'severity' => 'medium',
                    'timestamp' => now()->subMinutes(30)->toISOString(),
                    'message' => 'Suspicious request pattern detected'
                ]
            ];
        }
        
        return array_slice($alerts, 0, $limit);
    }

    /**
     * Get threats by date range
     */
    private function getThreatsByDateRange($startDate, $endDate, $threatType)
    {
        // This would typically query a threats table
        // For now, we'll simulate with cache data
        $threats = [];
        
        $current = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($current->lte($end)) {
            $date = $current->format('Y-m-d');
            $dailyMetrics = Cache::get("daily_metrics_{$date}", []);
            
            if ($threatType === 'all' || isset($dailyMetrics[$threatType])) {
                $threats[$date] = $dailyMetrics;
            }
            
            $current->addDay();
        }
        
        return $threats;
    }

    /**
     * Get threat timeline data
     */
    private function getThreatTimeline($period)
    {
        $days = $this->getPeriodDays($period);
        $timeline = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $metrics = Cache::get("daily_metrics_{$date}", []);
            
            $timeline[] = [
                'date' => $date,
                'total_events' => array_sum($metrics),
                'threats' => $metrics
            ];
        }
        
        return $timeline;
    }

    /**
     * Get threat distribution
     */
    private function getThreatDistribution($period)
    {
        $days = $this->getPeriodDays($period);
        $distribution = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $metrics = Cache::get("daily_metrics_{$date}", []);
            
            foreach ($metrics as $threatType => $count) {
                if (!isset($distribution[$threatType])) {
                    $distribution[$threatType] = 0;
                }
                $distribution[$threatType] += $count;
            }
        }
        
        return $distribution;
    }

    /**
     * Get top attacking IPs
     */
    private function getTopAttackingIPs($period)
    {
        // This would typically query logs for IPs with most attacks
        // For now, we'll simulate with cache data
        return Cache::get('top_attacking_ips', []);
    }

    /**
     * Get security events
     */
    private function getSecurityEvents($period)
    {
        // This would typically query a security events table
        // For now, we'll simulate with cache data
        return Cache::get('security_events', []);
    }

    /**
     * Get response time analytics
     */
    private function getResponseTimeAnalytics($period)
    {
        // This would typically analyze response time data
        // For now, we'll simulate with cache data
        return Cache::get('response_time_analytics', []);
    }

    /**
     * Get file upload security data
     */
    private function getFileUploadSecurity($period)
    {
        // This would typically analyze file upload security data
        // For now, we'll simulate with cache data
        return Cache::get('file_upload_security', []);
    }

    /**
     * Calculate trends between two metric sets
     */
    private function calculateTrends($today, $yesterday)
    {
        $trends = [];
        
        foreach ($today as $key => $value) {
            $yesterdayValue = $yesterday[$key] ?? 0;
            if ($yesterdayValue > 0) {
                $trends[$key] = round((($value - $yesterdayValue) / $yesterdayValue) * 100, 2);
            } else {
                $trends[$key] = $value > 0 ? 100 : 0;
            }
        }
        
        return $trends;
    }

    /**
     * Get period days from period string
     */
    private function getPeriodDays($period)
    {
        switch ($period) {
            case '1d': return 1;
            case '7d': return 7;
            case '30d': return 30;
            case '90d': return 90;
            default: return 7;
        }
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check cache health
     */
    private function checkCacheHealth()
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $value = Cache::get('health_check');
            return ['status' => $value === 'ok' ? 'healthy' : 'unhealthy', 'message' => 'Cache system operational'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Cache system failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check storage health
     */
    private function checkStorageHealth()
    {
        try {
            $path = storage_path('app');
            if (is_writable($path)) {
                return ['status' => 'healthy', 'message' => 'Storage is writable'];
            } else {
                return ['status' => 'unhealthy', 'message' => 'Storage is not writable'];
            }
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check memory health
     */
    private function checkMemoryHealth()
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return ['status' => 'healthy', 'message' => 'Memory usage: ' . $this->formatBytes($memoryUsage)];
        }
        
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
        
        if ($usagePercent > 90) {
            return ['status' => 'critical', 'message' => "Memory usage: {$usagePercent}%"];
        } elseif ($usagePercent > 75) {
            return ['status' => 'warning', 'message' => "Memory usage: {$usagePercent}%"];
        } else {
            return ['status' => 'healthy', 'message' => "Memory usage: {$usagePercent}%"];
        }
    }

    /**
     * Check disk space health
     */
    private function checkDiskSpaceHealth()
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);
        $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        
        if ($usagePercent > 90) {
            return ['status' => 'critical', 'message' => "Disk usage: {$usagePercent}%"];
        } elseif ($usagePercent > 75) {
            return ['status' => 'warning', 'message' => "Disk usage: {$usagePercent}%"];
        } else {
            return ['status' => 'healthy', 'message' => "Disk usage: {$usagePercent}%"];
        }
    }

    /**
     * Check security services health
     */
    private function checkSecurityServicesHealth()
    {
        $services = [
            'threat_monitoring' => env('THREAT_DETECTION_ENABLED', true),
            'rate_limiting' => env('RATE_LIMIT_ENABLED', true),
            'file_upload_security' => env('FILE_UPLOAD_SCAN_MALWARE', true),
            'encryption' => env('SESSION_ENCRYPT', false),
            'audit_logging' => env('AUDIT_ENABLED', true)
        ];
        
        $healthyCount = array_sum($services);
        $totalCount = count($services);
        
        if ($healthyCount === $totalCount) {
            return ['status' => 'healthy', 'message' => 'All security services active'];
        } else {
            return ['status' => 'warning', 'message' => "{$healthyCount}/{$totalCount} security services active"];
        }
    }

    /**
     * Get logs for export
     */
    private function getLogsForExport($startDate, $endDate, $logType)
    {
        // This would typically query actual log files or database
        // For now, we'll return sample data
        return [
            'export_info' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'log_type' => $logType,
                'generated_at' => now()->toISOString()
            ],
            'logs' => []
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit)
    {
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) $memoryLimit;
        
        switch ($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
}
