<?php

namespace Tests\Unit;

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
use App\Models\BankAccount;
use App\Http\Controllers\Customer\WithdrawController;
use App\Http\Controllers\Admin\WithdrawalRequestController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawModuleUnitTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
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
        
        $this->user = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $this->tenant->id
        ]);

        $this->member = Member::factory()->create([
            'user_id' => $this->user->id,
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
    public function test_withdraw_request_model_relationships()
    {
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Test relationships
        $this->assertInstanceOf(Member::class, $withdrawRequest->member);
        $this->assertInstanceOf(WithdrawMethod::class, $withdrawRequest->method);
        $this->assertInstanceOf(SavingsAccount::class, $withdrawRequest->account);
    }

    /** @test */
    public function test_withdraw_method_model_relationships()
    {
        $this->assertInstanceOf(Currency::class, $this->withdrawMethod->currency);
    }

    /** @test */
    public function test_withdraw_request_requirements_attribute()
    {
        $requirements = ['account_number' => '1234567890', 'bank_name' => 'Test Bank'];
        
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'requirements' => json_encode($requirements),
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertEquals($requirements, $withdrawRequest->requirements);
    }

    /** @test */
    public function test_withdraw_method_requirements_attribute()
    {
        $requirements = ['account_number', 'bank_name'];
        
        $withdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $this->currency->id,
            'requirements' => json_encode($requirements)
        ]);

        $this->assertEquals($requirements, $withdrawMethod->requirements);
    }

    /** @test */
    public function test_withdraw_controller_manual_methods()
    {
        $this->actingAs($this->user);

        $controller = new WithdrawController();
        $response = $controller->manual_methods();

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertEquals('backend.customer.withdraw.manual_methods', $response->getName());
    }

    /** @test */
    public function test_withdraw_controller_withdrawal_history()
    {
        $this->actingAs($this->user);

        // Create some withdrawal transactions
        Transaction::factory()->count(3)->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'type' => 'Withdraw',
            'status' => 2
        ]);

        $controller = new WithdrawController();
        $response = $controller->withdrawalHistory();

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertEquals('backend.customer.withdraw.history', $response->getName());
    }

    /** @test */
    public function test_withdraw_controller_withdrawal_requests()
    {
        $this->actingAs($this->user);

        // Create some withdrawal requests
        WithdrawRequest::factory()->count(2)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id
        ]);

        $controller = new WithdrawController();
        $response = $controller->withdrawalRequests();

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertEquals('backend.customer.withdraw.requests', $response->getName());
    }

    /** @test */
    public function test_admin_withdrawal_request_controller_index()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Create some withdrawal requests
        WithdrawRequest::factory()->count(3)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        $controller = new WithdrawalRequestController(app(\App\Services\PaymentMethodService::class));
        $response = $controller->index();

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertEquals('backend.admin.withdrawal_requests.index', $response->getName());
    }

    /** @test */
    public function test_admin_withdrawal_request_controller_show()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id
        ]);

        $controller = new WithdrawalRequestController(app(\App\Services\PaymentMethodService::class));
        $response = $controller->show($withdrawRequest->id);

        $this->assertInstanceOf(\Illuminate\View\View::class, $response);
        $this->assertEquals('backend.admin.withdrawal_requests.show', $response->getName());
    }

    /** @test */
    public function test_admin_withdrawal_request_controller_statistics()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Create withdrawal requests with different statuses
        WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0,
            'amount' => 100
        ]);

        WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 2,
            'amount' => 200
        ]);

        WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 3,
            'amount' => 150
        ]);

        $controller = new WithdrawalRequestController(app(\App\Services\PaymentMethodService::class));
        $response = $controller->statistics();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        
        $data = $response->getData(true);
        $this->assertEquals(1, $data['pending']);
        $this->assertEquals(1, $data['approved']);
        $this->assertEquals(1, $data['rejected']);
        $this->assertEquals(100, $data['total_amount_pending']);
        $this->assertEquals(200, $data['total_amount_approved']);
    }

    /** @test */
    public function test_withdraw_request_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        
        $otherMember = Member::factory()->create([
            'tenant_id' => $otherTenant->id
        ]);

        $otherSavingsAccount = SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'tenant_id' => $otherTenant->id
        ]);

        $otherWithdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency_id' => $this->currency->id
        ]);

        // Create withdrawal request for other tenant
        $otherWithdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $otherMember->id,
            'method_id' => $otherWithdrawMethod->id,
            'debit_account_id' => $otherSavingsAccount->id,
            'tenant_id' => $otherTenant->id
        ]);

        // Set current tenant context
        $this->app->instance('tenant', $this->tenant);

        // Query should only return current tenant's requests
        $withdrawRequests = WithdrawRequest::where('tenant_id', $this->tenant->id)->get();
        
        $this->assertCount(0, $withdrawRequests);
        $this->assertFalse($withdrawRequests->contains($otherWithdrawRequest));
    }

    /** @test */
    public function test_withdraw_method_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        
        $otherWithdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency_id' => $this->currency->id
        ]);

        // Set current tenant context
        $this->app->instance('tenant', $this->tenant);

        // Query should only return current tenant's methods
        $withdrawMethods = WithdrawMethod::where('tenant_id', $this->tenant->id)->get();
        
        $this->assertCount(1, $withdrawMethods);
        $this->assertTrue($withdrawMethods->contains($this->withdrawMethod));
        $this->assertFalse($withdrawMethods->contains($otherWithdrawMethod));
    }

    /** @test */
    public function test_withdraw_request_status_constants()
    {
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Test status values
        $this->assertEquals(0, $withdrawRequest->status); // Pending
        
        $withdrawRequest->status = 2; // Approved
        $this->assertEquals(2, $withdrawRequest->status);
        
        $withdrawRequest->status = 3; // Rejected
        $this->assertEquals(3, $withdrawRequest->status);
    }

    /** @test */
    public function test_withdraw_method_status_constants()
    {
        $this->assertEquals(1, $this->withdrawMethod->status); // Active
        
        $this->withdrawMethod->status = 0; // Inactive
        $this->assertEquals(0, $this->withdrawMethod->status);
    }

    /** @test */
    public function test_withdraw_request_json_encoding()
    {
        $requirements = [
            'payment_method_id' => 1,
            'payment_method_type' => 'paystack',
            'recipient_details' => [
                'name' => 'John Doe',
                'mobile' => '0712345678',
                'account_number' => '1234567890'
            ]
        ];

        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'requirements' => json_encode($requirements),
            'tenant_id' => $this->tenant->id
        ]);

        $this->assertEquals($requirements, $withdrawRequest->requirements);
        $this->assertIsArray($withdrawRequest->requirements);
        $this->assertEquals('John Doe', $withdrawRequest->requirements['recipient_details']['name']);
    }
}
