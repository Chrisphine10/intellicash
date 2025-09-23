<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Election;
use App\Models\Vote;
use App\Models\Member;
use App\Models\VotingPosition;
use App\Models\Candidate;
use App\Models\User;
use App\Services\BlockchainVotingService;
use App\Services\MilitaryGradeVotingSecurity;
use App\Services\VotingPolicyEnforcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

class VotingSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $adminUser;
    protected $member;
    protected $election;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 1,
        ]);

        // Create admin user
        $this->adminUser = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'user_type' => 'admin',
            'status' => 1,
        ]);

        // Create test member
        $this->member = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Member',
            'member_no' => 'TM001',
            'email' => 'member@test.com',
            'mobile' => '1234567890',
            'status' => 1,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->adminUser->id,
        ]);

        // Create voting position
        $position = VotingPosition::create([
            'name' => 'Test Position',
            'description' => 'Test position for security testing',
            'max_winners' => 1,
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test election
        $this->election = Election::create([
            'title' => 'Security Test Election',
            'description' => 'Election for security testing',
            'type' => 'single_winner',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'position_id' => $position->id,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
            'status' => 'active',
            'allow_abstain' => true,
            'weighted_voting' => true,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->adminUser->id,
        ]);

        // Create candidate
        Candidate::create([
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'name' => 'Test Candidate',
            'bio' => 'Test candidate bio',
            'manifesto' => 'Test manifesto',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function it_creates_vote_with_blockchain_security()
    {
        $blockchainService = new BlockchainVotingService();
        
        $vote = Vote::create([
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'candidate_id' => $this->election->candidates->first()->id,
            'choice' => 'candidate',
            'is_abstain' => false,
            'voted_at' => now(),
            'tenant_id' => $this->tenant->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Test Browser',
            'device_fingerprint' => hash('sha256', 'test_device'),
            'security_score' => 95,
        ]);

        $blockchainHash = $blockchainService->createVoteBlock($vote, $this->election);
        
        $this->assertNotNull($blockchainHash);
        $this->assertTrue($vote->fresh()->is_verified);
        $this->assertNotNull($vote->fresh()->blockchain_hash);
        $this->assertNotNull($vote->fresh()->encrypted_data);
    }

    /** @test */
    public function it_verifies_vote_blockchain_integrity()
    {
        $blockchainService = new BlockchainVotingService();
        
        $vote = Vote::create([
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'candidate_id' => $this->election->candidates->first()->id,
            'choice' => 'candidate',
            'is_abstain' => false,
            'voted_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $blockchainService->createVoteBlock($vote, $this->election);
        
        $isValid = $blockchainService->verifyVote($vote->fresh());
        
        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_enforces_military_grade_security()
    {
        $securityService = new MilitaryGradeVotingSecurity();
        
        $request = new \Illuminate\Http\Request();
        $request->headers->set('X-Device-Fingerprint', hash('sha256', 'test_device'));
        $request->headers->set('X-Latitude', '40.7128');
        $request->headers->set('X-Longitude', '-74.0060');
        
        $result = $securityService->validateVoteSecurity($request, $this->election, $this->member);
        
        $this->assertTrue($result['is_secure']);
        $this->assertGreaterThan(0, $result['security_score']);
    }

    /** @test */
    public function it_enforces_voting_policies()
    {
        $policyService = new VotingPolicyEnforcement();
        
        $voteData = [
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'choice' => 'candidate',
            'candidate_id' => $this->election->candidates->first()->id,
        ];
        
        $result = $policyService->enforceVotingPolicies($this->election, $this->member, $voteData);
        
        $this->assertTrue($result['all_policies_passed']);
        $this->assertGreaterThan(0, $result['compliance_score']);
    }

    /** @test */
    public function it_prevents_duplicate_votes()
    {
        // Create first vote
        Vote::create([
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'candidate_id' => $this->election->candidates->first()->id,
            'choice' => 'candidate',
            'is_abstain' => false,
            'voted_at' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $policyService = new VotingPolicyEnforcement();
        
        $voteData = [
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'choice' => 'candidate',
            'candidate_id' => $this->election->candidates->first()->id,
        ];
        
        $result = $policyService->enforceVotingPolicies($this->election, $this->member, $voteData);
        
        $this->assertFalse($result['all_policies_passed']);
        $this->assertStringContains('already voted', $result['policies']['member_eligibility']['message']);
    }

    /** @test */
    public function it_validates_voting_time_window()
    {
        // Create election outside voting window
        $pastElection = Election::create([
            'title' => 'Past Election',
            'description' => 'Election that has ended',
            'type' => 'single_winner',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'position_id' => $this->election->position_id,
            'start_date' => now()->subDays(2),
            'end_date' => now()->subDay(),
            'status' => 'closed',
            'allow_abstain' => true,
            'weighted_voting' => false,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->adminUser->id,
        ]);

        $policyService = new VotingPolicyEnforcement();
        
        $voteData = [
            'election_id' => $pastElection->id,
            'member_id' => $this->member->id,
            'choice' => 'candidate',
            'candidate_id' => $this->election->candidates->first()->id,
        ];
        
        $result = $policyService->enforceVotingPolicies($pastElection, $this->member, $voteData);
        
        $this->assertFalse($result['all_policies_passed']);
        $this->assertStringContains('not active', $result['policies']['election_status']['message']);
    }

    /** @test */
    public function it_generates_security_report()
    {
        // Create some test votes
        for ($i = 0; $i < 3; $i++) {
            Vote::create([
                'election_id' => $this->election->id,
                'member_id' => $this->member->id,
                'candidate_id' => $this->election->candidates->first()->id,
                'choice' => 'candidate',
                'is_abstain' => false,
                'voted_at' => now()->subMinutes($i * 10),
                'tenant_id' => $this->tenant->id,
                'blockchain_hash' => hash('sha256', 'test_hash_' . $i),
                'is_verified' => true,
                'security_score' => 90 + $i,
            ]);
        }

        $blockchainService = new BlockchainVotingService();
        $securityService = new MilitaryGradeVotingSecurity();
        
        $blockchainReport = $blockchainService->generateSecurityReport($this->election);
        $securityReport = $securityService->generateSecurityReport($this->election);
        
        $this->assertArrayHasKey('total_votes', $blockchainReport['blockchain_verification']);
        $this->assertArrayHasKey('valid_votes', $blockchainReport['blockchain_verification']);
        $this->assertArrayHasKey('security_score', $securityReport);
        $this->assertGreaterThan(0, $blockchainReport['blockchain_verification']['total_votes']);
    }

    /** @test */
    public function it_handles_rate_limiting()
    {
        $securityService = new MilitaryGradeVotingSecurity();
        
        $request = new \Illuminate\Http\Request();
        $request->headers->set('X-Device-Fingerprint', hash('sha256', 'test_device'));
        
        // Simulate multiple rapid vote attempts
        for ($i = 0; $i < 5; $i++) {
            $result = $securityService->validateVoteSecurity($request, $this->election, $this->member);
            
            if ($i < 3) {
                $this->assertTrue($result['is_secure']);
            } else {
                $this->assertFalse($result['is_secure']);
                $this->assertStringContains('Too many vote attempts', 
                    collect($result['checks'])->where('passed', false)->first()['message']);
                break;
            }
        }
    }

    /** @test */
    public function it_validates_device_fingerprint()
    {
        $securityService = new MilitaryGradeVotingSecurity();
        
        $request = new \Illuminate\Http\Request();
        // No device fingerprint provided
        
        $result = $securityService->validateVoteSecurity($request, $this->election, $this->member);
        
        $this->assertFalse($result['is_secure']);
        $this->assertStringContains('Device fingerprint required', 
            collect($result['checks'])->where('passed', false)->first()['message']);
    }

    /** @test */
    public function it_encrypts_vote_data()
    {
        $blockchainService = new BlockchainVotingService();
        
        $voteData = [
            'election_id' => $this->election->id,
            'member_id' => $this->member->id,
            'candidate_id' => $this->election->candidates->first()->id,
            'voted_at' => now()->toISOString(),
            'tenant_id' => $this->tenant->id,
        ];
        
        $encryptedData = $blockchainService->encryptVoteData($voteData);
        $decryptedData = $blockchainService->decryptVoteData($encryptedData);
        
        $this->assertNotEquals(json_encode($voteData), $encryptedData);
        $this->assertEquals($voteData, $decryptedData);
    }

    /** @test */
    public function it_calculates_merkle_tree()
    {
        $blockchainService = new BlockchainVotingService();
        
        // Create multiple votes
        $hashes = [];
        for ($i = 0; $i < 4; $i++) {
            $vote = Vote::create([
                'election_id' => $this->election->id,
                'member_id' => $this->member->id,
                'candidate_id' => $this->election->candidates->first()->id,
                'choice' => 'candidate',
                'is_abstain' => false,
                'voted_at' => now()->subMinutes($i * 5),
                'tenant_id' => $this->tenant->id,
                'blockchain_hash' => hash('sha256', 'test_hash_' . $i),
            ]);
            $hashes[] = $vote->blockchain_hash;
        }
        
        $merkleRoot = $blockchainService->generateMerkleTree($this->election);
        
        $this->assertNotNull($merkleRoot);
        $this->assertEquals(64, strlen($merkleRoot)); // SHA-256 hash length
    }
}
