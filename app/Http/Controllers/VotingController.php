<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Models\VotingPosition;
use App\Models\Candidate;
use App\Models\Vote;
use App\Models\ElectionResult;
use App\Models\VotingAuditLog;
use App\Models\Member;
use App\Services\VotingService;
use App\Services\BlockchainVotingService;
use App\Services\MilitaryGradeVotingSecurity;
use App\Services\VotingPolicyEnforcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VotingController extends Controller
{
    protected $votingService;
    protected $blockchainService;
    protected $securityService;
    protected $policyService;

    public function __construct(
        VotingService $votingService,
        BlockchainVotingService $blockchainService,
        MilitaryGradeVotingSecurity $securityService,
        VotingPolicyEnforcement $policyService
    ) {
        $this->votingService = $votingService;
        $this->blockchainService = $blockchainService;
        $this->securityService = $securityService;
        $this->policyService = $policyService;
    }

    /**
     * Display a listing of elections
     */
    public function index(Request $request)
    {
        $query = Election::with(['position', 'candidates', 'createdBy'])
            ->where('tenant_id', app('tenant')->id);

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by type
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        $elections = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('backend.voting.elections.index', compact('elections'));
    }

    /**
     * Show the form for creating a new election
     */
    public function create()
    {
        $positions = VotingPosition::where('tenant_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('backend.voting.elections.create', compact('positions'));
    }

    /**
     * Store a newly created election
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:single_winner,multi_position,referendum',
            'voting_mechanism' => 'required|in:majority,ranked_choice,weighted',
            'privacy_mode' => 'required|in:private,public,hybrid',
            'position_id' => 'nullable|exists:voting_positions,id',
            'start_date' => 'required|date|after:now',
            'end_date' => 'required|date|after:start_date',
            'allow_abstain' => 'boolean',
            'weighted_voting' => 'boolean',
        ]);

        // Additional validation for Multi Position elections
        if ($request->type === 'multi_position') {
            if (!$request->position_id) {
                return redirect()->back()
                    ->withErrors(['position_id' => _lang('Multi Position elections must have an associated position')])
                    ->withInput();
            }
            
            $position = VotingPosition::find($request->position_id);
            if ($position && $position->max_winners <= 1) {
                return redirect()->back()
                    ->withErrors(['position_id' => _lang('Multi Position elections require max_winners > 1 for the position')])
                    ->withInput();
            }
        }

        $election = Election::create([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'voting_mechanism' => $request->voting_mechanism,
            'privacy_mode' => $request->privacy_mode,
            'position_id' => $request->position_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'allow_abstain' => $request->has('allow_abstain'),
            'weighted_voting' => $request->has('weighted_voting'),
            'tenant_id' => app('tenant')->id,
            'created_by' => Auth::id(),
        ]);

        // Validate Multi Position election configuration
        $validationErrors = $this->votingService->validateMultiPositionElection($election);
        if (!empty($validationErrors)) {
            $election->delete(); // Remove the created election
            return redirect()->back()
                ->withErrors(['type' => implode(', ', $validationErrors)])
                ->withInput();
        }

        // Log the creation
        $this->logAuditAction($election, 'created', [
            'title' => $election->title,
            'type' => $election->type,
            'privacy_mode' => $election->privacy_mode,
        ]);

        return redirect()->route('voting.elections.show', [
            'tenant' => app('tenant')->slug,
            'election' => $election->id
        ])->with('success', _lang('Election created successfully'));
    }

    /**
     * Display the specified election
     */
    public function show($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('view', $election);

        $election->load(['position', 'candidates.member', 'votes.member', 'results.candidate']);

        // Get voting statistics
        $stats = $this->votingService->getElectionStats($election);

        return view('backend.voting.elections.show', compact('election', 'stats'));
    }

    /**
     * Show the form for editing the specified election
     */
    public function edit($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('update', $election);

        $positions = VotingPosition::where('tenant_id', app('tenant')->id)
            ->where('is_active', true)
            ->get();

        return view('backend.voting.elections.edit', compact('election', 'positions'));
    }

    /**
     * Update the specified election
     */
    public function update(Request $request, $tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('update', $election);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'allow_abstain' => 'boolean',
            'weighted_voting' => 'boolean',
        ]);

        $election->update([
            'title' => $request->title,
            'description' => $request->description,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'allow_abstain' => $request->has('allow_abstain'),
            'weighted_voting' => $request->has('weighted_voting'),
        ]);

        // Log the update
        $this->logAuditAction($election, 'updated', [
            'title' => $election->title,
        ]);

        return redirect()->route('voting.elections.show', [
            'tenant' => app('tenant')->slug,
            'election' => $election->id
        ])->with('success', _lang('Election updated successfully'));
    }

    /**
     * Start an election
     */
    public function start($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('manage', $election);

        if ($election->status !== 'draft') {
            return redirect()->back()->with('error', _lang('Only draft elections can be started'));
        }

        $election->update(['status' => 'active']);

        // Log the action
        $this->logAuditAction($election, 'started');

        // Send notifications to members
        $this->votingService->notifyMembersOfNewElection($election);

        return redirect()->back()->with('success', _lang('Election started successfully'));
    }

    /**
     * Close an election
     */
    public function close($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('manage', $election);

        if ($election->status !== 'active') {
            return redirect()->back()->with('error', _lang('Only active elections can be closed'));
        }

        DB::transaction(function () use ($election) {
            $election->update(['status' => 'closed']);
            
            // Calculate results
            $this->votingService->calculateResults($election);
            
            // Log the action
            $this->logAuditAction($election, 'closed');
        });

        // Send notifications about results
        $this->votingService->notifyMembersOfResults($election);

        return redirect()->back()->with('success', _lang('Election closed and results calculated'));
    }

    /**
     * Show voting interface for members
     */
    public function vote($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('vote', $election);

        if (!$election->canVote()) {
            return redirect()->back()->with('error', _lang('Voting is not currently active for this election'));
        }

        // Check if member has already voted
        $member = Member::where('user_id', Auth::id())
            ->where('tenant_id', app('tenant')->id)
            ->first();

        if (!$member) {
            return redirect()->back()->with('error', _lang('Member not found'));
        }

        $existingVote = Vote::where('election_id', $election->id)
            ->where('member_id', $member->id)
            ->with('candidate') // Load the candidate relationship
            ->first();

        $election->load(['candidates.member', 'position']);

        return view('backend.voting.vote', compact('election', 'member', 'existingVote'));
    }

    /**
     * Submit a vote
     */
    /**
     * Submit a vote with military-grade security and blockchain verification
     */
    public function submitVote(Request $request, $tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('vote', $election);

        if (!$election->canVote()) {
            return redirect()->back()->with('error', _lang('Voting is not currently active for this election'));
        }

        $member = Member::where('user_id', Auth::id())
            ->where('tenant_id', app('tenant')->id)
            ->first();

        if (!$member) {
            return redirect()->back()->with('error', _lang('Member not found'));
        }

        // Check if already voted
        $existingVote = Vote::where('election_id', $election->id)
            ->where('member_id', $member->id)
            ->first();

        if ($existingVote) {
            return redirect()->back()->with('error', _lang('You have already voted in this election'));
        }

        $request->validate($this->getVoteValidationRules($election));

        // Prepare vote data with security information
        $voteData = [
            'election_id' => $election->id,
            'member_id' => $member->id,
            'tenant_id' => app('tenant')->id,
            'voted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_fingerprint' => $request->header('X-Device-Fingerprint'),
            'latitude' => $request->header('X-Latitude'),
            'longitude' => $request->header('X-Longitude'),
        ];

        if ($election->type === 'referendum') {
            $voteData['choice'] = $request->choice;
            $voteData['is_abstain'] = $request->choice === 'abstain';
        } else {
            if ($request->has('candidate_id')) {
                $voteData['candidate_id'] = $request->candidate_id;
            } else {
                $voteData['is_abstain'] = true;
            }

            if ($election->voting_mechanism === 'ranked_choice' && $request->has('rankings')) {
                $voteData['rank'] = $request->rankings[$request->candidate_id] ?? 1;
            }
        }

        if ($election->weighted_voting) {
            $voteData['weight'] = $this->calculateVoteWeight($member);
        }

        // 1. Enforce voting policies
        $policyResult = $this->policyService->enforceVotingPolicies($election, $member, $voteData);
        if (!$policyResult['all_policies_passed']) {
            return redirect()->back()->with('error', _lang('Vote submission failed policy validation: ') . 
                collect($policyResult['policies'])->where('passed', false)->first()['message']);
        }

        // 2. Military-grade security validation
        $securityResult = $this->securityService->validateVoteSecurity($request, $election, $member);
        if (!$securityResult['is_secure']) {
            return redirect()->back()->with('error', _lang('Vote submission failed security validation: ') . 
                collect($securityResult['checks'])->where('passed', false)->first()['message']);
        }

        // 3. Create vote with blockchain security
        $vote = null;
        DB::transaction(function () use ($voteData, $election, $member, $policyResult, &$vote) {
            $vote = Vote::create($voteData);
            
            // 4. Generate blockchain hash and encrypt data
            $blockchainHash = $this->blockchainService->createVoteBlock($vote, $election);
            
            // 5. Update vote with security score
            $vote->update([
                'security_score' => $this->calculateSecurityScore($voteData),
            ]);

            // 6. Log the secure vote
            $this->logAuditAction($election, 'SECURE_VOTE_CREATED', [
                'member_id' => $member->id,
                'choice' => $voteData['choice'] ?? ($voteData['candidate_id'] ? 'candidate' : 'abstain'),
                'candidate_id' => $voteData['candidate_id'] ?? null,
                'blockchain_hash' => $blockchainHash,
                'security_score' => $vote->security_score,
                'policy_compliance' => $policyResult['compliance_score'],
            ], $member->id);
        });

        // 7. Verify the vote was created securely
        $verificationResult = $this->blockchainService->verifyVote($vote);
        if (!$verificationResult) {
            // This should never happen, but if it does, we need to investigate
            $this->logAuditAction($election, 'VOTE_VERIFICATION_FAILED', [
                'vote_id' => $vote->id,
                'member_id' => $member->id,
            ], $member->id);
        }

        return redirect()->route('voting.elections.show', [
            'tenant' => app('tenant')->slug,
            'election' => $election->id
        ])->with('success', _lang('Your vote has been securely submitted and verified on the blockchain'));
    }

    /**
     * Show election results
     */
    public function results($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('view', $election);

        if ($election->status !== 'closed') {
            return redirect()->back()->with('error', _lang('Results are only available for closed elections'));
        }

        $election->load(['results.candidate.member', 'position']);
        
        // Determine user type for privacy mode
        $userType = auth()->user()->user_type === 'admin' ? 'admin' : 'member';
        
        // Get results based on privacy mode
        $results = $this->votingService->getElectionResultsForUser($election, $userType);

        return view('backend.voting.results', compact('election', 'results'));
    }

    /**
     * Manage candidates for an election
     */
    public function manageCandidates($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('manage', $election);

        $election->load(['candidates.member']);
        $members = Member::where('tenant_id', app('tenant')->id)
            ->where('status', 1)
            ->get();

        return view('backend.voting.candidates.manage', compact('election', 'members'));
    }

    /**
     * Add candidate to election
     */
    public function addCandidate(Request $request, $tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('manage', $election);

        $request->validate([
            'member_id' => 'required|exists:members,id',
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'manifesto' => 'nullable|string',
        ]);

        // Check if member is already a candidate
        $existingCandidate = Candidate::where('election_id', $election->id)
            ->where('member_id', $request->member_id)
            ->first();

        if ($existingCandidate) {
            return redirect()->back()->with('error', _lang('This member is already a candidate'));
        }

        Candidate::create([
            'election_id' => $election->id,
            'member_id' => $request->member_id,
            'name' => $request->name,
            'bio' => $request->bio,
            'manifesto' => $request->manifesto,
            'tenant_id' => app('tenant')->id,
        ]);

        return redirect()->back()->with('success', _lang('Candidate added successfully'));
    }

    /**
     * Remove candidate from election
     */
    public function removeCandidate($candidateId)
    {
        $candidate = Candidate::where('id', $candidateId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('manage', $candidate->election);

        $candidate->delete();

        return redirect()->back()->with('success', _lang('Candidate removed successfully'));
    }

    /**
     * Show security report for an election
     */
    public function securityReport($tenant, $electionId)
    {
        $election = Election::where('id', $electionId)
            ->where('tenant_id', app('tenant')->id)
            ->firstOrFail();

        $this->authorize('view', $election);

        // Generate comprehensive security report
        $blockchainReport = $this->blockchainService->generateSecurityReport($election);
        $securityReport = $this->securityService->generateSecurityReport($election);
        $policyReport = $this->policyService->generateComplianceReport($election);

        $combinedReport = array_merge($blockchainReport, [
            'security_metrics' => $securityReport['security_metrics'],
            'security_score' => $securityReport['security_score'],
            'compliance_metrics' => $policyReport['compliance_metrics'],
        ]);

        return view('backend.voting.security.report', compact('election', 'securityReport'));
    }

    /**
     * Calculate security score for vote
     */
    private function calculateSecurityScore(array $voteData)
    {
        $score = 0;
        
        // Base score
        $score += 20;
        
        // IP address validation
        if (!empty($voteData['ip_address'])) {
            $score += 15;
        }
        
        // User agent validation
        if (!empty($voteData['user_agent'])) {
            $score += 10;
        }
        
        // Device fingerprint validation
        if (!empty($voteData['device_fingerprint'])) {
            $score += 20;
        }
        
        // Geolocation validation
        if (!empty($voteData['latitude']) && !empty($voteData['longitude'])) {
            $score += 15;
        }
        
        // Time-based validation (recent vote)
        if (isset($voteData['voted_at']) && $voteData['voted_at']->isAfter(now()->subMinutes(5))) {
            $score += 10;
        }
        
        // Member validation
        if (!empty($voteData['member_id'])) {
            $score += 10;
        }
        
        return min(100, $score);
    }

    /**
     * Get vote validation rules based on election type
     */
    private function getVoteValidationRules(Election $election)
    {
        $rules = [];

        if ($election->type === 'referendum') {
            $rules['choice'] = 'required|in:yes,no,abstain';
        } else {
            if ($election->allow_abstain) {
                $rules['candidate_id'] = 'nullable|exists:candidates,id';
            } else {
                $rules['candidate_id'] = 'required|exists:candidates,id';
            }

            if ($election->voting_mechanism === 'ranked_choice') {
                $rules['rankings'] = 'nullable|array';
                $rules['rankings.*'] = 'integer|min:1';
            }
        }

        return $rules;
    }

    /**
     * Calculate vote weight for weighted voting
     */
    private function calculateVoteWeight(Member $member)
    {
        // This could be based on shares, membership duration, or other criteria
        // For now, using a simple 1.0 weight
        return 1.0;
    }

    /**
     * Log audit action
     */
    private function logAuditAction(Election $election, string $action, array $details = [], $memberId = null)
    {
        VotingAuditLog::create([
            'election_id' => $election->id,
            'member_id' => $memberId,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'tenant_id' => app('tenant')->id,
            'performed_by' => Auth::id(),
        ]);
    }
}
