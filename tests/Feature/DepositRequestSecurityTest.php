<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\DepositRequest;
use App\Models\DepositMethod;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Currency;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DepositRequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $user;
    protected $member;
    protected $depositMethod;
    protected $savingsAccount;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant',
            'status' => 1
        ]);
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'user'
        ]);
        
        // Create test member
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id
        ]);
        
        // Create test currency
        $currency = Currency::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'USD'
        ]);
        
        // Create test savings product
        $savingsProduct = SavingsProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $currency->id
        ]);
        
        // Create test savings account
        $this->savingsAccount = SavingsAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'savings_product_id' => $savingsProduct->id
        ]);
        
        // Create test deposit method
        $this->depositMethod = DepositMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $currency->id
        ]);
        
        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function test_deposit_request_model_has_proper_fillable_attributes()
    {
        $fillable = (new DepositRequest())->getFillable();
        
        $expectedFillable = [
            'member_id',
            'deposit_method_id',
            'savings_account_id',
            'amount',
            'converted_amount',
            'charge',
            'description',
            'requirements',
            'attachment',
            'status',
            'transaction_id',
            'tenant_id'
        ];
        
        $this->assertEquals($expectedFillable, $fillable);
    }

    /** @test */
    public function test_deposit_request_model_relationships()
    {
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id
        ]);
        
        // Test relationships
        $this->assertInstanceOf(Member::class, $depositRequest->member);
        $this->assertInstanceOf(DepositMethod::class, $depositRequest->method);
        $this->assertInstanceOf(SavingsAccount::class, $depositRequest->account);
        
        $this->assertEquals($this->member->id, $depositRequest->member->id);
        $this->assertEquals($this->depositMethod->id, $depositRequest->method->id);
        $this->assertEquals($this->savingsAccount->id, $depositRequest->account->id);
    }

    /** @test */
    public function test_deposit_request_scopes()
    {
        // Create deposit requests with different statuses
        DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 0 // Pending
        ]);
        
        DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 1 // Rejected
        ]);
        
        DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 2 // Approved
        ]);
        
        // Test scopes
        $this->assertEquals(1, DepositRequest::pending()->count());
        $this->assertEquals(1, DepositRequest::rejected()->count());
        $this->assertEquals(1, DepositRequest::approved()->count());
    }

    /** @test */
    public function test_deposit_request_status_text_attribute()
    {
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 0
        ]);
        
        $this->assertEquals('Pending', $depositRequest->status_text);
        
        $depositRequest->status = 1;
        $this->assertEquals('Rejected', $depositRequest->status_text);
        
        $depositRequest->status = 2;
        $this->assertEquals('Approved', $depositRequest->status_text);
    }

    /** @test */
    public function test_deposit_request_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        
        // Create deposit request for other tenant
        DepositRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id
        ]);
        
        // Create deposit request for current tenant
        $currentDepositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id
        ]);
        
        // Test tenant isolation
        $this->assertEquals(1, DepositRequest::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals(1, DepositRequest::where('tenant_id', $otherTenant->id)->count());
    }

    /** @test */
    public function test_deposit_request_controller_tenant_isolation()
    {
        $this->actingAs($this->user);
        
        // Create deposit request for current tenant
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id
        ]);
        
        // Test show method with tenant isolation
        $response = $this->get("/{$this->tenant->slug}/deposit_requests/{$depositRequest->id}");
        $response->assertStatus(200);
        
        // Test that we can't access deposit request from another tenant
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        $otherDepositRequest = DepositRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id
        ]);
        
        $response = $this->get("/{$this->tenant->slug}/deposit_requests/{$otherDepositRequest->id}");
        $response->assertStatus(404); // Should not be found due to tenant isolation
    }

    /** @test */
    public function test_deposit_request_approval_process()
    {
        $this->actingAs($this->user);
        
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id,
            'status' => 0, // Pending
            'amount' => 100.00
        ]);
        
        // Test approval
        $response = $this->get("/{$this->tenant->slug}/deposit_requests/approve/{$depositRequest->id}");
        $response->assertRedirect();
        
        // Verify deposit request was approved
        $depositRequest->refresh();
        $this->assertEquals(2, $depositRequest->status);
        $this->assertNotNull($depositRequest->transaction_id);
        
        // Verify transaction was created
        $transaction = Transaction::find($depositRequest->transaction_id);
        $this->assertNotNull($transaction);
        $this->assertEquals(100.00, $transaction->amount);
        $this->assertEquals('cr', $transaction->dr_cr);
        $this->assertEquals('Deposit', $transaction->type);
    }

    /** @test */
    public function test_deposit_request_rejection_process()
    {
        $this->actingAs($this->user);
        
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id,
            'status' => 0 // Pending
        ]);
        
        // Test rejection
        $response = $this->get("/{$this->tenant->slug}/deposit_requests/reject/{$depositRequest->id}");
        $response->assertRedirect();
        
        // Verify deposit request was rejected
        $depositRequest->refresh();
        $this->assertEquals(1, $depositRequest->status);
        $this->assertNull($depositRequest->transaction_id);
    }

    /** @test */
    public function test_deposit_request_cannot_approve_already_processed()
    {
        $this->actingAs($this->user);
        
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id,
            'status' => 2 // Already approved
        ]);
        
        // Test approval of already approved request
        $response = $this->get("/{$this->tenant->slug}/deposit_requests/approve/{$depositRequest->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function test_deposit_request_mass_assignment_protection()
    {
        $data = [
            'member_id' => $this->member->id,
            'deposit_method_id' => $this->depositMethod->id,
            'savings_account_id' => $this->savingsAccount->id,
            'amount' => 100.00,
            'status' => 2, // Trying to set approved status directly
            'tenant_id' => $this->tenant->id
        ];
        
        $depositRequest = DepositRequest::create($data);
        
        // Status should be set to default (0) not the provided value
        $this->assertEquals(0, $depositRequest->status);
        $this->assertEquals(100.00, $depositRequest->amount);
    }

    /** @test */
    public function test_deposit_request_data_types_casting()
    {
        $depositRequest = DepositRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => '100.50',
            'converted_amount' => '105.75',
            'charge' => '5.25',
            'requirements' => ['key' => 'value'],
            'status' => '1'
        ]);
        
        // Test casting
        $this->assertIsFloat($depositRequest->amount);
        $this->assertIsFloat($depositRequest->converted_amount);
        $this->assertIsFloat($depositRequest->charge);
        $this->assertIsArray($depositRequest->requirements);
        $this->assertIsInt($depositRequest->status);
        
        $this->assertEquals(100.50, $depositRequest->amount);
        $this->assertEquals(105.75, $depositRequest->converted_amount);
        $this->assertEquals(5.25, $depositRequest->charge);
        $this->assertEquals(['key' => 'value'], $depositRequest->requirements);
        $this->assertEquals(1, $depositRequest->status);
    }
}
