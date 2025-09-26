<?php

namespace Tests\Performance;

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

class AdminPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $adminUser;
    protected $member;
    protected $savingsAccount;
    protected $withdrawMethod;
    protected $currency;

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

        $customerUser = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $this->tenant->id
        ]);

        $this->member = Member::factory()->create([
            'user_id' => $customerUser->id,
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

        // Set up tenant context
        $this->app->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_admin_approval_performance()
    {
        $this->actingAs($this->adminUser);

        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $startTime = microtime(true);
        
        $response = $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 2 seconds
        $this->assertLessThan(2, $executionTime);
        $response->assertRedirect();
    }

    /** @test */
    public function test_admin_rejection_performance()
    {
        $this->actingAs($this->adminUser);

        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $startTime = microtime(true);
        
        $response = $this->post(route('admin.withdrawal_requests.reject', $withdrawRequest->id), [
            'rejection_reason' => 'Test rejection',
            'approval_level' => 'standard',
            'risk_assessment' => 'low'
        ]);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 2 seconds
        $this->assertLessThan(2, $executionTime);
        $response->assertRedirect();
    }

    /** @test */
    public function test_admin_index_performance()
    {
        $this->actingAs($this->adminUser);

        // Create multiple withdrawal requests
        WithdrawRequest::factory()->count(50)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $startTime = microtime(true);
        
        $response = $this->get(route('admin.withdrawal_requests.index'));
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 1 second
        $this->assertLessThan(1, $executionTime);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_statistics_performance()
    {
        $this->actingAs($this->adminUser);

        // Create multiple withdrawal requests with different statuses
        WithdrawRequest::factory()->count(100)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        WithdrawRequest::factory()->count(100)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 2
        ]);

        WithdrawRequest::factory()->count(100)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 3
        ]);

        $startTime = microtime(true);
        
        $response = $this->get(route('admin.withdrawal_requests.statistics'));
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 1 second
        $this->assertLessThan(1, $executionTime);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_concurrent_admin_operations()
    {
        $this->actingAs($this->adminUser);

        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $responses = [];
        
        // Simulate 10 concurrent admin operations
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));
        }

        // Only one should succeed due to locking
        $successfulOperations = collect($responses)->filter(function ($response) {
            return $response->status() === 302 && $response->getSession()->has('success');
        });

        $this->assertEquals(1, $successfulOperations->count());
    }

    /** @test */
    public function test_admin_memory_usage()
    {
        $this->actingAs($this->adminUser);

        // Create large number of withdrawal requests
        WithdrawRequest::factory()->count(1000)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $memoryBefore = memory_get_usage();
        
        $response = $this->get(route('admin.withdrawal_requests.index'));
        
        $memoryAfter = memory_get_usage();
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Should not use more than 50MB
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_database_query_performance()
    {
        $this->actingAs($this->adminUser);

        // Create withdrawal requests
        WithdrawRequest::factory()->count(100)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Enable query logging
        \DB::enableQueryLog();

        $response = $this->get(route('admin.withdrawal_requests.index'));

        $queries = \DB::getQueryLog();
        
        // Should not execute more than 5 queries
        $this->assertLessThanOrEqual(5, count($queries));
        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_pagination_performance()
    {
        $this->actingAs($this->adminUser);

        // Create large number of withdrawal requests
        WithdrawRequest::factory()->count(500)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $startTime = microtime(true);
        
        // Test pagination
        $response = $this->get(route('admin.withdrawal_requests.index') . '?page=10');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 1 second even for high page numbers
        $this->assertLessThan(1, $executionTime);
        $response->assertStatus(200);
    }

    /** @test */
    public function test_admin_bulk_operations_performance()
    {
        $this->actingAs($this->adminUser);

        // Create multiple withdrawal requests
        $withdrawRequests = WithdrawRequest::factory()->count(20)->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $startTime = microtime(true);
        
        // Process multiple approvals
        foreach ($withdrawRequests as $request) {
            $this->post(route('admin.withdrawal_requests.approve', $request->id));
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 10 seconds for 20 operations
        $this->assertLessThan(10, $executionTime);
    }

    /** @test */
    public function test_admin_search_performance()
    {
        $this->actingAs($this->adminUser);

        // Create withdrawal requests with different member names
        for ($i = 0; $i < 100; $i++) {
            $member = Member::factory()->create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'Test' . $i,
                'last_name' => 'Member' . $i
            ]);

            WithdrawRequest::factory()->create([
                'member_id' => $member->id,
                'method_id' => $this->withdrawMethod->id,
                'debit_account_id' => $this->savingsAccount->id,
                'tenant_id' => $this->tenant->id,
                'status' => 0
            ]);
        }

        $startTime = microtime(true);
        
        // Test search functionality
        $response = $this->get(route('admin.withdrawal_requests.index') . '?search=Test50');
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete within 1 second
        $this->assertLessThan(1, $executionTime);
        $response->assertStatus(200);
    }
}
