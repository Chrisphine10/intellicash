<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\VotingPosition;
use App\Models\Candidate;
use App\Models\Member;
use App\Models\Vote;
use App\Models\ElectionResult;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class VotingSystemSeeder extends Seeder
{
    public function run()
    {
        // Get the first tenant
        $tenantId = 1; // Assuming tenant ID 1 exists
        
        // Create voting positions
        $positions = [
            [
                'name' => 'Chairperson',
                'description' => 'Group leader responsible for overall management and decision making',
                'max_winners' => 1,
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Treasurer',
                'description' => 'Financial officer responsible for managing group funds and records',
                'max_winners' => 1,
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Secretary',
                'description' => 'Record keeper responsible for meeting minutes and documentation',
                'max_winners' => 1,
                'tenant_id' => $tenantId,
            ],
            [
                'name' => 'Committee Members',
                'description' => 'General committee members to assist in group management',
                'max_winners' => 3,
                'tenant_id' => $tenantId,
            ],
        ];

        foreach ($positions as $positionData) {
            VotingPosition::create($positionData);
        }

        // Get members for the tenant
        $members = Member::where('tenant_id', $tenantId)->take(8)->get();
        
        if ($members->count() < 4) {
            $this->command->warn('Not enough members found. Creating sample members...');
            
            // Create sample members if they don't exist
            for ($i = 1; $i <= 8; $i++) {
                Member::create([
                    'first_name' => "Member{$i}",
                    'last_name' => "Last{$i}",
                    'member_no' => "M{$i}000",
                    'email' => "member{$i}@example.com",
                    'mobile' => "123456789{$i}",
                    'status' => 1,
                    'tenant_id' => $tenantId,
                ]);
            }
            $members = Member::where('tenant_id', $tenantId)->get();
        }

        // Create sample elections
        $elections = [
            [
                'title' => 'Annual Leadership Election 2024',
                'description' => 'Election for key leadership positions in our VSLA group',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'public',
                'position_id' => 1, // Chairperson
                'start_date' => Carbon::now()->subDays(2),
                'end_date' => Carbon::now()->addDays(5),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => 1, // Assuming user ID 1 exists
            ],
            [
                'title' => 'Treasurer Position Election',
                'description' => 'Election for the treasurer position',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'private',
                'position_id' => 2, // Treasurer
                'start_date' => Carbon::now()->subDays(1),
                'end_date' => Carbon::now()->addDays(3),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => 1,
            ],
            [
                'title' => 'Committee Members Selection',
                'description' => 'Election for 3 committee members',
                'type' => 'multi_position',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'hybrid',
                'position_id' => 4, // Committee Members
                'start_date' => Carbon::now()->subHours(6),
                'end_date' => Carbon::now()->addDays(2),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => 1,
            ],
            [
                'title' => 'Fund Allocation Proposal',
                'description' => 'Should we allocate 20% of our savings to emergency fund?',
                'type' => 'referendum',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'public',
                'position_id' => null,
                'start_date' => Carbon::now()->subHours(2),
                'end_date' => Carbon::now()->addDays(1),
                'status' => 'active',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => 1,
            ],
            [
                'title' => 'Previous Election Results',
                'description' => 'Completed election for secretary position',
                'type' => 'single_winner',
                'voting_mechanism' => 'majority',
                'privacy_mode' => 'public',
                'position_id' => 3, // Secretary
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->subDays(3),
                'status' => 'closed',
                'allow_abstain' => true,
                'weighted_voting' => false,
                'tenant_id' => $tenantId,
                'created_by' => 1,
            ],
        ];

        foreach ($elections as $electionData) {
            $election = Election::create($electionData);
            
            // Add candidates for non-referendum elections
            if ($election->type !== 'referendum') {
                $candidateCount = $election->type === 'multi_position' ? 5 : 3;
                $selectedMembers = $members->random(min($candidateCount, $members->count()));
                
                foreach ($selectedMembers as $index => $member) {
                    Candidate::create([
                        'election_id' => $election->id,
                        'member_id' => $member->id,
                        'name' => "{$member->first_name} {$member->last_name}",
                        'bio' => "Experienced member with " . ($index + 1) . " years in the group",
                        'manifesto' => "I promise to work hard for the benefit of all members and ensure transparent management.",
                        'order' => $index + 1,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
        }

        // Create sample votes for closed election
        $closedElection = Election::where('status', 'closed')->first();
        if ($closedElection) {
            $candidates = $closedElection->candidates;
            $votingMembers = $members->take(6); // 6 members voted
            
            foreach ($votingMembers as $index => $member) {
                if ($index < 4) {
                    // 4 members voted for first candidate
                    Vote::create([
                        'election_id' => $closedElection->id,
                        'member_id' => $member->id,
                        'candidate_id' => $candidates->first()->id,
                        'voted_at' => Carbon::now()->subDays(5),
                        'tenant_id' => $tenantId,
                    ]);
                } else {
                    // 2 members voted for second candidate
                    Vote::create([
                        'election_id' => $closedElection->id,
                        'member_id' => $member->id,
                        'candidate_id' => $candidates->skip(1)->first()->id,
                        'voted_at' => Carbon::now()->subDays(4),
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
            
            // Create results for closed election
            $totalVotes = $closedElection->votes()->count();
            $candidate1Votes = $closedElection->votes()->where('candidate_id', $candidates->first()->id)->count();
            $candidate2Votes = $closedElection->votes()->where('candidate_id', $candidates->skip(1)->first()->id)->count();
            
            ElectionResult::create([
                'election_id' => $closedElection->id,
                'candidate_id' => $candidates->first()->id,
                'total_votes' => $candidate1Votes,
                'percentage' => ($candidate1Votes / $totalVotes) * 100,
                'rank' => 1,
                'is_winner' => true,
                'tenant_id' => $tenantId,
            ]);
            
            ElectionResult::create([
                'election_id' => $closedElection->id,
                'candidate_id' => $candidates->skip(1)->first()->id,
                'total_votes' => $candidate2Votes,
                'percentage' => ($candidate2Votes / $totalVotes) * 100,
                'rank' => 2,
                'is_winner' => false,
                'tenant_id' => $tenantId,
            ]);
        }

        $this->command->info('Voting system sample data created successfully!');
        $this->command->info('Created:');
        $this->command->info('- 4 Voting Positions');
        $this->command->info('- 5 Elections (4 active, 1 closed)');
        $this->command->info('- Multiple Candidates');
        $this->command->info('- Sample Votes and Results');
    }
}
