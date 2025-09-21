<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ThreatMonitoringService
{
    private const THREAT_LEVELS = [
        'LOW' => 1,
        'MEDIUM' => 2,
        'HIGH' => 3,
        'CRITICAL' => 4,
    ];

    private const ALERT_THRESHOLDS = [
        'failed_logins' => 5,      // 5 failed logins in 5 minutes
        'suspicious_requests' => 10, // 10 suspicious requests in 10 minutes
        'rate_limit_exceeded' => 3,  // 3 rate limit violations in 1 minute
        'sql_injection_attempts' => 1, // Any SQL injection attempt
        'file_upload_abuse' => 5,   // 5 failed uploads in 5 minutes
    ];

    /**
     * Monitor and analyze security events in real-time
     */
    public function monitorEvent(string $eventType, array $data = []): void
    {
        $threatLevel = $this->analyzeThreatLevel($eventType, $data);
        
        if ($threatLevel >= self::THREAT_LEVELS['MEDIUM']) {
            $this->logThreatEvent($eventType, $data, $threatLevel);
        }
        
        if ($threatLevel >= self::THREAT_LEVELS['HIGH']) {
            $this->triggerSecurityAlert($eventType, $data, $threatLevel);
        }
        
        if ($threatLevel >= self::THREAT_LEVELS['CRITICAL']) {
            $this->triggerCriticalAlert($eventType, $data, $threatLevel);
        }
        
        $this->updateThreatMetrics($eventType, $data);
    }

    /**
     * Analyze threat level based on event type and data
     */
    private function analyzeThreatLevel(string $eventType, array $data): int
    {
        $threatLevel = self::THREAT_LEVELS['LOW'];
        
        switch ($eventType) {
            case 'failed_login':
                $threatLevel = $this->analyzeFailedLoginThreat($data);
                break;
                
            case 'suspicious_request':
                $threatLevel = $this->analyzeSuspiciousRequestThreat($data);
                break;
                
            case 'sql_injection_attempt':
                $threatLevel = self::THREAT_LEVELS['CRITICAL'];
                break;
                
            case 'rate_limit_exceeded':
                $threatLevel = $this->analyzeRateLimitThreat($data);
                break;
                
            case 'file_upload_abuse':
                $threatLevel = $this->analyzeFileUploadThreat($data);
                break;
                
            case 'unauthorized_access':
                $threatLevel = self::THREAT_LEVELS['HIGH'];
                break;
                
            case 'data_exfiltration_attempt':
                $threatLevel = self::THREAT_LEVELS['CRITICAL'];
                break;
        }
        
        return $threatLevel;
    }

    /**
     * Analyze failed login threat level
     */
    private function analyzeFailedLoginThreat(array $data): int
    {
        $ip = $data['ip'] ?? 'unknown';
        $userId = $data['user_id'] ?? null;
        
        // Check recent failed logins for this IP
        $recentFailures = $this->getRecentEventCount('failed_login', $ip, 5); // 5 minutes
        
        if ($recentFailures >= self::ALERT_THRESHOLDS['failed_logins']) {
            return self::THREAT_LEVELS['HIGH'];
        }
        
        if ($recentFailures >= 3) {
            return self::THREAT_LEVELS['MEDIUM'];
        }
        
        return self::THREAT_LEVELS['LOW'];
    }

    /**
     * Analyze suspicious request threat level
     */
    private function analyzeSuspiciousRequestThreat(array $data): int
    {
        $ip = $data['ip'] ?? 'unknown';
        $pattern = $data['pattern'] ?? '';
        
        // Check for critical patterns
        $criticalPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
        ];
        
        foreach ($criticalPatterns as $criticalPattern) {
            if (preg_match($criticalPattern, $pattern)) {
                return self::THREAT_LEVELS['CRITICAL'];
            }
        }
        
        // Check recent suspicious requests
        $recentSuspicious = $this->getRecentEventCount('suspicious_request', $ip, 10); // 10 minutes
        
        if ($recentSuspicious >= self::ALERT_THRESHOLDS['suspicious_requests']) {
            return self::THREAT_LEVELS['HIGH'];
        }
        
        if ($recentSuspicious >= 5) {
            return self::THREAT_LEVELS['MEDIUM'];
        }
        
        return self::THREAT_LEVELS['LOW'];
    }

    /**
     * Analyze rate limit threat level
     */
    private function analyzeRateLimitThreat(array $data): int
    {
        $ip = $data['ip'] ?? 'unknown';
        $endpoint = $data['endpoint'] ?? '';
        
        // Check recent rate limit violations
        $recentViolations = $this->getRecentEventCount('rate_limit_exceeded', $ip, 1); // 1 minute
        
        if ($recentViolations >= self::ALERT_THRESHOLDS['rate_limit_exceeded']) {
            return self::THREAT_LEVELS['HIGH'];
        }
        
        // Check if it's a sensitive endpoint
        $sensitiveEndpoints = ['login', 'admin', 'api', 'upload'];
        foreach ($sensitiveEndpoints as $sensitive) {
            if (strpos($endpoint, $sensitive) !== false) {
                return self::THREAT_LEVELS['MEDIUM'];
            }
        }
        
        return self::THREAT_LEVELS['LOW'];
    }

    /**
     * Analyze file upload threat level
     */
    private function analyzeFileUploadThreat(array $data): int
    {
        $ip = $data['ip'] ?? 'unknown';
        $userId = $data['user_id'] ?? null;
        
        // Check recent failed uploads
        $recentFailures = $this->getRecentEventCount('file_upload_abuse', $ip, 5); // 5 minutes
        
        if ($recentFailures >= self::ALERT_THRESHOLDS['file_upload_abuse']) {
            return self::THREAT_LEVELS['HIGH'];
        }
        
        if ($recentFailures >= 3) {
            return self::THREAT_LEVELS['MEDIUM'];
        }
        
        return self::THREAT_LEVELS['LOW'];
    }

    /**
     * Log threat event
     */
    private function logThreatEvent(string $eventType, array $data, int $threatLevel): void
    {
        $threatLevelName = array_search($threatLevel, self::THREAT_LEVELS);
        
        Log::warning('Security Threat Detected', [
            'event_type' => $eventType,
            'threat_level' => $threatLevelName,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ]);
    }

    /**
     * Trigger security alert
     */
    private function triggerSecurityAlert(string $eventType, array $data, int $threatLevel): void
    {
        $threatLevelName = array_search($threatLevel, self::THREAT_LEVELS);
        
        // Log critical security event
        Log::critical('Security Alert Triggered', [
            'event_type' => $eventType,
            'threat_level' => $threatLevelName,
            'timestamp' => now()->toISOString(),
            'data' => $data,
        ]);
        
        // Send email alert to security team
        $this->sendSecurityAlert($eventType, $data, $threatLevelName);
        
        // Update threat metrics
        $this->updateThreatMetrics($eventType, $data);
    }

    /**
     * Trigger critical alert
     */
    private function triggerCriticalAlert(string $eventType, array $data, int $threatLevel): void
    {
        $threatLevelName = array_search($threatLevel, self::THREAT_LEVELS);
        
        // Log critical security event
        Log::critical('CRITICAL Security Alert', [
            'event_type' => $eventType,
            'threat_level' => $threatLevelName,
            'timestamp' => now()->toISOString(),
            'data' => $data,
            'action_required' => 'IMMEDIATE',
        ]);
        
        // Send immediate alert
        $this->sendCriticalAlert($eventType, $data, $threatLevelName);
        
        // Consider automatic response actions
        $this->considerAutomaticResponse($eventType, $data);
    }

    /**
     * Send security alert email
     */
    private function sendSecurityAlert(string $eventType, array $data, string $threatLevel): void
    {
        try {
            $securityEmails = explode(',', env('SECURITY_ALERT_EMAILS', ''));
            
            if (!empty($securityEmails)) {
                Mail::raw(
                    "Security Alert: {$eventType}\n" .
                    "Threat Level: {$threatLevel}\n" .
                    "Time: " . now()->toDateTimeString() . "\n" .
                    "IP: " . ($data['ip'] ?? 'unknown') . "\n" .
                    "User ID: " . ($data['user_id'] ?? 'unknown') . "\n" .
                    "Details: " . json_encode($data),
                    function ($message) use ($securityEmails, $eventType, $threatLevel) {
                        $message->to($securityEmails)
                            ->subject("Security Alert: {$eventType} - {$threatLevel} Threat Level");
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send security alert email', [
                'error' => $e->getMessage(),
                'event_type' => $eventType
            ]);
        }
    }

    /**
     * Send critical alert
     */
    private function sendCriticalAlert(string $eventType, array $data, string $threatLevel): void
    {
        try {
            $criticalEmails = explode(',', env('CRITICAL_ALERT_EMAILS', ''));
            
            if (!empty($criticalEmails)) {
                Mail::raw(
                    "CRITICAL SECURITY ALERT\n" .
                    "Event: {$eventType}\n" .
                    "Threat Level: {$threatLevel}\n" .
                    "Time: " . now()->toDateTimeString() . "\n" .
                    "IP: " . ($data['ip'] ?? 'unknown') . "\n" .
                    "User ID: " . ($data['user_id'] ?? 'unknown') . "\n" .
                    "Details: " . json_encode($data) . "\n\n" .
                    "IMMEDIATE ACTION REQUIRED",
                    function ($message) use ($criticalEmails, $eventType) {
                        $message->to($criticalEmails)
                            ->subject("CRITICAL: {$eventType} - IMMEDIATE ACTION REQUIRED");
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send critical alert email', [
                'error' => $e->getMessage(),
                'event_type' => $eventType
            ]);
        }
    }

    /**
     * Consider automatic response actions
     */
    private function considerAutomaticResponse(string $eventType, array $data): void
    {
        $ip = $data['ip'] ?? 'unknown';
        
        switch ($eventType) {
            case 'sql_injection_attempt':
            case 'data_exfiltration_attempt':
                // Block IP temporarily
                $this->blockIP($ip, 3600); // 1 hour
                break;
                
            case 'failed_login':
                $recentFailures = $this->getRecentEventCount('failed_login', $ip, 5);
                if ($recentFailures >= 10) {
                    // Block IP for 30 minutes
                    $this->blockIP($ip, 1800);
                }
                break;
        }
    }

    /**
     * Block IP address
     */
    private function blockIP(string $ip, int $seconds): void
    {
        $blockKey = 'blocked_ip_' . $ip;
        Cache::put($blockKey, true, $seconds);
        
        Log::warning('IP Address Blocked', [
            'ip' => $ip,
            'duration_seconds' => $seconds,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Check if IP is blocked
     */
    public function isIPBlocked(string $ip): bool
    {
        $blockKey = 'blocked_ip_' . $ip;
        return Cache::has($blockKey);
    }

    /**
     * Get recent event count
     */
    private function getRecentEventCount(string $eventType, string $identifier, int $minutes): int
    {
        $cacheKey = "event_count_{$eventType}_{$identifier}";
        $events = Cache::get($cacheKey, []);
        
        $cutoff = now()->subMinutes($minutes);
        $recentEvents = array_filter($events, function ($timestamp) use ($cutoff) {
            return Carbon::parse($timestamp)->isAfter($cutoff);
        });
        
        return count($recentEvents);
    }

    /**
     * Update threat metrics
     */
    private function updateThreatMetrics(string $eventType, array $data): void
    {
        $ip = $data['ip'] ?? 'unknown';
        $userId = $data['user_id'] ?? null;
        
        // Update event count
        $cacheKey = "event_count_{$eventType}_{$ip}";
        $events = Cache::get($cacheKey, []);
        $events[] = now()->toISOString();
        
        // Keep only last 100 events
        if (count($events) > 100) {
            $events = array_slice($events, -100);
        }
        
        Cache::put($cacheKey, $events, 3600); // 1 hour
        
        // Update daily metrics
        $dailyKey = "daily_metrics_" . now()->format('Y-m-d');
        $metrics = Cache::get($dailyKey, []);
        
        if (!isset($metrics[$eventType])) {
            $metrics[$eventType] = 0;
        }
        $metrics[$eventType]++;
        
        Cache::put($dailyKey, $metrics, 86400); // 24 hours
    }

    /**
     * Get threat statistics
     */
    public function getThreatStatistics(): array
    {
        $today = now()->format('Y-m-d');
        $dailyKey = "daily_metrics_{$today}";
        $metrics = Cache::get($dailyKey, []);
        
        return [
            'date' => $today,
            'metrics' => $metrics,
            'total_events' => array_sum($metrics),
        ];
    }
}
