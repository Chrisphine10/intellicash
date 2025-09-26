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
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class WithdrawModuleSecurityTest extends TestCase
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
    public function test_withdrawal_prevents_race_conditions()
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

        // Simulate concurrent withdrawal requests
        $responses = [];
        
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 200,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Test withdrawal'
            ]);
            
            $responses[] = $response;
        }

        // Only one withdrawal should succeed
        $successfulWithdrawals = collect($responses)->filter(function ($response) {
            return $response->status() === 302 && $response->getSession()->has('success');
        });

        $this->assertEquals(1, $successfulWithdrawals->count());
    }

    /** @test */
    public function test_withdrawal_validates_account_ownership()
    {
        $this->actingAs($this->user);

        // Create another member's account
        $otherMember = Member::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherAccount = SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $otherAccount->id,
            'amount' => 100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Unauthorized withdrawal attempt'
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Account not found or unauthorized access', session('error'));
    }

    /** @test */
    public function test_withdrawal_validates_amount_constraints()
    {
        $this->actingAs($this->user);

        // Test negative amount
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => -100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Negative amount test'
        ]);

        $response->assertSessionHasErrors(['amount']);

        // Test zero amount
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 0,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Zero amount test'
        ]);

        $response->assertSessionHasErrors(['amount']);

        // Test excessive amount
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 1000000,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Excessive amount test'
        ]);

        $response->assertSessionHasErrors(['amount']);
    }

    /** @test */
    public function test_withdrawal_validates_recipient_data()
    {
        $this->actingAs($this->user);

        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method_type' => 'paystack'
        ]);

        // Test invalid recipient name
        $response = $this->post(route('withdraw.manual_withdraw', 'payment_' . $bankAccount->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'recipient_name' => 'John123@#$',
            'recipient_mobile' => '0712345678',
            'recipient_account' => '1234567890',
            'description' => 'Invalid name test'
        ]);

        $response->assertSessionHasErrors(['recipient_name']);

        // Test invalid mobile number
        $response = $this->post(route('withdraw.manual_withdraw', 'payment_' . $bankAccount->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'recipient_name' => 'John Doe',
            'recipient_mobile' => 'invalid-mobile',
            'recipient_account' => '1234567890',
            'description' => 'Invalid mobile test'
        ]);

        $response->assertSessionHasErrors(['recipient_mobile']);

        // Test invalid account number
        $response = $this->post(route('withdraw.manual_withdraw', 'payment_' . $bankAccount->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'recipient_name' => 'John Doe',
            'recipient_mobile' => '0712345678',
            'recipient_account' => 'invalid-account',
            'description' => 'Invalid account test'
        ]);

        $response->assertSessionHasErrors(['recipient_account']);
    }

    /** @test */
    public function test_file_upload_security()
    {
        $this->actingAs($this->user);

        Storage::fake('private');

        // Test malicious file upload
        $maliciousFile = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');
        
        $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'File upload test',
            'attachment' => $maliciousFile
        ]);

        // Should reject PHP files
        $response->assertSessionHasErrors(['attachment']);
    }

    /** @test */
    public function test_rate_limiting_prevents_abuse()
    {
        $this->actingAs($this->user);

        // Make multiple rapid requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
                'debit_account' => $this->savingsAccount->id,
                'amount' => 100,
                'requirements' => ['account_number' => '1234567890'],
                'description' => 'Rate limit test'
            ]);
        }

        // Should be rate limited
        $this->assertEquals(429, $response->status());
    }

    /** @test */
    public function test_tenant_isolation()
    {
        $this->actingAs($this->user);

        // Create another tenant's withdraw method
        $otherTenant = Tenant::factory()->create();
        $otherWithdrawMethod = WithdrawMethod::factory()->create([
            'tenant_id' => $otherTenant->id,
            'currency_id' => $this->currency->id
        ]);

        $response = $this->post(route('withdraw.manual_withdraw', $otherWithdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Cross-tenant test'
        ]);

        $response->assertStatus(404); // Method not found for this tenant
    }

    /** @test */
    public function test_audit_logging()
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

        $this->post(route('withdraw.manual_withdraw', $this->withdrawMethod->id), [
            'debit_account' => $this->savingsAccount->id,
            'amount' => 100,
            'requirements' => ['account_number' => '1234567890'],
            'description' => 'Audit test'
        ]);

        // Check if audit log was created
        $this->assertDatabaseHas('withdraw_requests', [
            'member_id' => $this->member->id,
            'amount' => 100,
            'description' => 'Audit test'
        ]);
    }

    /** @test */
    public function test_admin_approval_security()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Create withdrawal request
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Test approval with pessimistic locking
        $response = $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verify status was updated
        $this->assertDatabaseHas('withdraw_requests', [
            'id' => $withdrawRequest->id,
            'status' => 2
        ]);
    }

    /** @test */
    public function test_concurrent_admin_approval_prevention()
    {
        // Create admin user
        $adminUser = User::factory()->create([
            'user_type' => 'admin',
            'tenant_id' => $this->tenant->id
        ]);

        $this->actingAs($adminUser);

        // Create withdrawal request
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);

        // Simulate concurrent approval attempts
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = $this->post(route('admin.withdrawal_requests.approve', $withdrawRequest->id));
        }

        // Only first approval should succeed
        $successfulApprovals = collect($responses)->filter(function ($response) {
            return $response->status() === 302 && $response->getSession()->has('success');
        });

        $this->assertEquals(1, $successfulApprovals->count());
    }

    /** @test */
    public function test_database_constraints_enforcement()
    {
        $this->actingAs($this->user);

        // Test that negative amounts are rejected at database level
        $this->expectException(\Exception::class);

        DB::transaction(function () {
            WithdrawRequest::create([
                'member_id' => $this->member->id,
                'method_id' => $this->withdrawMethod->id,
                'debit_account_id' => $this->savingsAccount->id,
                'amount' => -100, // This should fail
                'converted_amount' => -100,
                'status' => 0,
                'tenant_id' => $this->tenant->id
            ]);
        });
    }

    /** @test */
    public function test_xss_prevention_in_views()
    {
        $this->actingAs($this->user);

        // Create withdrawal request with XSS payload
        $withdrawRequest = WithdrawRequest::factory()->create([
            'member_id' => $this->member->id,
            'debit_account_id' => $this->savingsAccount->id,
            'tenant_id' => $this->tenant->id,
            'requirements' => json_encode([
                'recipient_details' => [
                    'name' => '<script>alert("xss")</script>',
                    'mobile' => '0712345678'
                ]
            ])
        ]);

        $response = $this->get(route('admin.withdrawal_requests.show', $withdrawRequest->id));

        // Should escape the XSS payload
        $response->assertDontSee('<script>alert("xss")</script>', false);
        $response->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }
}
