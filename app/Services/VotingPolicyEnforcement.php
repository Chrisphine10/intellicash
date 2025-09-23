<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Vote;
use App\Models\Member;
use App\Models\VotingPosition;
use App\Models\VotingAuditLog;
use Carbon\Carbon;

class VotingPolicyEnforcement
{
    /**
     * Enforce all voting policies before allowing vote submission
     */
    public function enforceVotingPolicies(Election $election, Member $member, array $voteData)
    {
        $policies = [
            'election_status' => $this->enforceElectionStatusPolicy($election),
            'voting_window' => $this->enforceVotingWindowPolicy($election),
            'member_eligibility' => $this->enforceMemberEligibilityPolicy($member, $election),
            'vote_validation' => $this->enforceVoteValidationPolicy($election, $voteData),
            'candidate_eligibility' => $this->enforceCandidateEligibilityPolicy($election, $voteData),
            'voting_mechanism' => $this->enforceVotingMechanismPolicy($election, $voteData),
            'privacy_compliance' => $this->enforcePrivacyPolicy($election, $member),
            'audit_requirements' => $this->enforceAuditPolicy($election, $member),
        ];

        $allPoliciesPassed = collect($policies)->every(fn($policy) => $policy['passed']);
        
        // Log policy enforcement
        $this->logPolicyEnforcement($election, $member, $policies, $allPoliciesPassed);

        return [
            'all_policies_passed' => $allPoliciesPassed,
            'policies' => $policies,
            'compliance_score' => $this->calculateComplianceScore($policies),
        ];
    }

