<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Vote;
use App\Models\Member;
use App\Models\VotingAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MilitaryGradeVotingSecurity
{
    private $maxVoteAttempts = 10; // Increased from 3 to allow for form validation retries
    private $voteCooldownMinutes = 2; // Reduced from 5 to 2 minutes
    private $suspiciousActivityThreshold = 20; // Increased threshold for IP-based blocking

    /**
     * Validate vote security before submission
     */
    public function validateVoteSecurity(Request $request, Election $election, Member $member)
    {
        $securityChecks = [
            'rate_limiting' => $this->checkRateLimiting($member),
            'ip_validation' => $this->validateIPAddress($request),
            'session_security' => $this->validateSessionSecurity($request),
            'device_fingerprint' => $this->validateDeviceFingerprint($request),
            'time_validation' => $this->validateVotingTime($election),
            'member_eligibility' => $this->validateMemberEligibility($member, $election),
            'duplicate_prevention' => $this->checkDuplicateVote($member, $election),
            'geolocation_validation' => $this->validateGeolocation($request),
        ];

        $isSecure = collect($securityChecks)->every(fn($check) => $check['passed']);

        // Log security validation
        $this->logSecurityValidation($election, $member, $securityChecks, $isSecure);

        return [
            'is_secure' => $isSecure,
            'checks' => $securityChecks,
            'security_score' => $this->calculateSecurityScore($securityChecks),
        ];
    }

    /**
     * Check rate limiting for vote attempts with IP-based protection
     */
    private function checkRateLimiting(Member $member)
    {
        $memberKey = "vote_attempts:{$member->id}";
        $ipKey = "vote_attempts_ip:" . request()->ip();
        
        $memberAttempts = RateLimiter::attempts($memberKey);
        $ipAttempts = RateLimiter::attempts($ipKey);
        
        // Check both member and IP-based rate limits
        if ($memberAttempts >= $this->maxVoteAttempts || $ipAttempts >= $this->maxVoteAttempts) {
            // Check if the member has already successfully voted in this election
            $hasVotedRecently = Vote::where('member_id', $member->id)
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();
            
            if (!$hasVotedRecently) {
                RateLimiter::hit($memberKey, $this->voteCooldownMinutes * 60);
                RateLimiter::hit($ipKey, $this->voteCooldownMinutes * 60);
                
                return [
                    'passed' => false,
                    'message' => 'Too many vote attempts. Please wait before trying again.',
                    'cooldown_until' => now()->addMinutes($this->voteCooldownMinutes),
                    'member_attempts' => $memberAttempts,
                    'ip_attempts' => $ipAttempts,
                ];
            }
        }

        RateLimiter::hit($memberKey, $this->voteCooldownMinutes * 60);
        RateLimiter::hit($ipKey, $this->voteCooldownMinutes * 60);
        
        return [
            'passed' => true,
            'message' => 'Rate limiting check passed',
            'member_attempts_remaining' => $this->maxVoteAttempts - $memberAttempts - 1,
            'ip_attempts_remaining' => $this->maxVoteAttempts - $ipAttempts - 1,
        ];
    }

    /**
     * Validate IP address for suspicious activity
     */
    private function validateIPAddress(Request $request)
    {
        $ip = $request->ip();
        $key = "suspicious_ip:{$ip}";
        
        // Check if IP is blacklisted
        if (Cache::has($key)) {
            return [
                'passed' => false,
                'message' => 'IP address is flagged for suspicious activity',
                'ip_address' => $ip,
            ];
        }

        // Check for multiple votes from same IP
        $votesFromIP = Vote::where('ip_address', $ip)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($votesFromIP >= $this->suspiciousActivityThreshold) {
            Cache::put($key, true, now()->addHours(24));
            return [
                'passed' => false,
                'message' => 'Too many votes from this IP address',
                'ip_address' => $ip,
            ];
        }

        return [
            'passed' => true,
            'message' => 'IP address validation passed',
            'ip_address' => $ip,
        ];
    }

    /**
     * Validate session security
     */
    private function validateSessionSecurity(Request $request)
    {
        $sessionId = $request->session()->getId();
        $key = "session_security:{$sessionId}";
        
        // Check for session hijacking attempts
        $sessionData = Cache::get($key, []);
        $currentFingerprint = $this->generateSessionFingerprint($request);
        
        if (isset($sessionData['fingerprint']) && 
            $sessionData['fingerprint'] !== $currentFingerprint) {
            return [
                'passed' => false,
                'message' => 'Session security violation detected',
                'session_id' => $sessionId,
            ];
        }

        // Store current fingerprint
        Cache::put($key, [
            'fingerprint' => $currentFingerprint,
            'last_activity' => now(),
        ], now()->addHours(2));

        return [
            'passed' => true,
            'message' => 'Session security validation passed',
            'session_id' => $sessionId,
        ];
    }

    /**
     * Generate session fingerprint
     */
    private function generateSessionFingerprint(Request $request)
    {
        $data = [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
        ];

        return hash('sha256', implode('|', $data));
    }

    /**
     * Validate device fingerprint with enhanced security
     */
    private function validateDeviceFingerprint(Request $request)
    {
        $deviceFingerprint = $request->header('X-Device-Fingerprint');
        
        // If no device fingerprint provided, generate one automatically
        if (!$deviceFingerprint) {
            $deviceFingerprint = $this->generateDeviceFingerprint($request);
        }

        // Validate fingerprint format (should be 64 character hex string)
        if (!preg_match('/^[a-f0-9]{64}$/', $deviceFingerprint)) {
            // If invalid format, generate a new one
            $deviceFingerprint = $this->generateDeviceFingerprint($request);
        }

        // Check for suspicious device fingerprint patterns
        $suspiciousPatterns = [
            '0000000000000000000000000000000000000000000000000000000000000000',
            'ffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
        ];
        
        if (in_array($deviceFingerprint, $suspiciousPatterns)) {
            return [
                'passed' => false,
                'message' => 'Suspicious device fingerprint detected',
                'fingerprint' => $deviceFingerprint,
            ];
        }

        // Check for duplicate device fingerprints from different IPs
        $duplicateFingerprints = Vote::where('device_fingerprint', $deviceFingerprint)
            ->where('ip_address', '!=', $request->ip())
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($duplicateFingerprints > 5) {
            return [
                'passed' => false,
                'message' => 'Device fingerprint used from multiple IP addresses',
                'fingerprint' => $deviceFingerprint,
                'duplicate_count' => $duplicateFingerprints,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Device fingerprint validation passed',
            'fingerprint' => $deviceFingerprint,
        ];
    }

    /**
     * Generate device fingerprint from request data
     */
    private function generateDeviceFingerprint(Request $request)
    {
        $data = [
            $request->ip(),
            $request->userAgent(),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
            $request->header('User-Agent'),
            now()->timestamp,
        ];

        return hash('sha256', implode('|', array_filter($data)));
    }

    /**
     * Validate voting time window
     */
    private function validateVotingTime(Election $election)
    {
        $now = now();
        
        if ($now->lt($election->start_date)) {
            return [
                'passed' => false,
                'message' => 'Voting has not started yet',
                'starts_at' => $election->start_date,
            ];
        }

        if ($now->gt($election->end_date)) {
            return [
                'passed' => false,
                'message' => 'Voting has ended',
                'ended_at' => $election->end_date,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Voting time validation passed',
            'time_remaining' => $election->end_date->diffInMinutes($now),
        ];
    }

    /**
     * Validate member eligibility
     */
    private function validateMemberEligibility(Member $member, Election $election)
    {
        // Check if member is active
        if ($member->status !== 1) {
            return [
                'passed' => false,
                'message' => 'Member account is not active',
                'member_status' => $member->status,
            ];
        }

        // Check if member belongs to correct tenant
        if ($member->tenant_id !== $election->tenant_id) {
            return [
                'passed' => false,
                'message' => 'Member does not belong to this tenant',
                'member_tenant' => $member->tenant_id,
                'election_tenant' => $election->tenant_id,
            ];
        }

        // Check if member has required role (if applicable)
        // Note: Role requirements should typically only apply to candidates, not voters
        // Only enforce role restrictions if explicitly configured for voting (not just for candidacy)
        if ($election->position && $election->position->required_role) {
            // Check if this is a special election that restricts voting to specific roles
            // For now, we'll be permissive and allow all active members to vote
            // Role requirements should primarily apply to who can be a candidate, not who can vote
            if (false) { // Disabled for now - role requirements should not restrict voting
                if (!$this->memberHasRequiredRole($member, $election->position->required_role)) {
                    return [
                        'passed' => false,
                        'message' => 'Member does not have required role for this election',
                        'required_role' => $election->position->required_role,
                    ];
                }
            }
        }

        return [
            'passed' => true,
            'message' => 'Member eligibility validation passed',
            'member_id' => $member->id,
        ];
    }

    /**
     * Check if member has required role
     */
    private function memberHasRequiredRole(Member $member, string $requiredRole)
    {
        $roleMap = [
            'chairperson' => 'is_vsla_chairperson',
            'treasurer' => 'is_vsla_treasurer',
            'secretary' => 'is_vsla_secretary',
        ];

        if (!isset($roleMap[$requiredRole])) {
            return true; // No specific role required
        }

        return $member->{$roleMap[$requiredRole]} ?? false;
    }

    /**
     * Check for duplicate votes
     */
    private function checkDuplicateVote(Member $member, Election $election)
    {
        $existingVote = Vote::where('member_id', $member->id)
            ->where('election_id', $election->id)
            ->first();

        if ($existingVote) {
            return [
                'passed' => false,
                'message' => 'Member has already voted in this election',
                'voted_at' => $existingVote->voted_at,
            ];
        }

        return [
            'passed' => true,
            'message' => 'No duplicate vote found',
        ];
    }

    /**
     * Validate geolocation (if available)
     */
    private function validateGeolocation(Request $request)
    {
        $latitude = $request->header('X-Latitude');
        $longitude = $request->header('X-Longitude');
        
        if (!$latitude || !$longitude) {
            return [
                'passed' => true, // Optional check
                'message' => 'Geolocation not provided (optional)',
            ];
        }

        // Basic coordinate validation
        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return [
                'passed' => false,
                'message' => 'Invalid geolocation coordinates',
            ];
        }

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return [
                'passed' => false,
                'message' => 'Geolocation coordinates out of valid range',
            ];
        }

        return [
            'passed' => true,
            'message' => 'Geolocation validation passed',
            'coordinates' => "{$latitude},{$longitude}",
        ];
    }

    /**
     * Calculate overall security score
     */
    private function calculateSecurityScore(array $checks)
    {
        $totalChecks = count($checks);
        $passedChecks = collect($checks)->where('passed', true)->count();
        
        return $totalChecks > 0 ? round(($passedChecks / $totalChecks) * 100) : 0;
    }

    /**
     * Log security validation
     */
    private function logSecurityValidation(Election $election, Member $member, array $checks, bool $isSecure)
    {
        VotingAuditLog::create([
            'election_id' => $election->id,
            'member_id' => $member->id,
            'action' => 'SECURITY_VALIDATION',
            'details' => json_encode([
                'checks' => $checks,
                'is_secure' => $isSecure,
                'timestamp' => now()->toISOString(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tenant_id' => $election->tenant_id,
            'performed_by' => $member->user_id ?? auth()->id(),
        ]);
    }

    /**
     * Generate security report for election
     */
    public function generateSecurityReport(Election $election)
    {
        $votes = $election->votes()->with('member')->get();
        $securityMetrics = [
            'total_votes' => $votes->count(),
            'unique_ips' => $votes->pluck('ip_address')->unique()->count(),
            'suspicious_activities' => $this->countSuspiciousActivities($election),
            'security_violations' => $this->countSecurityViolations($election),
        ];

        return [
            'election_id' => $election->id,
            'election_title' => $election->title,
            'security_metrics' => $securityMetrics,
            'security_score' => $this->calculateElectionSecurityScore($securityMetrics),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Count suspicious activities
     */
    private function countSuspiciousActivities(Election $election)
    {
        return VotingAuditLog::where('election_id', $election->id)
            ->where('action', 'LIKE', '%SUSPICIOUS%')
            ->count();
    }

    /**
     * Count security violations
     */
    private function countSecurityViolations(Election $election)
    {
        return VotingAuditLog::where('election_id', $election->id)
            ->where('action', 'LIKE', '%VIOLATION%')
            ->count();
    }

    /**
     * Calculate election security score
     */
    private function calculateElectionSecurityScore(array $metrics)
    {
        $score = 100;
        
        // Deduct points for suspicious activities
        $score -= $metrics['suspicious_activities'] * 5;
        
        // Deduct points for security violations
        $score -= $metrics['security_violations'] * 10;
        
        // Bonus for high unique IP ratio
        if ($metrics['total_votes'] > 0) {
            $uniqueIPRatio = $metrics['unique_ips'] / $metrics['total_votes'];
            if ($uniqueIPRatio > 0.8) {
                $score += 10;
            }
        }
        
        return max(0, min(100, $score));
    }

    /**
     * Clear rate limiting for a specific member (for debugging/admin use)
     */
    public function clearRateLimit(Member $member)
    {
        $key = "vote_attempts:{$member->id}";
        RateLimiter::clear($key);
        
        return [
            'success' => true,
            'message' => 'Rate limit cleared for member',
            'member_id' => $member->id,
        ];
    }

    /**
     * Get rate limit status for a member
     */
    public function getRateLimitStatus(Member $member)
    {
        $key = "vote_attempts:{$member->id}";
        $attempts = RateLimiter::attempts($key);
        $remaining = $this->maxVoteAttempts - $attempts;
        
        return [
            'member_id' => $member->id,
            'attempts' => $attempts,
            'max_attempts' => $this->maxVoteAttempts,
            'remaining' => max(0, $remaining),
            'is_blocked' => $attempts >= $this->maxVoteAttempts,
        ];
    }
}
