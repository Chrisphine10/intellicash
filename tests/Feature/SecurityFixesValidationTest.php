<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Loan;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\LoanRepayment;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Currency;
use App\Models\Branch;
use App\Models\LoanProduct;
use App\Models\SavingsAccount;
use App\Models\SavingsType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SecurityFixesValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant1;
    protected $tenant2;
    protected $user1;
    protected $user2;
    protected $member1;
    protected $member2;
    protected $loan1;
    protected $loan2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenants
        $this->tenant1 = Tenant::create([
            'name' => 'Test Tenant 1',
            'slug' => 'test-tenant-1',
            'status' => 1,
        ]);

        $this->tenant2 = Tenant::create([
            'name' => 'Test Tenant 2',
            'slug' => 'test-tenant-2',
            'status' => 1,
        ]);

        // Create test users
        $this->user1 = User::create([
            'name' => 'Test User 1',
            'email' => 'user1@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'tenant_id' => $this->tenant1->id,
            'status' => 1,
        ]);

        $this->user2 = User::create([
            'name' => 'Test User 2',
            'email' => 'user2@test.com',
            'password' => bcrypt('password'),
            'user_type' => 'admin',
            'tenant_id' => $this->tenant2->id,
            'status' => 1,
        ]);

        // Create test branches
        $branch1 = Branch::create([
            'name' => 'Branch 1',
            'tenant_id' => $this->tenant1->id,
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch 2',
            'tenant_id' => $this->tenant2->id,
        ]);

        // Create test currencies
        $currency1 = Currency::create([
            'full_name' => 'Test Currency 1',
            'name' => 'TC1',
            'exchange_rate' => 1.0,
            'base_currency' => 1,
            'status' => 1,
            'tenant_id' => $this->tenant1->id,
        ]);

        $currency2 = Currency::create([
            'full_name' => 'Test Currency 2',
            'name' => 'TC2',
            'exchange_rate' => 1.0,
            'base_currency' => 1,
            'status' => 1,
            'tenant_id' => $this->tenant2->id,
        ]);

        // Create test members
        $this->member1 = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Member 1',
            'member_no' => 'TM001',
            'email' => 'member1@test.com',
            'mobile' => '1234567890',
            'status' => 1,
            'tenant_id' => $this->tenant1->id,
            'branch_id' => $branch1->id,
        ]);

        $this->member2 = Member::create([
            'first_name' => 'Test',
            'last_name' => 'Member 2',
            'member_no' => 'TM002',
            'email' => 'member2@test.com',
            'mobile' => '0987654321',
            'status' => 1,
            'tenant_id' => $this->tenant2->id,
            'branch_id' => $branch2->id,
        ]);

        // Create test loan products
        $loanProduct1 = LoanProduct::create([
            'name' => 'Test Loan Product 1',
            'interest_rate' => 12.0,
            'term' => 12,
            'term_period' => '+1 month',
            'status' => 1,
            'tenant_id' => $this->tenant1->id,
            'currency_id' => $currency1->id,
        ]);

        $loanProduct2 = LoanProduct::create([
            'name' => 'Test Loan Product 2',
            'interest_rate' => 15.0,
            'term' => 6,
            'term_period' => '+1 month',
            'status' => 1,
            'tenant_id' => $this->tenant2->id,
            'currency_id' => $currency2->id,
        ]);

        // Create test loans
        $this->loan1 = Loan::create([
            'loan_product_id' => $loanProduct1->id,
            'borrower_id' => $this->member1->id,
            'currency_id' => $currency1->id,
            'applied_amount' => 10000,
            'total_payable' => 11200,
            'total_paid' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant1->id,
            'branch_id' => $branch1->id,
        ]);

        $this->loan2 = Loan::create([
            'loan_product_id' => $loanProduct2->id,
            'borrower_id' => $this->member2->id,
            'currency_id' => $currency2->id,
            'applied_amount' => 15000,
            'total_payable' => 16500,
            'total_paid' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant2->id,
            'branch_id' => $branch2->id,
        ]);
    }

    /** @test */
    public function test_tenant_isolation_in_loan_model()
    {
        // Login as user from tenant 1
        Auth::login($this->user1);

        // Query loans - should only return loans from tenant 1
        $loans = Loan::all();
        
        $this->assertCount(1, $loans);
        $this->assertEquals($this->loan1->id, $loans->first()->id);
        $this->assertEquals($this->tenant1->id, $loans->first()->tenant_id);

        // Try to access loan from tenant 2 directly
        $loan2Access = Loan::find($this->loan2->id);
        $this->assertNull($loan2Access);
    }

    /** @test */
    public function test_tenant_isolation_in_member_model()
    {
        // Login as user from tenant 1
        Auth::login($this->user1);

        // Query members - should only return members from tenant 1
        $members = Member::all();
        
        $this->assertCount(1, $members);
        $this->assertEquals($this->member1->id, $members->first()->id);
        $this->assertEquals($this->tenant1->id, $members->first()->tenant_id);

        // Try to access member from tenant 2 directly
        $member2Access = Member::find($this->member2->id);
        $this->assertNull($member2Access);
    }

    /** @test */
    public function test_dashboard_controller_tenant_isolation()
    {
        // Login as user from tenant 1
        Auth::login($this->user1);

        // Access dashboard
        $response = $this->get(route('dashboard.index', ['tenant' => $this->tenant1->slug]));
        
        $this->assertEquals(200, $response->status());
        
        // Verify dashboard data is tenant-specific
        $viewData = $response->viewData();
        
        // Check that recent transactions are tenant-specific
        if (isset($viewData['recent_transactions'])) {
            foreach ($viewData['recent_transactions'] as $transaction) {
                $this->assertEquals($this->tenant1->id, $transaction->tenant_id);
            }
        }

        // Check that loan balances are tenant-specific
        if (isset($viewData['loan_balances'])) {
            foreach ($viewData['loan_balances'] as $balance) {
                $this->assertEquals($this->tenant1->id, $balance->tenant_id);
            }
        }
    }

    /** @test */
    public function test_cross_tenant_access_prevention()
    {
        // Login as user from tenant 1
        Auth::login($this->user1);

        // Try to access tenant 2's dashboard
        $response = $this->get(route('dashboard.index', ['tenant' => $this->tenant2->slug]));
        
        // Should be denied access or redirected
        $this->assertTrue(
            $response->status() === 403 || 
            $response->status() === 404 || 
            $response->isRedirect()
        );
    }

    /** @test */
    public function test_foreign_key_constraints_with_tenant_validation()
    {
        // Test that foreign key constraints work properly
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        // Try to create a loan with a member from different tenant
        Loan::create([
            'loan_product_id' => $this->loan1->loan_product_id,
            'borrower_id' => $this->member2->id, // Member from tenant 2
            'currency_id' => $this->loan1->currency_id,
            'applied_amount' => 10000,
            'total_payable' => 11200,
            'total_paid' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant1->id, // But loan for tenant 1
            'branch_id' => $this->loan1->branch_id,
        ]);
    }

    /** @test */
    public function test_security_event_monitoring()
    {
        // Mock the ThreatMonitoringService
        $mockService = $this->mock('App\Services\ThreatMonitoringService');
        $mockService->shouldReceive('monitorEvent')
            ->with('loan_created', \Mockery::type('array'))
            ->once();

        // Login as user from tenant 1
        Auth::login($this->user1);

        // Create a new loan - should trigger security monitoring
        $newLoan = Loan::create([
            'loan_product_id' => $this->loan1->loan_product_id,
            'borrower_id' => $this->member1->id,
            'currency_id' => $this->loan1->currency_id,
            'applied_amount' => 5000,
            'total_payable' => 5600,
            'total_paid' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant1->id,
            'branch_id' => $this->loan1->branch_id,
        ]);

        $this->assertNotNull($newLoan);
    }

    /** @test */
    public function test_database_indexes_performance()
    {
        // Test that tenant-aware indexes exist
        $indexes = DB::select("SHOW INDEX FROM loans WHERE Key_name LIKE '%tenant%'");
        
        $this->assertGreaterThan(0, count($indexes));
        
        // Verify specific indexes exist
        $indexNames = collect($indexes)->pluck('Key_name')->toArray();
        
        $this->assertContains('idx_loans_tenant_status_created', $indexNames);
        $this->assertContains('idx_loans_tenant_borrower_status', $indexNames);
        $this->assertContains('idx_loans_tenant_product_status', $indexNames);
    }

    /** @test */
    public function test_composite_unique_constraint()
    {
        // Test that composite unique constraint prevents duplicate borrower-tenant combinations
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        // Try to create another loan for the same borrower in the same tenant
        Loan::create([
            'loan_product_id' => $this->loan1->loan_product_id,
            'borrower_id' => $this->member1->id, // Same borrower
            'currency_id' => $this->loan1->currency_id,
            'applied_amount' => 20000,
            'total_payable' => 22400,
            'total_paid' => 0,
            'status' => 1,
            'tenant_id' => $this->tenant1->id, // Same tenant
            'branch_id' => $this->loan1->branch_id,
        ]);
    }

    /** @test */
    public function test_unauthorized_access_attempts()
    {
        // Test unauthorized access attempts are blocked
        Auth::login($this->user1);

        // Try to access member from different tenant
        $response = $this->get(route('members.show', [
            'tenant' => $this->tenant1->slug,
            'id' => $this->member2->id
        ]));

        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 403 ||
            $response->isRedirect()
        );
    }

    /** @test */
    public function test_sql_injection_protection()
    {
        // Test that SQL injection attempts are blocked
        $maliciousInputs = [
            "'; DROP TABLE users; --",
            "' OR '1'='1",
            "UNION SELECT * FROM users",
            "'; INSERT INTO users VALUES ('hacker', 'password'); --"
        ];

        foreach ($maliciousInputs as $input) {
            $response = $this->post(route('savings_accounts.store'), [
                'account_number' => $input,
                'member_id' => $this->member1->id,
                'savings_product_id' => 1,
                'status' => 1,
                'opening_balance' => 1000,
            ]);

            // Should not succeed with malicious input
            $this->assertNotEquals(200, $response->status());
        }
    }

    /** @test */
    public function test_global_scope_bypass_protection()
    {
        // Test that global scope bypasses are logged and blocked
        Auth::login($this->user1);

        // Attempt to bypass global scopes (this should be logged)
        try {
            $allLoans = Loan::withoutGlobalScopes()->get();
            // If this succeeds, verify tenant isolation is still maintained
            $crossTenantLoans = $allLoans->where('tenant_id', $this->tenant2->id);
            $this->assertCount(0, $crossTenantLoans);
        } catch (\Exception $e) {
            // Expected behavior - global scope bypass should be blocked
            $this->assertStringContains('Unauthorized', $e->getMessage());
        }
    }

    /** @test */
    public function test_input_validation_security()
    {
        // Test XSS protection
        $xssPayload = '<script>alert("xss")</script>';
        
        $response = $this->post(route('savings_accounts.store'), [
            'account_number' => 'TEST001',
            'member_id' => $this->member1->id,
            'savings_product_id' => 1,
            'status' => 1,
            'opening_balance' => 1000,
            'description' => $xssPayload,
        ]);

        // Should sanitize or reject XSS payload
        $this->assertNotEquals(200, $response->status());
    }

    /** @test */
    public function test_authorization_middleware()
    {
        // Test that unauthorized users cannot access protected resources
        $response = $this->get(route('savings_accounts.index', ['tenant' => $this->tenant1->slug]));
        
        // Should redirect to login or return 403
        $this->assertTrue(
            $response->status() === 302 || 
            $response->status() === 403
        );
    }

    /** @test */
    public function test_secure_response_formatting()
    {
        // Test that responses use proper JSON formatting instead of echo
        Auth::login($this->user1);
        
        $response = $this->get(route('dashboard.json_expense_analytics', ['currency_id' => 1]));
        
        // Should return proper JSON response
        $this->assertEquals(200, $response->status());
        $this->assertJson($response->content());
        
        $data = json_decode($response->content(), true);
        $this->assertArrayHasKey('amounts', $data);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('colors', $data);
    }
}
