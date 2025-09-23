<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\VotingPosition;
use App\Models\Member;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VotingTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $member;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a tenant
        $this->tenant = Tenant::factory()->create([
            'vsla_enabled' => true,
        ]);
        
        // Create a user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'admin',
        ]);
        
        // Create a member
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        
        // Set tenant context
        session(['tenant_id' => $this->tenant->id]);
    }

    public function test_can_create_voting_position()
    {
        $this->actingAs($this->user);
        
        $positionData = [
            'name' => 'Chairperson',
            'description' => 'Group leader position',
            'max_winners' => 1,
        ];
        
        $response = $this->post(route('voting.positions.store'), $positionData);
        
        $response->assertRedirect(route('voting.positions.index'));
        $this->assertDatabaseHas('voting_positions', [
            'name' => 'Chairperson',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_create_election()
    {
        $this->actingAs($this->user);
        
        // Create a position first
        $position = VotingPosition::create([
            'name' => 'Chairperson',
            'description' => 'Group leader',
            'max_winners' => 1,
            'tenant_id' => $this->tenant->id,
        ]);
        
        $electionData = [
            'title' => 'Election for Chairperson',
            'description' => 'Annual election for group chairperson',
            'type' => 'single_winner',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'position_id' => $position->id,
            'start_date' => now()->addHour()->format('Y-m-d\TH:i'),
            'end_date' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'allow_abstain' => true,
            'weighted_voting' => false,
        ];
        
        $response = $this->post(route('voting.elections.store'), $electionData);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('elections', [
            'title' => 'Election for Chairperson',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_can_start_election()
    {
        $this->actingAs($this->user);
        
        $election = Election::create([
            'title' => 'Test Election',
            'type' => 'referendum',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'start_date' => now()->addHour(),
            'end_date' => now()->addDays(7),
            'status' => 'draft',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        
        $response = $this->post(route('voting.elections.start', $election->id));
        
        $response->assertRedirect();
        $this->assertDatabaseHas('elections', [
            'id' => $election->id,
            'status' => 'active',
        ]);
    }

    public function test_can_vote_in_election()
    {
        $this->actingAs($this->user);
        
        $election = Election::create([
            'title' => 'Test Referendum',
            'type' => 'referendum',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(7),
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        
        $voteData = [
            'choice' => 'yes',
        ];
        
        $response = $this->post(route('voting.vote.submit', $election->id), $voteData);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'member_id' => $this->member->id,
            'choice' => 'yes',
        ]);
    }

    public function test_cannot_vote_twice()
    {
        $this->actingAs($this->user);
        
        $election = Election::create([
            'title' => 'Test Referendum',
            'type' => 'referendum',
            'voting_mechanism' => 'majority',
            'privacy_mode' => 'private',
            'start_date' => now()->subHour(),
            'end_date' => now()->addDays(7),
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        
        // First vote
        $this->post(route('voting.vote.submit', $election->id), ['choice' => 'yes']);
        
        // Second vote should fail
        $response = $this->post(route('voting.vote.submit', $election->id), ['choice' => 'no']);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('votes', [
            'election_id' => $election->id,
            'member_id' => $this->member->id,
            'choice' => 'yes',
        ]);
        
        // Should only have one vote
        $this->assertEquals(1, $election->votes()->count());
    }
}
