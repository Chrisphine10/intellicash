<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\Transaction;
use App\Models\SavingsProduct;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TenantIsolationSecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant1;
    protected $tenant2;
    protected $user1;
    protected $user2;
    protected $member1;
    protected $member2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create two test tenants
        $this->tenant1 = Tenant::factory()->create();
        $this->tenant2 = Tenant::factory()->create();
        
        // Create users for each tenant
        $this->user1 = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'user_type' => 'admin',
        ]);
        
        $this->user2 = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'user_type' => 'admin',
        ]);
        
        // Create members for each tenant
        $this->member1 = Member::factory()->create([
            'tenant_id' => $this->tenant1->id,
        ]);
        
        $this->member2 = Member::factory()->create([
            'tenant_id' => $this->tenant2->id,
        ]);
    }

    /** @test */
    public function test_tenant_isolation_prevents_data_leakage()
    {
        // Login as user from tenant1
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Try to access member from tenant2
        $response = $this->get("/members/{$this->member2->id}");
        
        // Should be denied
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function test_global_scope_enforces_tenant_isolation()
    {
        // Login as user from tenant1
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Query members - should only see tenant1 members
        $members = Member::all();
        
        $this->assertCount(1, $members);
        $this->assertEquals($this->member1->id, $members->first()->id);
    }

    /** @test */
    public function test_transaction_model_tenant_isolation()
    {
        // Create savings accounts for each tenant
        $account1 = SavingsAccount::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'member_id' => $this->member1->id,
        ]);
        
        $account2 = SavingsAccount::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'member_id' => $this->member2->id,
        ]);

        // Create transaction for tenant1
        $transaction = Transaction::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'savings_account_id' => $account1->id,
        ]);

        // Login as user from tenant1
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Access account through transaction
        $account = $transaction->account;
        
        // Should only access tenant1's account
        $this->assertEquals($account1->id, $account->id);
        $this->assertEquals($this->tenant1->id, $account->tenant_id);
    }

    /** @test */
    public function test_superadmin_bypass_tenant_isolation()
    {
        // Create superadmin user
        $superadmin = User::factory()->create([
            'user_type' => 'superadmin',
        ]);

        Auth::login($superadmin);

        // Superadmin should be able to access all tenants' data
        $members = Member::all();
        
        $this->assertCount(2, $members);
    }

    /** @test */
    public function test_middleware_prevents_tenant_switching()
    {
        // Login as user from tenant1
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Try to access tenant2's data through API
        $response = $this->get("/api/members/{$this->member2->id}");
        
        // Should be denied
        $this->assertTrue(in_array($response->status(), [403, 404]));
    }

    /** @test */
    public function test_mass_assignment_prevents_tenant_switching()
    {
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Try to change member's tenant_id
        $response = $this->put("/members/{$this->member1->id}", [
            'first_name' => 'Updated',
            'tenant_id' => $this->tenant2->id, // Try to switch tenant
        ]);

        // Refresh member
        $this->member1->refresh();

        // Tenant ID should not change
        $this->assertEquals($this->tenant1->id, $this->member1->tenant_id);
    }

    /** @test */
    public function test_concurrent_tenant_access_isolation()
    {
        // Simulate concurrent requests from different tenants
        $responses = [];

        // Request 1: Tenant1 user
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);
        $responses[] = $this->get("/members/{$this->member1->id}");

        // Request 2: Tenant2 user
        Auth::login($this->user2);
        app()->instance('tenant', $this->tenant2);
        $responses[] = $this->get("/members/{$this->member2->id}");

        // Both should succeed
        $this->assertEquals(200, $responses[0]->status());
        $this->assertEquals(200, $responses[1]->status());
    }

    /** @test */
    public function test_database_constraints_enforce_tenant_isolation()
    {
        // Try to create member with wrong tenant_id
        $this->expectException(\Exception::class);
        
        Member::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'tenant_id' => 999, // Non-existent tenant
            'member_no' => 'TEST001',
        ]);
    }

    /** @test */
    public function test_api_tenant_isolation()
    {
        // Create API token for tenant1 user
        $token = $this->user1->createToken('test-token')->plainTextToken;

        // Make API request with token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->get('/api/members');

        // Should only return tenant1's members
        $data = $response->json();
        $this->assertCount(1, $data['data']);
        $this->assertEquals($this->member1->id, $data['data'][0]['id']);
    }

    /** @test */
    public function test_session_tenant_isolation()
    {
        // Login as tenant1 user
        Auth::login($this->user1);
        app()->instance('tenant', $this->tenant1);

        // Store tenant in session
        session(['tenant_id' => $this->tenant1->id]);

        // Verify session tenant
        $this->assertEquals($this->tenant1->id, session('tenant_id'));

        // Try to change session tenant
        session(['tenant_id' => $this->tenant2->id]);

        // Middleware should prevent this
        $response = $this->get('/members');
        
        // Should redirect or deny access
        $this->assertTrue(in_array($response->status(), [302, 403, 404]));
    }
}
