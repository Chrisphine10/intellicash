<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class RealTimeSecurityMonitoring
{
    private const MONITORING_INTERVAL = 30; // seconds
    private const ALERT_THRESHOLDS = [
        'failed_logins_per_minute' => 10,
        'suspicious_requests_per_minute' => 20,
        'sql_injection_attempts_per_hour' => 5,
        'rate_limit_violations_per_minute' => 15,
        'file_upload_abuse_per_hour' => 10,
    ];

    private const CRITICAL_PATTERNS = [
        'sql_injection' => [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
        ],
        'xss_attempts' => [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
        ],
        'path_traversal' => [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e%5c/i',
        ],
        'command_injection' => [
            '/;\s*rm\s+/i',
            '/;\s*cat\s+/i',
            '/;\s*ls\s+/i',
            '/;\s*whoami/i',
            '/\|\s*nc\s+/i',
            '/\|\s*wget\s+/i',
        ]
    ];

    /**
     * Start real-time monitoring
     */
    public function startMonitoring(): void
    {
        Log::info('Starting real-time security monitoring');
        
        // Schedule monitoring tasks
        $this->scheduleMonitoringTasks();
        
        // Start background monitoring
        $this->startBackgroundMonitoring();
    }

    /**
     * Monitor security event in real-time
     */
    public function monitorEvent(string $eventType, array $data = []): void
    {
        $timestamp = now();
        $ip = $data['ip'] ?? 'unknown';
        $userId = $data['user_id'] ?? null;
        
        // Store event for analysis
        $this->storeSecurityEvent($eventType, $data, $timestamp);
        
        // Check for immediate threats
        $this->checkImmediateThreats($eventType, $data, $timestamp);
        
        // Update real-time metrics
        $this->updateRealTimeMetrics($eventType, $ip, $timestamp);
        
        // Check for pattern-based threats
        $this->checkPatternBasedThreats($eventType, $data, $timestamp);
        
        // Update threat intelligence
        $this->updateThreatIntelligence($ip, $eventType, $timestamp);
    }

    /**
     * Get real-time security status
     */
    public function getRealTimeStatus(): array
    {
        return [
            'monitoring_active' => $this->isMonitoringActive(),
            'current_threats' => $this->getCurrentThreats(),
            'blocked_ips' => $this->getCurrentlyBlockedIPs(),
            'system_health' => $this->getSystemHealthStatus(),
            'recent_events' => $this->getRecentEvents(10),
            'threat_level' => $this->getCurrentThreatLevel(),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Get security metrics for dashboard
     */
    public function getSecurityMetrics(): array
    {
        $now = now();
        $lastHour = $now->copy()->subHour();
        $last24Hours = $now->copy()->subDay();
        
        return [
            'last_hour' => $this->getMetricsForPeriod($lastHour, $now),
            'last_24_hours' => $this->getMetricsForPeriod($last24Hours, $now),
            'real_time' => $this->getRealTimeMetrics(),
            'trends' => $this->getSecurityTrends(),
            'top_threats' => $this->getTopThreats(),
            'geographic_threats' => $this->getGeographicThreats()
        ];
    }

    /**
     * Get threat analytics
     */
    public function getThreatAnalytics(string $period = '24h'): array
    {
        $endTime = now();
        $startTime = $this->getStartTimeForPeriod($period, $endTime);
        
        return [
            'timeline' => $this->getThreatTimeline($startTime, $endTime),
            'distribution' => $this->getThreatDistribution($startTime, $endTime),
            'top_ips' => $this->getTopThreateningIPs($startTime, $endTime),
            'attack_vectors' => $this->getAttackVectors($startTime, $endTime),
            'response_times' => $this->getResponseTimeAnalytics($startTime, $endTime),
            'success_rate' => $this->getSecuritySuccessRate($startTime, $endTime)
        ];
    }

    /**
     * Store security event
     */
    private function storeSecurityEvent(string $eventType, array $data, Carbon $timestamp): void
    {
        $event = [
            'event_type' => $eventType,
            'data' => $data,
            'timestamp' => $timestamp->toISOString(),
            'ip' => $data['ip'] ?? 'unknown',
            'user_id' => $data['user_id'] ?? null,
            'severity' => $this->calculateEventSeverity($eventType, $data),
            'threat_level' => $this->calculateThreatLevel($eventType, $data)
        ];
        
        // Store in cache for real-time access
        $cacheKey = 'security_event_' . $timestamp->format('Y-m-d_H-i-s') . '_' . uniqid();
        Cache::put($cacheKey, $event, 3600); // 1 hour
        
        // Store in recent events list
        $recentEvents = Cache::get('recent_security_events', []);
        array_unshift($recentEvents, $event);
        $recentEvents = array_slice($recentEvents, 0, 100); // Keep last 100 events
        Cache::put('recent_security_events', $recentEvents, 3600);
        
        // Log to file
        Log::info('Security event recorded', $event);
    }

    /**
     * Check for immediate threats
     */
    private function checkImmediateThreats(string $eventType, array $data, Carbon $timestamp): void
    {
        $ip = $data['ip'] ?? 'unknown';
        
        // Check rate limits
        if ($this->isRateLimitExceeded($ip, $eventType)) {
            $this->triggerImmediateAlert('rate_limit_exceeded', [
                'ip' => $ip,
                'event_type' => $eventType,
                'timestamp' => $timestamp->toISOString()
            ]);
        }
        
        // Check for critical patterns
        if ($this->hasCriticalPattern($data)) {
            $this->triggerImmediateAlert('critical_pattern_detected', [
                'ip' => $ip,
                'event_type' => $eventType,
                'pattern' => $this->getDetectedPattern($data),
                'timestamp' => $timestamp->toISOString()
            ]);
        }
        
        // Check for brute force attacks
        if ($this->isBruteForceAttack($ip, $eventType)) {
            $this->triggerImmediateAlert('brute_force_attack', [
                'ip' => $ip,
                'event_type' => $eventType,
                'timestamp' => $timestamp->toISOString()
            ]);
        }
    }

    /**
     * Update real-time metrics
     */
    private function updateRealTimeMetrics(string $eventType, string $ip, Carbon $timestamp): void
    {
        $minuteKey = 'metrics_' . $timestamp->format('Y-m-d_H-i');
        $hourKey = 'metrics_' . $timestamp->format('Y-m-d_H');
        $dayKey = 'metrics_' . $timestamp->format('Y-m-d');
        
        // Update minute metrics
        $minuteMetrics = Cache::get($minuteKey, []);
        $minuteMetrics[$eventType] = ($minuteMetrics[$eventType] ?? 0) + 1;
        $minuteMetrics['total'] = ($minuteMetrics['total'] ?? 0) + 1;
        Cache::put($minuteKey, $minuteMetrics, 3600);
        
        // Update hour metrics
        $hourMetrics = Cache::get($hourKey, []);
        $hourMetrics[$eventType] = ($hourMetrics[$eventType] ?? 0) + 1;
        $hourMetrics['total'] = ($hourMetrics['total'] ?? 0) + 1;
        Cache::put($hourKey, $hourMetrics, 86400);
        
        // Update day metrics
        $dayMetrics = Cache::get($dayKey, []);
        $dayMetrics[$eventType] = ($dayMetrics[$eventType] ?? 0) + 1;
        $dayMetrics['total'] = ($dayMetrics['total'] ?? 0) + 1;
        Cache::put($dayKey, $dayMetrics, 2592000); // 30 days
        
        // Update IP-specific metrics
        $ipKey = 'ip_metrics_' . $ip . '_' . $timestamp->format('Y-m-d');
        $ipMetrics = Cache::get($ipKey, []);
        $ipMetrics[$eventType] = ($ipMetrics[$eventType] ?? 0) + 1;
        $ipMetrics['total'] = ($ipMetrics['total'] ?? 0) + 1;
        $ipMetrics['last_activity'] = $timestamp->toISOString();
        Cache::put($ipKey, $ipMetrics, 86400);
    }

    /**
     * Check for pattern-based threats
     */
    private function checkPatternBasedThreats(string $eventType, array $data, Carbon $timestamp): void
    {
        $input = json_encode($data);
        
        foreach (self::CRITICAL_PATTERNS as $patternType => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    $this->triggerPatternAlert($patternType, $pattern, $data, $timestamp);
                }
            }
        }
    }

    /**
     * Update threat intelligence
     */
    private function updateThreatIntelligence(string $ip, string $eventType, Carbon $timestamp): void
    {
        $intelligenceKey = 'threat_intel_' . $ip;
        $intelligence = Cache::get($intelligenceKey, [
            'ip' => $ip,
            'first_seen' => $timestamp->toISOString(),
            'last_seen' => $timestamp->toISOString(),
            'event_count' => 0,
            'event_types' => [],
            'threat_level' => 'low',
            'risk_score' => 0
        ]);
        
        $intelligence['last_seen'] = $timestamp->toISOString();
        $intelligence['event_count']++;
        $intelligence['event_types'][$eventType] = ($intelligence['event_types'][$eventType] ?? 0) + 1;
        $intelligence['risk_score'] = $this->calculateRiskScore($intelligence);
        $intelligence['threat_level'] = $this->calculateThreatLevelFromRiskScore($intelligence['risk_score']);
        
        Cache::put($intelligenceKey, $intelligence, 86400 * 7); // 7 days
    }

    /**
     * Schedule monitoring tasks
     */
    private function scheduleMonitoringTasks(): void
    {
        // This would typically use Laravel's task scheduler
        // For now, we'll use cache-based scheduling
        
        Cache::put('monitoring_tasks', [
            'threat_analysis' => now()->addMinutes(5),
            'ip_reputation_check' => now()->addMinutes(10),
            'pattern_analysis' => now()->addMinutes(15),
            'metrics_cleanup' => now()->addHour(),
        ], 3600);
    }

    /**
     * Start background monitoring
     */
    private function startBackgroundMonitoring(): void
    {
        // This would typically be handled by a queue worker
        // For now, we'll simulate with cache-based monitoring
        
        Cache::put('monitoring_active', true, 3600);
        Cache::put('monitoring_started', now()->toISOString(), 3600);
    }

    /**
     * Check if monitoring is active
     */
    private function isMonitoringActive(): bool
    {
        return Cache::get('monitoring_active', false);
    }

    /**
     * Get current threats
     */
    private function getCurrentThreats(): array
    {
        $threats = Cache::get('current_threats', []);
        return array_slice($threats, 0, 10); // Last 10 threats
    }

    /**
     * Get currently blocked IPs
     */
    private function getCurrentlyBlockedIPs(): array
    {
        $blockedIPs = [];
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
        
        return $blockedIPs;
    }

    /**
     * Get system health status
     */
    private function getSystemHealthStatus(): array
    {
        return [
            'monitoring' => $this->isMonitoringActive() ? 'active' : 'inactive',
            'cache' => $this->checkCacheHealth(),
            'database' => $this->checkDatabaseHealth(),
            'memory' => $this->checkMemoryHealth(),
            'disk_space' => $this->checkDiskSpaceHealth()
        ];
    }

    /**
     * Get recent events
     */
    private function getRecentEvents(int $limit = 10): array
    {
        $events = Cache::get('recent_security_events', []);
        return array_slice($events, 0, $limit);
    }

    /**
     * Get current threat level
     */
    private function getCurrentThreatLevel(): string
    {
        $recentEvents = $this->getRecentEvents(50);
        $highSeverityCount = 0;
        
        foreach ($recentEvents as $event) {
            if (($event['severity'] ?? 'low') === 'high') {
                $highSeverityCount++;
            }
        }
        
        if ($highSeverityCount >= 10) {
            return 'critical';
        } elseif ($highSeverityCount >= 5) {
            return 'high';
        } elseif ($highSeverityCount >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate event severity
     */
    private function calculateEventSeverity(string $eventType, array $data): string
    {
        $criticalEvents = ['sql_injection_attempt', 'data_exfiltration_attempt', 'unauthorized_access'];
        $highEvents = ['failed_login', 'suspicious_request', 'rate_limit_exceeded'];
        
        if (in_array($eventType, $criticalEvents)) {
            return 'critical';
        } elseif (in_array($eventType, $highEvents)) {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Calculate threat level
     */
    private function calculateThreatLevel(string $eventType, array $data): string
    {
        $severity = $this->calculateEventSeverity($eventType, $data);
        
        if ($severity === 'critical') {
            return 'critical';
        } elseif ($severity === 'high') {
            return 'high';
        } else {
            return 'medium';
        }
    }

    /**
     * Check if rate limit is exceeded
     */
    private function isRateLimitExceeded(string $ip, string $eventType): bool
    {
        $minuteKey = 'rate_limit_' . $ip . '_' . now()->format('Y-m-d_H-i');
        $count = Cache::get($minuteKey, 0);
        
        $threshold = self::ALERT_THRESHOLDS['rate_limit_violations_per_minute'] ?? 15;
        
        if ($count >= $threshold) {
            return true;
        }
        
        Cache::increment($minuteKey);
        Cache::expire($minuteKey, 60); // 1 minute
        
        return false;
    }

    /**
     * Check for critical patterns
     */
    private function hasCriticalPattern(array $data): bool
    {
        $input = json_encode($data);
        
        foreach (self::CRITICAL_PATTERNS as $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Get detected pattern
     */
    private function getDetectedPattern(array $data): string
    {
        $input = json_encode($data);
        
        foreach (self::CRITICAL_PATTERNS as $patternType => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    return $patternType . ': ' . $pattern;
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * Check for brute force attack
     */
    private function isBruteForceAttack(string $ip, string $eventType): bool
    {
        if ($eventType !== 'failed_login') {
            return false;
        }
        
        $hourKey = 'brute_force_' . $ip . '_' . now()->format('Y-m-d_H');
        $count = Cache::get($hourKey, 0);
        
        $threshold = self::ALERT_THRESHOLDS['failed_logins_per_minute'] ?? 10;
        
        if ($count >= $threshold) {
            return true;
        }
        
        Cache::increment($hourKey);
        Cache::expire($hourKey, 3600); // 1 hour
        
        return false;
    }

    /**
     * Trigger immediate alert
     */
    private function triggerImmediateAlert(string $alertType, array $data): void
    {
        $alert = [
            'type' => $alertType,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'severity' => 'high'
        ];
        
        // Store alert
        $alerts = Cache::get('security_alerts', []);
        array_unshift($alerts, $alert);
        $alerts = array_slice($alerts, 0, 100); // Keep last 100 alerts
        Cache::put('security_alerts', $alerts, 3600);
        
        // Log alert
        Log::critical('Security alert triggered', $alert);
        
        // Send immediate notification
        $this->sendImmediateNotification($alert);
    }

    /**
     * Trigger pattern alert
     */
    private function triggerPatternAlert(string $patternType, string $pattern, array $data, Carbon $timestamp): void
    {
        $alert = [
            'type' => 'pattern_detected',
            'pattern_type' => $patternType,
            'pattern' => $pattern,
            'data' => $data,
            'timestamp' => $timestamp->toISOString(),
            'severity' => 'critical'
        ];
        
        // Store alert
        $alerts = Cache::get('security_alerts', []);
        array_unshift($alerts, $alert);
        $alerts = array_slice($alerts, 0, 100);
        Cache::put('security_alerts', $alerts, 3600);
        
        // Log alert
        Log::critical('Security pattern detected', $alert);
        
        // Send immediate notification
        $this->sendImmediateNotification($alert);
    }

    /**
     * Send immediate notification
     */
    private function sendImmediateNotification(array $alert): void
    {
        try {
            $securityEmails = explode(',', env('SECURITY_ALERT_EMAILS', ''));
            
            if (!empty($securityEmails)) {
                Mail::raw(
                    "IMMEDIATE SECURITY ALERT\n\n" .
                    "Type: {$alert['type']}\n" .
                    "Severity: {$alert['severity']}\n" .
                    "Time: {$alert['timestamp']}\n" .
                    "Details: " . json_encode($alert['data'], JSON_PRETTY_PRINT),
                    function ($message) use ($securityEmails, $alert) {
                        $message->to($securityEmails)
                            ->subject("CRITICAL: Security Alert - {$alert['type']}");
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send security notification', [
                'error' => $e->getMessage(),
                'alert' => $alert
            ]);
        }
    }

    /**
     * Calculate risk score
     */
    private function calculateRiskScore(array $intelligence): int
    {
        $score = 0;
        
        // Base score from event count
        $score += min($intelligence['event_count'] * 2, 50);
        
        // Bonus for multiple event types
        $score += count($intelligence['event_types']) * 5;
        
        // Time-based scoring
        $lastSeen = Carbon::parse($intelligence['last_seen']);
        $hoursSinceLastSeen = now()->diffInHours($lastSeen);
        
        if ($hoursSinceLastSeen < 1) {
            $score += 20; // Recent activity
        } elseif ($hoursSinceLastSeen < 24) {
            $score += 10; // Recent activity
        }
        
        return min($score, 100);
    }

    /**
     * Calculate threat level from risk score
     */
    private function calculateThreatLevelFromRiskScore(int $riskScore): string
    {
        if ($riskScore >= 80) {
            return 'critical';
        } elseif ($riskScore >= 60) {
            return 'high';
        } elseif ($riskScore >= 40) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Get metrics for period
     */
    private function getMetricsForPeriod(Carbon $startTime, Carbon $endTime): array
    {
        $metrics = [];
        $current = $startTime->copy();
        
        while ($current->lt($endTime)) {
            $key = 'metrics_' . $current->format('Y-m-d_H');
            $hourMetrics = Cache::get($key, []);
            
            foreach ($hourMetrics as $eventType => $count) {
                if (!isset($metrics[$eventType])) {
                    $metrics[$eventType] = 0;
                }
                $metrics[$eventType] += $count;
            }
            
            $current->addHour();
        }
        
        return $metrics;
    }

    /**
     * Get real-time metrics
     */
    private function getRealTimeMetrics(): array
    {
        $now = now();
        $minuteKey = 'metrics_' . $now->format('Y-m-d_H-i');
        
        return Cache::get($minuteKey, []);
    }

    /**
     * Get security trends
     */
    private function getSecurityTrends(): array
    {
        $today = now()->format('Y-m-d');
        $yesterday = now()->subDay()->format('Y-m-d');
        
        $todayMetrics = Cache::get("metrics_{$today}", []);
        $yesterdayMetrics = Cache::get("metrics_{$yesterday}", []);
        
        $trends = [];
        
        foreach ($todayMetrics as $eventType => $count) {
            $yesterdayCount = $yesterdayMetrics[$eventType] ?? 0;
            if ($yesterdayCount > 0) {
                $trends[$eventType] = round((($count - $yesterdayCount) / $yesterdayCount) * 100, 2);
            } else {
                $trends[$eventType] = $count > 0 ? 100 : 0;
            }
        }
        
        return $trends;
    }

    /**
     * Get top threats
     */
    private function getTopThreats(): array
    {
        $today = now()->format('Y-m-d');
        $metrics = Cache::get("metrics_{$today}", []);
        
        arsort($metrics);
        return array_slice($metrics, 0, 5, true);
    }

    /**
     * Get geographic threats
     */
    private function getGeographicThreats(): array
    {
        // This would typically use GeoIP data
        // For now, we'll return sample data
        return Cache::get('geographic_threats', []);
    }

    /**
     * Get start time for period
     */
    private function getStartTimeForPeriod(string $period, Carbon $endTime): Carbon
    {
        switch ($period) {
            case '1h': return $endTime->copy()->subHour();
            case '24h': return $endTime->copy()->subDay();
            case '7d': return $endTime->copy()->subWeek();
            case '30d': return $endTime->copy()->subMonth();
            default: return $endTime->copy()->subDay();
        }
    }

    /**
     * Get threat timeline
     */
    private function getThreatTimeline(Carbon $startTime, Carbon $endTime): array
    {
        $timeline = [];
        $current = $startTime->copy();
        
        while ($current->lt($endTime)) {
            $key = 'metrics_' . $current->format('Y-m-d_H');
            $metrics = Cache::get($key, []);
            
            $timeline[] = [
                'timestamp' => $current->toISOString(),
                'total_events' => array_sum($metrics),
                'metrics' => $metrics
            ];
            
            $current->addHour();
        }
        
        return $timeline;
    }

    /**
     * Get threat distribution
     */
    private function getThreatDistribution(Carbon $startTime, Carbon $endTime): array
    {
        $distribution = [];
        $current = $startTime->copy();
        
        while ($current->lt($endTime)) {
            $key = 'metrics_' . $current->format('Y-m-d_H');
            $metrics = Cache::get($key, []);
            
            foreach ($metrics as $eventType => $count) {
                if (!isset($distribution[$eventType])) {
                    $distribution[$eventType] = 0;
                }
                $distribution[$eventType] += $count;
            }
            
            $current->addHour();
        }
        
        return $distribution;
    }

    /**
     * Get top threatening IPs
     */
    private function getTopThreateningIPs(Carbon $startTime, Carbon $endTime): array
    {
        $ipScores = [];
        $current = $startTime->copy();
        
        while ($current->lt($endTime)) {
            $date = $current->format('Y-m-d');
            $keys = Cache::get('ip_keys_' . $date, []);
            
            foreach ($keys as $key) {
                $ip = str_replace('ip_metrics_', '', explode('_', $key)[0]);
                $metrics = Cache::get($key, []);
                
                if (!isset($ipScores[$ip])) {
                    $ipScores[$ip] = 0;
                }
                $ipScores[$ip] += $metrics['total'] ?? 0;
            }
            
            $current->addDay();
        }
        
        arsort($ipScores);
        return array_slice($ipScores, 0, 10, true);
    }

    /**
     * Get attack vectors
     */
    private function getAttackVectors(Carbon $startTime, Carbon $endTime): array
    {
        // This would typically analyze attack patterns
        // For now, we'll return sample data
        return Cache::get('attack_vectors', []);
    }

    /**
     * Get response time analytics
     */
    private function getResponseTimeAnalytics(Carbon $startTime, Carbon $endTime): array
    {
        // This would typically analyze response times
        // For now, we'll return sample data
        return Cache::get('response_time_analytics', []);
    }

    /**
     * Get security success rate
     */
    private function getSecuritySuccessRate(Carbon $startTime, Carbon $endTime): float
    {
        $totalEvents = 0;
        $blockedEvents = 0;
        $current = $startTime->copy();
        
        while ($current->lt($endTime)) {
            $key = 'metrics_' . $current->format('Y-m-d_H');
            $metrics = Cache::get($key, []);
            
            $totalEvents += array_sum($metrics);
            $blockedEvents += $metrics['blocked'] ?? 0;
            
            $current->addHour();
        }
        
        if ($totalEvents === 0) {
            return 100.0;
        }
        
        return round(($blockedEvents / $totalEvents) * 100, 2);
    }

    /**
     * Check cache health
     */
    private function checkCacheHealth(): string
    {
        try {
            Cache::put('health_check', 'ok', 60);
            $value = Cache::get('health_check');
            return $value === 'ok' ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth(): string
    {
        try {
            \DB::connection()->getPdo();
            return 'healthy';
        } catch (\Exception $e) {
            return 'unhealthy';
        }
    }

    /**
     * Check memory health
     */
    private function checkMemoryHealth(): string
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 'healthy';
        }
        
        $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
        $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
        
        if ($usagePercent > 90) {
            return 'critical';
        } elseif ($usagePercent > 75) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Check disk space health
     */
    private function checkDiskSpaceHealth(): string
    {
        $path = storage_path();
        $freeBytes = disk_free_space($path);
        $totalBytes = disk_total_space($path);
        $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        
        if ($usagePercent > 90) {
            return 'critical';
        } elseif ($usagePercent > 75) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit): int
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
