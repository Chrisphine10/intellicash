<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\VotingPosition;
use App\Models\Candidate;
use App\Models\Member;
use App\Models\Vote;
use App\Models\ElectionResult;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VotingSecurityTestSeeder extends Seeder
{
    public function run()
    {
        // Get the first tenant
        $tenant = \App\Models\Tenant::first();
        
        if (!$tenant) {
            $this->command->info('No tenants found. Skipping voting security test seeding.');
            return;
        }
        
        $tenantId = $tenant->id;
        
        // Create test admin user if not exists
        $adminUser = User::firstOrCreate(
            ['email' => 'voting-admin@test.com'],
            [
                'name' => 'Voting Test Admin',
                'password' => bcrypt('password123'),
                'user_type' => 'admin',
                'status' => 1,
            ]
        );

        // Create voting positions
        $positions = [
            [
                'name' => 'Chairperson',
                'description' => 'Group leader responsible for overall management and decision making',
                'max_winners' => 1,
                'is_active' => true,
                'required_role' => 'chairperson',
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Treasurer',
                'description' => 'Financial officer responsible for managing group funds and records',
                'max_winners' => 1,
                'is_active' => true,
                'required_role' => 'treasurer',
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Secretary',
                'description' => 'Record keeper responsible for meeting minutes and documentation',
                'max_winners' => 1,
                'is_active' => true,
                'required_role' => 'secretary',
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Committee Members',
                'description' => 'General committee members to assist in group management',
                'max_winners' => 3,
                'is_active' => true,
                'required_role' => null,
                'tenant_id' => $tenantId,
            ],
        ];

        $createdPositions = [];
        foreach ($positions as $positionData) {
            $position = VotingPosition::create($positionData);
            $createdPositions[] = $position;
        }

        // Create test members
        $members = [];
        for ($i = 1; $i <= 10; $i++) {
            $member = Member::create([
                'first_name' => "TestMember{$i}",
                'last_name' => "LastName{$i}",
                'member_no' => "TM{$i}000",
                'email' => "member{$i}@test.com",
                'mobile' => "123456789{$i}",
                'status' => 1,
                'tenant_id' => $tenantId,
                'is_vsla_chairperson' => $i === 1,
                'is_vsla_treasurer' => $i === 2,
                'is_vsla_secretary' => $i === 3,
            ]);
            $members[] = $member;
        }

        // Create test elections with different security configurations
        $elections = [
            [
                'title' => 'Secure Leadership Election 2024',
                'description' => 'High-security election for key leadership positions with blockchain verification',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'private',
                'position_id' => $createdPositions[0]->id, // Chairperson
                'start_date' => Carbon::now()->subDays(1),
                'end_date' => Carbon::now()->addDays(5),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => true,
                'tenant_id' => $tenantId,
                'created_by' => $adminUser->id,
            ],
            [
                'title' => 'Treasurer Position - Military Grade Security',
                'description' => 'Election with military-grade security features and comprehensive audit trail',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'hybrid',
                'position_id' => $createdPositions[1]->id, // Treasurer
                'start_date' => Carbon::now()->subHours(6),
                'end_date' => Carbon::now()->addDays(2),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => $adminUser->id,
            ],
            [
                'title' => 'Committee Members - Blockchain Verified',
                'description' => 'Multi-position election with blockchain verification and ranked choice voting',
                'type' => 'multi_position',
                'voting_mechanism' => 'ranked_choice',
                'privacy_mode' => 'public',
                'position_id' => $createdPositions[3]->id, // Committee Members
                'start_date' => Carbon::now()->subHours(2),
                'end_date' => Carbon::now()->addDays(1),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => $adminUser->id,
            ],
            [
                'title' => 'Fund Allocation Proposal - Public Vote',
                'description' => 'Should we allocate 25% of our savings to emergency fund?',
                'type' => 'referendum',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'public',
                'position_id' => null,
                'start_date' => Carbon::now()->subHours(1),
                'end_date' => Carbon::now()->addHours(23),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => $adminUser->id,
            ],
            [
                'title' => 'Completed Election - Security Test',
                'description' => 'Completed election for testing security verification and audit features',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'private',
                'position_id' => $createdPositions[2]->id, // Secretary
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->subDays(3),
                'status' => 'closed',
                'allow_abstain' => true,
                'weighted_voting' => true,
                'tenant_id' => $tenantId,
                'created_by' => $adminUser->id,
            ],
        ];

        foreach ($elections as $electionData) {
            $election = Election::create($electionData);
            
            // Add candidates for non-referendum elections
            if ($election->type !== 'referendum') {
                $candidateCount = $election->type === 'multi_position' ? 6 : 3;
                $selectedMembers = collect($members)->random(min($candidateCount, count($members)));
                
                foreach ($selectedMembers as $index => $member) {
                    Candidate::create([
                        'election_id' => $election->id,
                        'member_id' => $member->id,
                        'name' => "{$member->first_name} {$member->last_name}",
                        'bio' => "Experienced member with " . ($index + 1) . " years in the group",
                        'manifesto' => "I promise to work hard for the benefit of all members and ensure transparent management with blockchain security.",
                        'order' => $index + 1,
                        'is_active' => true,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
        }

        // Create test votes for completed election with security features
        $completedElection = Election::where('status', 'closed')->first();
        if ($completedElection) {
            $candidates = $completedElection->candidates;
            $votingMembers = collect($members)->take(8); // 8 members voted
            
            foreach ($votingMembers as $index => $member) {
                $candidate = $candidates->random();
                
                $vote = Vote::create([
                    'election_id' => $completedElection->id,
                    'member_id' => $member->id,
                    'candidate_id' => $candidate->id,
                    'choice' => 'candidate',
                    'is_abstain' => false,
                    'weight' => $completedElection->weighted_voting ? rand(1, 5) : 1,
                    'voted_at' => Carbon::now()->subDays(5)->addMinutes($index * 10),
                    'tenant_id' => $tenantId,
                    'ip_address' => '192.168.1.' . (100 + $index),
                    'user_agent' => 'Mozilla/5.0 (Test Browser)',
                    'device_fingerprint' => hash('sha256', 'test_device_' . $member->id),
                    'latitude' => 40.7128 + (rand(-10, 10) / 100),
                    'longitude' => -74.0060 + (rand(-10, 10) / 100),
                    'security_score' => rand(80, 100),
                    'is_verified' => true,
                    'verification_timestamp' => Carbon::now()->subDays(5)->addMinutes($index * 10),
                ]);

                // Generate blockchain hash for the vote
                $blockchainHash = hash('sha256', 
                    $vote->election_id . 
                    $vote->member_id . 
                    $vote->candidate_id . 
                    $vote->voted_at->timestamp . 
                    $vote->tenant_id . 
                    config('app.key')
                );
                
                $vote->update([
                    'blockchain_hash' => $blockchainHash,
                    'digital_signature' => hash_hmac('sha256', $blockchainHash, config('app.key')),
                ]);
            }
            
            // Create results for completed election
            $totalVotes = $completedElection->votes()->count();
            $candidateVotes = $completedElection->votes()
                ->selectRaw('candidate_id, COUNT(*) as vote_count, SUM(weight) as total_weight')
                ->groupBy('candidate_id')
                ->orderBy('vote_count', 'desc')
                ->get();

            foreach ($candidateVotes as $index => $candidateVote) {
                ElectionResult::create([
                    'election_id' => $completedElection->id,
                    'candidate_id' => $candidateVote->candidate_id,
                    'total_votes' => $candidateVote->vote_count,
                    'percentage' => ($candidateVote->vote_count / $totalVotes) * 100,
                    'rank' => $index + 1,
                    'is_winner' => $index === 0,
                    'tenant_id' => $tenantId,
                ]);
            }
        }

        $this->command->info('Voting security test data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- 4 Voting Positions with role requirements');
        $this->command->info('- 10 Test Members with proper roles');
        $this->command->info('- 5 Elections with different security configurations');
        $this->command->info('- Multiple Candidates with security features');
        $this->command->info('- Test Votes with blockchain hashes and security scores');
        $this->command->info('- Election Results with proper calculations');
    }
}
