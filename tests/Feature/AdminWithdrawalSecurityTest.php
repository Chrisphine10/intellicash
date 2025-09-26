<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Currency;
use App\Models\WithdrawMethod;
use App\Models\WithdrawRequest;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

class AdminWithdrawalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $adminUser;
    protected $customerUser;
    protected $member;
    protected $savingsAccount;
    protected $withdrawMethod;
    protected $currency;
    protected $withdrawRequest;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->tenant = Tenant::factory()->create();
        $this->currency = Currency::factory()->create(['name' => 'KES']);
        
        $this->adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->customerUser = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $this->tenant->id
        ]);

        $this->member = Member::factory()->create([
            'user_id' => $this->customerUser->id,
            'tenant_id' => $this->tenant->id
        ]);

        $savingsProduct = SavingsProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $this->currency->id,
            'allow_withdraw' => 1,
            'minimum_account_balance' => 100
        ]);

        $this->savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $this->member->id,
            'savings_product_id' => $savingsProduct->id,
            'tenant_id' => $this->tenant->id
        ]);

        $this->withdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $this->currency->id,
            'status' => 1
        ]);

        $this->withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Set up tenant context
        $this->app->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_admin_approval_requires_proper_permissions()
    {
        // Create admin user without withdrawal approval permission
        $adminWithoutPermission = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminWithoutPermission);

        $response = $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Insufficient permissions to approve withdrawals');
    }

    /** @test */
    public function test_admin_cannot_approve_cross_tenant_withdrawals()
    {
        $this->actingAs($this->adminUser);

        // Create withdrawal request for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherWithdrawRequest = WithdrawRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => 0
        ]);

        $response = $this->post(route('admin.withdrawal_requests.approve', $otherWithdrawRequest->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function test_admin_approval_logs_security_events()
    {
        $this->actingAs($this->adminUser);

        Log::shouldReceive('info')
            ->once()
            ->with('Withdrawal request approval initiated', \Mockery::type('array'));

        Log::shouldReceive('info')
            ->once()
            ->with('Withdrawal request approved successfully', \Mockery::type('array'));

        $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));
    }

    /** @test */
    public function test_admin_rejection_requires_proper_permissions()
    {
        // Create admin user without withdrawal rejection permission
        $adminWithoutPermission = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminWithoutPermission);

        $response = $this->post(route('admin.withdrawal_requests.reject', $this->withdrawRequest->id), [
            'rejection_reason' => 'Test rejection',
            'approval_level' => 'standard',
            'risk_assessment' => 'low'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Insufficient permissions to reject withdrawals');
    }

    /** @test */
    public function test_admin_rejection_validates_input()
    {
        $this->actingAs($this->adminUser);

        // Test missing required fields
        $response = $this->post(route('admin.withdrawal_requests.reject', $this->withdrawRequest->id), []);

        $response->assertSessionHasErrors(['rejection_reason', 'approval_level', 'risk_assessment']);

        // Test invalid approval level
        $response = $this->post(route('admin.withdrawal_requests.reject', $this->withdrawRequest->id), [
            'rejection_reason' => 'Test rejection',
            'approval_level' => 'invalid_level',
            'risk_assessment' => 'low'
        ]);

        $response->assertSessionHasErrors(['approval_level']);

        // Test invalid risk assessment
        $response = $this->post(route('admin.withdrawal_requests.reject', $this->withdrawRequest->id), [
            'rejection_reason' => 'Test rejection',
            'approval_level' => 'standard',
            'risk_assessment' => 'invalid_risk'
        ]);

        $response->assertSessionHasErrors(['risk_assessment']);
    }

    /** @test */
    public function test_admin_cannot_access_without_authentication()
    {
        $response = $this->get(route('admin.withdrawal_requests.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function test_customer_cannot_access_admin_functions()
    {
        $this->actingAs($this->customerUser);

        $response = $this->get(route('admin.withdrawal_requests.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function test_concurrent_admin_approval_prevention()
    {
        $this->actingAs($this->adminUser);

        // Simulate concurrent approval attempts
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));
        }

        // Only first approval should succeed
        $successfulApprovals = collect($responses)->filter(function ($response) {
            return $response->status() === 302 && $response->getSession()->has('success');
        });

        $this->assertEquals(1, $successfulApprovals->count());
    }

    /** @test */
    public function test_admin_cannot_approve_already_processed_request()
    {
        $this->actingAs($this->adminUser);

        // Process the withdrawal request first
        $this->withdrawRequest->status = 2; // Approved
        $this->withdrawRequest->save();

        $response = $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function test_admin_statistics_requires_permissions()
    {
        // Create admin user without statistics permission
        $adminWithoutPermission = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminWithoutPermission);

        $response = $this->get(route('admin.withdrawal_requests.statistics'));

        $response->assertStatus(403);
    }

    /** @test */
    public function test_admin_statistics_returns_correct_data()
    {
        $this->actingAs($this->adminUser);

        // Create additional withdrawal requests with different statuses
        WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 2, // Approved
            'amount' => 500
        ]);

        WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 3, // Rejected
            'amount' => 300
        ]);

        $response = $this->get(route('admin.withdrawal_requests.statistics'));

        $response->assertStatus(200);
        $response->assertJson([
            'pending' => 1,
            'approved' => 1,
            'rejected' => 1
        ]);
    }

    /** @test */
    public function test_admin_approval_with_enhanced_validation()
    {
        $this->actingAs($this->adminUser);

        // Test approval with valid data
        $response = $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was approved
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $this->withdrawRequest->id,
            'status' => 2
        ]);
    }

    /** @test */
    public function test_admin_rejection_with_enhanced_validation()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('admin.withdrawal_requests.reject', $this->withdrawRequest->id), [
            'rejection_reason' => 'Insufficient documentation',
            'admin_notes' => 'Customer needs to provide additional bank statements',
            'approval_level' => 'manager',
            'risk_assessment' => 'medium'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was rejected
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $this->withdrawRequest->id,
            'status' => 3
        ]);
    }

    /** @test */
    public function test_admin_unauthorized_access_logging()
    {
        // Create admin user without permissions
        $adminWithoutPermission = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminWithoutPermission);

        Log::shouldReceive('warning')
            ->once()
            ->with('Unauthorized withdrawal approval attempt', \Mockery::type('array'));

        $this->post(route('admin.withdrawal_requests.approve', $this->withdrawRequest->id));
    }

    /** @test */
    public function test_admin_tenant_isolation()
    {
        $this->actingAs($this->adminUser);

        // Create another tenant's withdrawal request
        $otherTenant = Tenant::factory()->create();
        $otherWithdrawRequest = WithdrawRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => 0
        ]);

        // Should not be able to see other tenant's requests
        $response = $this->get(route('admin.withdrawal_requests.show', $otherWithdrawRequest->id));
        $response->assertStatus(404);

        // Should not be able to approve other tenant's requests
        $response = $this->post(route('admin.withdrawal_requests.approve', $otherWithdrawRequest->id));
        $response->assertStatus(404);
    }
}
