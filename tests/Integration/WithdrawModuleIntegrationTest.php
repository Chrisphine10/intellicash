<?php

namespace Tests\Integration;

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
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Notification;

class WithdrawModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $member;
    protected $savingsAccount;
    protected $withdrawMethod;
    protected $currency;
    protected $bankAccount;

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

        $this->bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method_type' => 'paystack',
            'is_active' => true
        ]);

        // Set up tenant context
        $this->app->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_complete_withdrawal_workflow()
    {
        $this->actingAs($this->user);

        // Create initial balance
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // Step 1: Create withdrawal request
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 200,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Test withdrawal'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was created
        $withdrawRequest = WithdrawRequest::where('member_id', $this->member->id)->first();
        $this->assertNotNull($withdrawRequest);
        $this->assertEquals(200, $withdrawRequest->amount);
        $this->assertEquals(0, $withdrawRequest->status); // Pending

        // Verify transaction was created
        $transaction = Transaction::where('member_id', $this->member->id)
            ->where('type', 'Withdraw')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(0, $transaction->status); // Pending

        // Step 2: Admin approval
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Mock payment method service
        $paymentService = $this->mock(PaymentMethodService::class);
        $paymentService->shouldReceive('processWithdrawal')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['transaction_id' => 'TXN123'],
                'message' => 'Withdrawal processed successfully'
            ]);

        $this->app->instance(PaymentMethodService::class, $paymentService);

        $response = $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was approved
        $withdrawRequest->refresh();
        $this->assertEquals(2, $withdrawRequest->status); // Approved

        // Verify transaction was completed
        $transaction->refresh();
        $this->assertEquals(2, $transaction->status); // Completed
    }

    /** @test */
    public function test_payment_method_withdrawal_workflow()
    {
        $this->actingAs($this->user);

        // Create initial balance
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // Create withdrawal request via payment method
        $response = $this->post(route('withdraw.manual_withdraw', 'payment_' . $this->bankAccount->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 300,
            'recipient_name' => 'John Doe',
            'recipient_mobile' => '0712345678',
            'recipient_account' => '1234567890',
            'recipient_bank_code' => '001',
            'description' => 'Payment method withdrawal'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was created
        $withdrawRequest = WithdrawRequest::where('member_id', $this->member->id)->first();
        $this->assertNotNull($withdrawRequest);
        $this->assertEquals(300, $withdrawRequest->amount);
        $this->assertEquals(0, $withdrawRequest->status); // Pending

        // Verify requirements contain payment method details
        $requirements = $withdrawRequest->requirements;
        $this->assertEquals($this->bankAccount->id, $requirements['payment_method_id']);
        $this->assertEquals('paystack', $requirements['payment_method_type']);
        $this->assertEquals('John Doe', $requirements['recipient_details']['name']);
    }

    /** @test */
    public function test_withdrawal_rejection_workflow()
    {
        $this->actingAs($this->user);

        // Create withdrawal request
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Admin rejection
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        $response = $this->post(route('admin.withdrawal_requests.reject', $withdrawRequest->id), [
            'rejection_reason' => 'Insufficient documentation provided'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify withdrawal request was rejected
        $withdrawRequest->refresh();
        $this->assertEquals(3, $withdrawRequest->status); // Rejected

        // Verify rejection reason was stored
        $requirements = $withdrawRequest->requirements;
        $this->assertEquals('Insufficient documentation provided', $requirements['rejection_reason']);
    }

    /** @test */
    public function test_withdrawal_history_and_requests_pages()
    {
        $this->actingAs($this->user);

        // Create some withdrawal transactions
        Transaction::factory()->count(3)->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'type' => 'Withdraw',
            'status' => 2
        ]);

        // Create some withdrawal requests
        WithdrawRequest::factory()->count(2)->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id
        ]);

        // Test withdrawal history page
        $response = $this->get(route('withdraw.history'));
        $response->assertStatus(200);
        $response->assertViewIs('backend.customer.withdraw.history');

        // Test withdrawal requests page
        $response = $this->get(route('withdraw.requests'));
        $response->assertStatus(200);
        $response->assertViewIs('backend.customer.withdraw.requests');
    }

    /** @test */
    public function test_admin_withdrawal_management_pages()
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

        // Test admin withdrawal requests index
        $response = $this->get(route('admin.withdrawal_requests.index'));
        $response->assertStatus(200);
        $response->assertViewIs('backend.admin.withdrawal_requests.index');

        // Test withdrawal request details
        $withdrawRequest = WithdrawRequest::first();
        $response = $this->get(route('admin.withdrawal_requests.show', $withdrawRequest->id));
        $response->assertStatus(200);
        $response->assertViewIs('backend.admin.withdrawal_requests.show');

        // Test statistics endpoint
        $response = $this->get(route('admin.withdrawal_requests.statistics'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'pending',
            'approved',
            'rejected',
            'total_amount_pending',
            'total_amount_approved'
        ]);
    }

    /** @test */
    public function test_withdrawal_notifications()
    {
        Notification::fake();

        $this->actingAs($this->user);

        // Create withdrawal request
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'method_id' => $this->withdrawMethod->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Create associated transaction
        $transaction = Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'type' => 'Withdraw',
            'status' => 0
        ]);

        $withdrawRequest->transaction_id = $transaction->id;
        $withdrawRequest->save();

        // Admin approval
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Mock payment method service
        $paymentService = $this->mock(PaymentMethodService::class);
        $paymentService->shouldReceive('processWithdrawal')
            ->once()
            ->andReturn([
                'success' => true,
                'data' => ['transaction_id' => 'TXN123'],
                'message' => 'Withdrawal processed successfully'
            ]);

        $this->app->instance(PaymentMethodService::class, $paymentService);

        $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        // Verify notification was sent
        Notification::assertSentTo(
            $this->member,
            \App\Notifications\WithdrawMoney::class
        );
    }

    /** @test */
    public function test_withdrawal_audit_trail()
    {
        $this->actingAs($this->user);

        // Create initial balance
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // Create withdrawal request
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 200,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Audit test withdrawal'
        ]);

        $response->assertRedirect();

        // Verify audit trail
        $withdrawRequest = WithdrawRequest::where('member_id', $this->member->id)->first();
        $this->assertNotNull($withdrawRequest);
        $this->assertEquals($this->member->id, $withdrawRequest->member_id);
        $this->assertEquals($this->savingsAccount->id, $withdrawRequest->debit_account_id);
        $this->assertEquals(200, $withdrawRequest->amount);
        $this->assertEquals('Audit test withdrawal', $withdrawRequest->description);

        // Verify transaction audit trail
        $transaction = Transaction::where('member_id', $this->member->id)
            ->where('type', 'Withdraw')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($this->user->id, $transaction->created_user_id);
        $this->assertEquals($this->member->branch_id, $transaction->branch_id);
    }

    /** @test */
    public function test_withdrawal_database_constraints()
    {
        $this->actingAs($this->user);

        // Test that database constraints prevent invalid data
        $this->expectException(\Exception::class);

        DB::transaction(function () {
            WithdrawRequest::create([
                'member_id' => $this->member->id,
                'method_id' => $this->withdrawMethod->id,
                'debit_account_id' => $this->savingsAccount->id,
                'amount' => -100, // This should fail due to check constraint
                'converted_amount' => -100,
                'status' => 0,
                'tenant_id' => $this->tenant->id
            ]);
        });
    }

    /** @test */
    public function test_withdrawal_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create();
        
        $otherUser = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $otherTenant->id
        ]);

        $otherMember = Member::factory()->create([
            'user_id' => $otherUser->id,
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

        // Try to access other tenant's withdrawal method
        $this->actingAs($this->user);

        $response = $this->post(route('withdraw.manual_withdraw', $otherWithdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Cross-tenant test'
        ]);

        $response->assertStatus(404); // Method not found for this tenant
    }
}
