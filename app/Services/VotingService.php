<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Vote;
use App\Models\ElectionResult;
use App\Models\Member;
use App\Notifications\ElectionCreatedNotification;
use App\Notifications\ElectionResultsNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class VotingService
{
    /**
     * Calculate election results based on voting mechanism
     */
    public function calculateResults(Election $election)
    {
        // Clear existing results
        $election->results()->delete();

        switch ($election->voting_mechanism) {
            case 'majority':
                $this->calculateMajorityResults($election);
                break;
            case 'ranked_choice':
                $this->calculateRankedChoiceResults($election);
                break;
            case 'weighted':
                $this->calculateWeightedResults($election);
                break;
        }
    }

    /**
     * Calculate majority vote results
     */
    private function calculateMajorityResults(Election $election)
    {
        if ($election->type === 'referendum') {
            $this->calculateReferendumResults($election);
            return;
        }

        $votes = Vote::where('election_id', $election->id)
            ->where('is_abstain', false)
            ->selectRaw('candidate_id, COUNT(*) as vote_count, SUM(weight) as total_weight')
            ->groupBy('candidate_id')
            ->get();

        $totalVotes = $votes->sum('total_weight');
        
        // For Multi Position elections, use position max_winners, otherwise default to 1
        $maxWinners = $election->type === 'multi_position' 
            ? ($election->position ? $election->position->max_winners : 1)
            : 1;

        $results = [];
        foreach ($votes as $vote) {
            $percentage = $totalVotes > 0 ? ($vote->total_weight / $totalVotes) * 100 : 0;
            
            $results[] = [
                'election_id' => $election->id,
                'candidate_id' => $vote->candidate_id,
                'total_votes' => $vote->total_weight,
                'percentage' => $percentage,
                'is_winner' => false,
                'tenant_id' => $election->tenant_id,
            ];
        }

        // Sort by vote count and mark winners
        usort($results, function ($a, $b) {
            return $b['total_votes'] <=> $a['total_votes'];
        });

        // Mark winners based on election type
        $winnersCount = min($maxWinners, count($results));
        for ($i = 0; $i < $winnersCount; $i++) {
            $results[$i]['is_winner'] = true;
            $results[$i]['rank'] = $i + 1;
        }

        // Add rank to all results
        foreach ($results as $index => $result) {
            if (!isset($result['rank'])) {
                $results[$index]['rank'] = $index + 1;
            }
        }

        ElectionResult::insert($results);
    }

    /**
     * Calculate referendum results
     */
    private function calculateReferendumResults(Election $election)
    {
        $votes = Vote::where('election_id', $election->id)
            ->selectRaw('choice, COUNT(*) as vote_count, SUM(weight) as total_weight')
            ->groupBy('choice')
            ->get();

        $totalVotes = $votes->sum('total_weight');

        $results = [];
        foreach ($votes as $vote) {
            $percentage = $totalVotes > 0 ? ($vote->total_weight / $totalVotes) * 100 : 0;
            
            $results[] = [
                'election_id' => $election->id,
                'choice' => $vote->choice,
                'total_votes' => $vote->total_weight,
                'percentage' => $percentage,
                'is_winner' => $vote->choice === 'yes',
                'tenant_id' => $election->tenant_id,
            ];
        }

        ElectionResult::insert($results);
    }

    /**
     * Calculate ranked choice voting results
     */
    private function calculateRankedChoiceResults(Election $election)
    {
        $candidates = $election->candidates->pluck('id')->toArray();
        $votes = Vote::where('election_id', $election->id)
            ->where('is_abstain', false)
            ->get();

        // For Multi Position elections, use position max_winners, otherwise default to 1
        $maxWinners = $election->type === 'multi_position' 
            ? ($election->position ? $election->position->max_winners : 1)
            : 1;
            
        $winners = [];
        $round = 1;
        $remainingCandidates = $candidates;

        while (count($winners) < $maxWinners && count($remainingCandidates) > 0) {
            $tally = $this->tallyRankedVotes($votes, $remainingCandidates, $winners);
            
            // Check for majority
            $totalVotes = array_sum($tally);
            $majorityThreshold = $totalVotes / 2;
            
            $foundWinner = false;
            foreach ($tally as $candidateId => $voteCount) {
                if ($voteCount > $majorityThreshold) {
                    $winners[] = $candidateId;
                    $remainingCandidates = array_diff($remainingCandidates, [$candidateId]);
                    $foundWinner = true;
                    break;
                }
            }

            // If no majority and we need more winners, eliminate lowest candidate
            if (!$foundWinner && count($remainingCandidates) > 0) {
                $lowestCandidate = array_keys($tally, min($tally))[0];
                $remainingCandidates = array_diff($remainingCandidates, [$lowestCandidate]);
            }

            $round++;
            
            // Prevent infinite loop
            if ($round > 100) {
                break;
            }
        }

        // Create results
        $this->createRankedChoiceResults($election, $tally ?? [], $winners);
    }

    /**
     * Tally votes for ranked choice
     */
    private function tallyRankedVotes($votes, $candidates, $winners)
    {
        $tally = array_fill_keys($candidates, 0);

        foreach ($votes as $vote) {
            if ($vote->candidate_id && in_array($vote->candidate_id, $candidates)) {
                $tally[$vote->candidate_id]++;
            }
        }

        return $tally;
    }

    /**
     * Create ranked choice results
     */
    private function createRankedChoiceResults(Election $election, $tally, $winners)
    {
        $totalVotes = array_sum($tally);
        $results = [];

        foreach ($tally as $candidateId => $voteCount) {
            $percentage = $totalVotes > 0 ? ($voteCount / $totalVotes) * 100 : 0;
            
            $results[] = [
                'election_id' => $election->id,
                'candidate_id' => $candidateId,
                'total_votes' => $voteCount,
                'percentage' => $percentage,
                'is_winner' => in_array($candidateId, $winners),
                'tenant_id' => $election->tenant_id,
            ];
        }

        ElectionResult::insert($results);
    }

    /**
     * Calculate weighted voting results
     */
    private function calculateWeightedResults(Election $election)
    {
        // For now, same as majority but with weights
        $this->calculateMajorityResults($election);
    }

    /**
     * Get election statistics
     */
    public function getElectionStats(Election $election)
    {
        $totalMembers = Member::where('tenant_id', $election->tenant_id)->count();
        $totalVotes = $election->votes()->count();
        $abstentions = $election->votes()->where('is_abstain', true)->count();
        $participationRate = $totalMembers > 0 ? ($totalVotes / $totalMembers) * 100 : 0;

        return [
            'total_members' => $totalMembers,
            'total_votes' => $totalVotes,
            'abstentions' => $abstentions,
            'participation_rate' => round($participationRate, 2),
            'remaining_votes' => $totalMembers - $totalVotes,
        ];
    }

    /**
     * Notify members of new election
     */
    public function notifyMembersOfNewElection(Election $election)
    {
        $members = Member::where('tenant_id', $election->tenant_id)
            ->where('status', 1)
            ->get();

        foreach ($members as $member) {
            if ($member->user) {
                $member->user->notify(new ElectionCreatedNotification($election));
            }
        }
    }

    /**
     * Notify members of election results
     */
    public function notifyMembersOfResults(Election $election)
    {
        $members = Member::where('tenant_id', $election->tenant_id)
            ->where('status', 1)
            ->get();

        foreach ($members as $member) {
            if ($member->user) {
                $member->user->notify(new ElectionResultsNotification($election));
            }
        }
    }

    /**
     * Get member's voting history
     */
    public function getMemberVotingHistory($memberId, $tenantId)
    {
        return Vote::where('member_id', $memberId)
            ->where('tenant_id', $tenantId)
            ->with(['election', 'candidate'])
            ->orderBy('voted_at', 'desc')
            ->get();
    }

    /**
     * Get active elections for member
     */
    public function getActiveElectionsForMember($memberId, $tenantId)
    {
        $votedElectionIds = Vote::where('member_id', $memberId)
            ->where('tenant_id', $tenantId)
            ->pluck('election_id');

        return Election::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNotIn('id', $votedElectionIds)
            ->with(['position', 'candidates'])
            ->get();
    }

    /**
     * Validate Multi Position election configuration
     */
    public function validateMultiPositionElection(Election $election)
    {
        $errors = [];

        if ($election->type === 'multi_position') {
            // Check if position is set
            if (!$election->position) {
                $errors[] = 'Multi Position elections must have an associated position';
            } else {
                // Check if position has max_winners > 1
                if ($election->position->max_winners <= 1) {
                    $errors[] = 'Multi Position elections require max_winners > 1 for the position';
                }
                
                // Check if there are enough candidates
                $candidateCount = $election->candidates()->count();
                if ($candidateCount < $election->position->max_winners) {
                    $errors[] = "Not enough candidates for Multi Position election. Need at least {$election->position->max_winners} candidates, found {$candidateCount}";
                }
            }
        }

        return $errors;
    }

    /**
     * Get election results based on privacy mode
     */
    public function getElectionResultsForUser(Election $election, $userType = 'member')
    {
        $results = $election->results()->with('candidate.member')->get();
        
        switch ($election->privacy_mode) {
            case 'private':
                // Only show aggregated results, no individual vote details
                return $this->getPrivateResults($results);
                
            case 'public':
                // Show all vote details
                return $this->getPublicResults($results, $election);
                
            case 'hybrid':
                // Show different levels based on user type
                if ($userType === 'admin') {
                    return $this->getPublicResults($results, $election);
                } else {
                    return $this->getPrivateResults($results);
                }
                
            default:
                return $this->getPrivateResults($results);
        }
    }

    /**
     * Get private results (aggregated only)
     */
    private function getPrivateResults($results)
    {
        return $results->map(function ($result) {
            return (object) [
                'candidate_name' => $result->candidate ? $result->candidate->name : ($result->choice ?? 'Unknown'),
                'choice' => $result->choice ?? null,
                'total_votes' => $result->total_votes,
                'percentage' => $result->percentage,
                'is_winner' => $result->is_winner,
                'rank' => $result->rank,
                'individual_votes' => null, // Hidden for privacy
            ];
        });
    }

    /**
     * Get public results (with individual vote details)
     */
    private function getPublicResults($results, Election $election)
    {
        $votes = Vote::where('election_id', $election->id)
            ->with('member')
            ->get()
            ->groupBy('candidate_id');

        return $results->map(function ($result) use ($votes) {
            $individualVotes = $votes->get($result->candidate_id, collect())->map(function ($vote) {
                return (object) [
                    'member_name' => $vote->member ? $vote->member->first_name . ' ' . $vote->member->last_name : 'Unknown',
                    'voted_at' => $vote->voted_at,
                    'weight' => $vote->weight,
                ];
            });

            return (object) [
                'candidate_name' => $result->candidate ? $result->candidate->name : ($result->choice ?? 'Unknown'),
                'choice' => $result->choice ?? null,
                'total_votes' => $result->total_votes,
                'percentage' => $result->percentage,
                'is_winner' => $result->is_winner,
                'rank' => $result->rank,
                'individual_votes' => $individualVotes,
            ];
        });
    }
}