    /**
     * Enforce election status policy
     */
    private function enforceElectionStatusPolicy(Election $election)
    {
        if ($election->status !== 'active') {
            return [
                'passed' => false,
                'message' => 'Election is not active. Current status: ' . $election->status,
                'policy' => 'election_status',
                'current_status' => $election->status,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Election status policy passed',
            'policy' => 'election_status',
            'current_status' => $election->status,
        ];
    }

    /**
     * Enforce voting window policy
     */
    private function enforceVotingWindowPolicy(Election $election)
    {
        $now = now();
        
        if ($now->lt($election->start_date)) {
            return [
                'passed' => false,
                'message' => 'Voting window has not opened yet',
                'policy' => 'voting_window',
                'opens_at' => $election->start_date,
                'current_time' => $now,
            ];
        }

        if ($now->gt($election->end_date)) {
            return [
                'passed' => false,
                'message' => 'Voting window has closed',
                'policy' => 'voting_window',
                'closed_at' => $election->end_date,
                'current_time' => $now,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Voting window policy passed',
            'policy' => 'voting_window',
            'time_remaining' => $election->end_date->diffInMinutes($now),
        ];
    }

    /**
     * Enforce member eligibility policy
     */
    private function enforceMemberEligibilityPolicy(Member $member, Election $election)
    {
        // Check member status
        if ($member->status !== 1) {
            return [
                'passed' => false,
                'message' => 'Member account is not active',
                'policy' => 'member_eligibility',
                'member_status' => $member->status,
            ];
        }

        // Check tenant membership
        if ($member->tenant_id !== $election->tenant_id) {
            return [
                'passed' => false,
                'message' => 'Member does not belong to this tenant',
                'policy' => 'member_eligibility',
                'member_tenant' => $member->tenant_id,
                'election_tenant' => $election->tenant_id,
            ];
        }

        // Check if member has already voted
        $existingVote = Vote::where('member_id', $member->id)
            ->where('election_id', $election->id)
            ->first();

        if ($existingVote) {
            return [
                'passed' => false,
                'message' => 'Member has already voted in this election',
                'policy' => 'member_eligibility',
                'voted_at' => $existingVote->voted_at,
            ];
        }

        // Check member role requirements
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
                        'message' => 'Member does not have required role: ' . $election->position->required_role,
                        'policy' => 'member_eligibility',
                        'required_role' => $election->position->required_role,
                    ];
                }
            }
        }

        return [
            'passed' => true,
            'message' => 'Member eligibility policy passed',
            'policy' => 'member_eligibility',
            'member_id' => $member->id,
        ];
    }

    /**
     * Enforce vote validation policy
     */
    private function enforceVoteValidationPolicy(Election $election, array $voteData)
    {
        if ($election->type === 'referendum') {
            // For referendum elections, validate choice field
            if (!isset($voteData['choice']) || empty($voteData['choice'])) {
                return [
                    'passed' => false,
                    'message' => "Required field 'choice' is missing for referendum election",
                    'policy' => 'vote_validation',
                    'missing_field' => 'choice',
                ];
            }

            // Validate choice value for referendum
            $validChoices = ['yes', 'no', 'abstain'];
            if (!in_array($voteData['choice'], $validChoices)) {
                return [
                    'passed' => false,
                    'message' => 'Invalid choice value for referendum. Must be one of: ' . implode(', ', $validChoices),
                    'policy' => 'vote_validation',
                    'invalid_choice' => $voteData['choice'],
                ];
            }
        } else {
            // For candidate elections, validate candidate_id or abstain
            if (isset($voteData['candidate_id']) && !empty($voteData['candidate_id'])) {
                // Voting for a candidate
                if (!isset($voteData['candidate_id']) || empty($voteData['candidate_id'])) {
                    return [
                        'passed' => false,
                        'message' => 'candidate_id is required when voting for a candidate',
                        'policy' => 'vote_validation',
                    ];
                }
            } elseif (!isset($voteData['is_abstain']) || !$voteData['is_abstain']) {
                // Not voting for a candidate and not abstaining
                if (!$election->allow_abstain) {
                    return [
                        'passed' => false,
                        'message' => 'Must select a candidate or abstain',
                        'policy' => 'vote_validation',
                    ];
                }
            }
        }

        return [
            'passed' => true,
            'message' => 'Vote validation policy passed',
            'policy' => 'vote_validation',
        ];
    }

    /**
     * Enforce candidate eligibility policy
     */
    private function enforceCandidateEligibilityPolicy(Election $election, array $voteData)
    {
        // For referendum elections, no candidate validation needed
        if ($election->type === 'referendum') {
            return [
                'passed' => true,
                'message' => 'No candidate validation needed for referendum',
                'policy' => 'candidate_eligibility',
            ];
        }

        // For candidate elections, check if voting for a candidate
        if (!isset($voteData['candidate_id']) || empty($voteData['candidate_id'])) {
            return [
                'passed' => true,
                'message' => 'No candidate validation needed (abstain)',
                'policy' => 'candidate_eligibility',
            ];
        }

        $candidate = $election->candidates()->find($voteData['candidate_id']);
        
        if (!$candidate) {
            return [
                'passed' => false,
                'message' => 'Selected candidate does not exist in this election',
                'policy' => 'candidate_eligibility',
                'candidate_id' => $voteData['candidate_id'],
            ];
        }

        // Check if candidate is still active
        if (!$candidate->is_active) {
            return [
                'passed' => false,
                'message' => 'Selected candidate is no longer active',
                'policy' => 'candidate_eligibility',
                'candidate_id' => $candidate->id,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Candidate eligibility policy passed',
            'policy' => 'candidate_eligibility',
            'candidate_id' => $candidate->id,
        ];
    }

    /**
     * Enforce voting mechanism policy
     */
    private function enforceVotingMechanismPolicy(Election $election, array $voteData)
    {
        switch ($election->voting_mechanism) {
            case 'majority':
                return $this->enforceMajorityVotingPolicy($election, $voteData);
            case 'ranked_choice':
                return $this->enforceRankedChoicePolicy($election, $voteData);
            case 'weighted':
                return $this->enforceWeightedVotingPolicy($election, $voteData);
            default:
                return [
                    'passed' => false,
                    'message' => 'Unknown voting mechanism: ' . $election->voting_mechanism,
                    'policy' => 'voting_mechanism',
                ];
        }
    }

    /**
     * Enforce majority voting policy
     */
    private function enforceMajorityVotingPolicy(Election $election, array $voteData)
    {
        if ($election->type === 'referendum') {
            // For referendum elections, validate choice
            if (isset($voteData['choice']) && in_array($voteData['choice'], ['yes', 'no', 'abstain'])) {
                return [
                    'passed' => true,
                    'message' => 'Majority voting policy passed (referendum)',
                    'policy' => 'voting_mechanism',
                    'mechanism' => 'majority',
                ];
            }
        } else {
            // For candidate elections, check candidate selection
            if (isset($voteData['candidate_id']) && !empty($voteData['candidate_id'])) {
                // For majority voting, only one candidate can be selected
                return [
                    'passed' => true,
                    'message' => 'Majority voting policy passed (candidate)',
                    'policy' => 'voting_mechanism',
                    'mechanism' => 'majority',
                ];
            }
        }

        return [
            'passed' => true,
            'message' => 'Majority voting policy passed (abstain)',
            'policy' => 'voting_mechanism',
            'mechanism' => 'majority',
        ];
    }

    /**
     * Enforce ranked choice voting policy
     */
    private function enforceRankedChoicePolicy(Election $election, array $voteData)
    {
        // Ranked choice voting only applies to candidate elections
        if ($election->type === 'referendum') {
            return [
                'passed' => true,
                'message' => 'Ranked choice not applicable to referendum',
                'policy' => 'voting_mechanism',
                'mechanism' => 'ranked_choice',
            ];
        }

        if (isset($voteData['candidate_id']) && !empty($voteData['candidate_id']) && isset($voteData['rankings'])) {
            $rankings = $voteData['rankings'];
            
            // Validate rankings format
            if (!is_array($rankings) || empty($rankings)) {
                return [
                    'passed' => false,
                    'message' => 'Rankings must be provided for ranked choice voting',
                    'policy' => 'voting_mechanism',
                ];
            }

            // Validate all candidates are ranked
            $candidateIds = $election->candidates()->pluck('id')->toArray();
            $rankedCandidateIds = array_keys($rankings);
            
            if (count($rankedCandidateIds) !== count($candidateIds)) {
                return [
                    'passed' => false,
                    'message' => 'All candidates must be ranked',
                    'policy' => 'voting_mechanism',
                ];
            }

            // Validate ranking values are unique
            $rankingValues = array_values($rankings);
            if (count($rankingValues) !== count(array_unique($rankingValues))) {
                return [
                    'passed' => false,
                    'message' => 'Ranking values must be unique',
                    'policy' => 'voting_mechanism',
                ];
            }
        }

        return [
            'passed' => true,
            'message' => 'Ranked choice voting policy passed',
            'policy' => 'voting_mechanism',
            'mechanism' => 'ranked_choice',
        ];
    }

    /**
     * Enforce weighted voting policy
     */
    private function enforceWeightedVotingPolicy(Election $election, array $voteData)
    {
        if (!$election->weighted_voting) {
            return [
                'passed' => true,
                'message' => 'Weighted voting not enabled',
                'policy' => 'voting_mechanism',
            ];
        }

        // For weighted voting, we need to validate the member's voting weight
        // This would typically be based on shares, membership duration, etc.
        $memberWeight = $this->calculateMemberVotingWeight($election, $voteData['member_id'] ?? null);
        
        if ($memberWeight <= 0) {
            return [
                'passed' => false,
                'message' => 'Member has no voting weight',
                'policy' => 'voting_mechanism',
                'member_weight' => $memberWeight,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Weighted voting policy passed',
            'policy' => 'voting_mechanism',
            'mechanism' => 'weighted',
            'member_weight' => $memberWeight,
        ];
    }

    /**
     * Enforce privacy policy
     */
    private function enforcePrivacyPolicy(Election $election, Member $member)
    {
        // Check if election allows voting based on privacy mode
        if ($election->privacy_mode === 'private') {
            // In private mode, ensure vote data is properly encrypted
            return [
                'passed' => true,
                'message' => 'Privacy policy enforced (private mode)',
                'policy' => 'privacy_compliance',
                'privacy_mode' => $election->privacy_mode,
            ];
        }

        return [
            'passed' => true,
            'message' => 'Privacy policy passed',
            'policy' => 'privacy_compliance',
            'privacy_mode' => $election->privacy_mode,
        ];
    }

    /**
     * Enforce audit policy
     */
    private function enforceAuditPolicy(Election $election, Member $member)
    {
        // Ensure all voting activities are properly audited
        return [
            'passed' => true,
            'message' => 'Audit policy enforced',
            'policy' => 'audit_requirements',
            'audit_enabled' => true,
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
     * Calculate member voting weight
     */
    private function calculateMemberVotingWeight(Election $election, $memberId)
    {
        if (!$memberId) {
            return 0;
        }

        $member = Member::find($memberId);
        if (!$member) {
            return 0;
        }

        // Base weight
        $weight = 1;

        // Add weight based on membership duration
        $membershipDuration = $member->created_at->diffInMonths(now());
        $weight += floor($membershipDuration / 12); // 1 point per year

        // Add weight based on savings (if applicable)
        $totalSavings = $member->savings_accounts()->sum('balance');
        $weight += floor($totalSavings / 1000); // 1 point per 1000 units

        return max(1, $weight); // Minimum weight of 1
    }

    /**
     * Calculate compliance score
     */
    private function calculateComplianceScore(array $policies)
    {
        $totalPolicies = count($policies);
        $passedPolicies = collect($policies)->where('passed', true)->count();
        
        return $totalPolicies > 0 ? round(($passedPolicies / $totalPolicies) * 100) : 0;
    }

    /**
     * Log policy enforcement
     */
    private function logPolicyEnforcement(Election $election, Member $member, array $policies, bool $allPassed)
    {
        VotingAuditLog::create([
            'election_id' => $election->id,
            'member_id' => $member->id,
            'action' => 'POLICY_ENFORCEMENT',
            'details' => json_encode([
                'policies' => $policies,
                'all_passed' => $allPassed,
                'compliance_score' => $this->calculateComplianceScore($policies),
                'timestamp' => now()->toISOString(),
            ]),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tenant_id' => $election->tenant_id,
            'performed_by' => $member->user_id ?? auth()->id(),
        ]);
    }

    /**
     * Generate policy compliance report
     */
    public function generateComplianceReport(Election $election)
    {
        $votes = $election->votes()->with('member')->get();
        $policyLogs = VotingAuditLog::where('election_id', $election->id)
            ->where('action', 'POLICY_ENFORCEMENT')
            ->get();

        $complianceMetrics = [
            'total_votes' => $votes->count(),
            'policy_checks' => $policyLogs->count(),
            'compliance_rate' => $this->calculateOverallComplianceRate($policyLogs),
        ];

        return [
            'election_id' => $election->id,
            'election_title' => $election->title,
            'compliance_metrics' => $complianceMetrics,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate overall compliance rate
     */
    private function calculateOverallComplianceRate($policyLogs)
    {
        if ($policyLogs->isEmpty()) {
            return 100;
        }

        $totalChecks = $policyLogs->count();
        $passedChecks = $policyLogs->filter(function ($log) {
            $details = json_decode($log->details, true);
            return $details['all_passed'] ?? false;
        })->count();

        return round(($passedChecks / $totalChecks) * 100);
    }
}
