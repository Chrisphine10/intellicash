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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class WithdrawalFlowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $adminUser;
    protected $customerUser;
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

        // Set up tenant context
        $this->app->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_complete_withdrawal_flow()
    {
        // 1. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 2. Customer creates withdrawal request
        $customerResponse = $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 500,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);

        $customerResponse->assertRedirect();
        $customerResponse->assertSessionHas('success');

        // 3. Verify withdrawal request was created
        $withdrawRequest = WithdrawRequest::latest()->first();
        $this->assertNotNull($withdrawRequest);
        $this->assertEquals(0, $withdrawRequest->status); // Pending
        $this->assertEquals(500, $withdrawRequest->amount);

        // 4. Admin approves withdrawal
        $adminResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        $adminResponse->assertRedirect();
        $adminResponse->assertSessionHas('success');

        // 5. Verify withdrawal request was approved
        $withdrawRequest->refresh();
        $this->assertEquals(2, $withdrawRequest->status); // Approved

        // 6. Verify transaction was created and completed
        $transaction = $withdrawRequest->transaction;
        $this->assertNotNull($transaction);
        $this->assertEquals(2, $transaction->status); // Completed
        $this->assertEquals('Withdraw', $transaction->type);
        $this->assertEquals(500, $transaction->amount);

        // 7. Verify customer balance was updated
        $balance = get_account_balance($this->savingsAccount->id, $this->member->id);
        $this->assertEquals(500, $balance); // 1000 - 500 = 500
    }

    /** @test */
    public function test_withdrawal_rejection_flow()
    {
        // 1. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 2. Customer creates withdrawal request
        $customerResponse = $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 500,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);

        $customerResponse->assertRedirect();

        // 3. Admin rejects withdrawal
        $withdrawRequest = WithdrawRequest::latest()->first();
        $adminResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.reject', $withdrawRequest->id), [
                'rejection_reason' => 'Insufficient documentation',
                'approval_level' => 'manager',
                'risk_assessment' => 'medium'
            ]);

        $adminResponse->assertRedirect();
        $adminResponse->assertSessionHas('success');

        // 4. Verify withdrawal request was rejected
        $withdrawRequest->refresh();
        $this->assertEquals(3, $withdrawRequest->status); // Rejected

        // 5. Verify transaction was rejected
        $transaction = $withdrawRequest->transaction;
        $this->assertNotNull($transaction);
        $this->assertEquals(3, $transaction->status); // Rejected

        // 6. Verify customer balance was not affected
        $balance = get_account_balance($this->savingsAccount->id, $this->member->id);
        $this->assertEquals(1000, $balance); // Balance unchanged
    }

    /** @test */
    public function test_payment_method_withdrawal_flow()
    {
        // 1. Create bank account for payment method
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method_type' => 'paystack'
        ]);

        // 2. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 3. Customer creates payment method withdrawal request
        $customerResponse = $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', 'payment_' . $bankAccount->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 300,
                'recipient_name' => 'John Doe',
                'recipient_mobile' => '0712345678',
                'recipient_account' => '1234567890',
                'description' => 'Payment method withdrawal'
            ]);

        $customerResponse->assertRedirect();
        $customerResponse->assertSessionHas('success');

        // 4. Verify withdrawal request was created
        $withdrawRequest = WithdrawRequest::latest()->first();
        $this->assertNotNull($withdrawRequest);
        $this->assertEquals(0, $withdrawRequest->status); // Pending

        // 5. Verify requirements contain payment method details
        $requirements = $withdrawRequest->requirements;
        $this->assertArrayHasKey('payment_method_id', $requirements);
        $this->assertArrayHasKey('recipient_details', $requirements);
        $this->assertEquals($bankAccount->id, $requirements['payment_method_id']);

        // 6. Admin approves withdrawal
        $adminResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        $adminResponse->assertRedirect();
        $adminResponse->assertSessionHas('success');

        // 7. Verify withdrawal was processed
        $withdrawRequest->refresh();
        $this->assertEquals(2, $withdrawRequest->status); // Approved
    }

    /** @test */
    public function test_withdrawal_notification_flow()
    {
        Notification::fake();

        // 1. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 2. Customer creates withdrawal request
        $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 500,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);

        // 3. Admin approves withdrawal
        $withdrawRequest = WithdrawRequest::latest()->first();
        $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        // 4. Verify notification was sent
        Notification::assertSentTo(
            $this->member,
            \App\Notifications\WithdrawMoney::class
        );
    }

    /** @test */
    public function test_withdrawal_audit_trail()
    {
        // 1. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 2. Customer creates withdrawal request
        $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 500,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);

        // 3. Verify withdrawal request audit trail
        $withdrawRequest = WithdrawRequest::latest()->first();
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawRequest->id,
            'member_id' => $this->member->id,
            'amount' => 500,
            'status' => 0
        ]);

        // 4. Admin approves withdrawal
        $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        // 5. Verify approval audit trail
        $withdrawRequest->refresh();
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawRequest->id,
            'status' => 2
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $withdrawRequest->transaction_id,
            'status' => 2,
            'type' => 'Withdraw'
        ]);
    }

    /** @test */
    public function test_withdrawal_tenant_isolation()
    {
        // 1. Create another tenant
        $otherTenant = Tenant::factory()->create();
        $otherCurrency = Currency::factory()->create(['name' => 'USD']);

        $otherAdminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $otherTenant->id
        ]);

        $otherCustomerUser = User::factory()->create([
            'user_type' => 'customer',
            'tenant_id' => $otherTenant->id
        ]);

        $otherMember = Member::factory()->create([
            'user_id' => $otherCustomerUser->id,
            'tenant_id' => $otherTenant->id
        ]);

        $otherSavingsProduct = SavingsProduct::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency_id' => $otherCurrency->id,
            'allow_withdraw' => 1
        ]);

        $otherSavingsAccount = SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'savings_product_id' => $otherSavingsProduct->id,
            'tenant_id' => $otherTenant->id
        ]);

        $otherWithdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency_id' => $otherCurrency->id,
            'status' => 1
        ]);

        // 2. Create withdrawal request for other tenant
        $this->app->instance('tenant', $otherTenant);
        $this->actingAs($otherCustomerUser)
            ->post(route('withdraw.manual_withdraw', $otherWithdrawMethod->id), [
                'debit_account' => $otherSavingsAccount->id,
                'amount' => 200,
                'requirements' => ['account_number' => '9876543210'],
                'description' => 'Other tenant withdrawal'
            ]);

        // 3. Switch back to original tenant
        $this->app->instance('tenant', $this->tenant);

        // 4. Verify original tenant admin cannot see other tenant's requests
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.withdrawal_requests.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Other tenant withdrawal');

        // 5. Verify original tenant admin cannot approve other tenant's requests
        $otherWithdrawRequest = WithdrawRequest::where('tenant_id', $otherTenant->id)->first();
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.withdrawal_requests.approve', $otherWithdrawRequest->id));

        $response->assertStatus(404);
    }

    /** @test */
    public function test_withdrawal_concurrent_processing()
    {
        // 1. Create initial balance for customer
        Transaction::factory()->create([
            'member_id' => $this->member->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 1000,
            'dr_cr' => 'cr',
            'status' => 2
        ]);

        // 2. Customer creates withdrawal request
        $this->actingAs($this->customerUser)
            ->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 500,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);

        $withdrawRequest = WithdrawRequest::latest()->first();

        // 3. Simulate concurrent admin approval attempts
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->actingAs($this->adminUser)
                ->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));
        }

        // 4. Only one approval should succeed
        $successfulApprovals = collect($responses)->filter(function ($response) {
            return $response->status() === 302 && $response->getSession()->has('success');
        });

        $this->assertEquals(1, $successfulApprovals->count());

        // 5. Verify withdrawal request was processed only once
        $withdrawRequest->refresh();
        $this->assertEquals(2, $withdrawRequest->status);
    }
}
