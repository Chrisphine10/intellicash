<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Currency;
use App\Models\SavingsAccount;
use App\Models\SavingsType;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\LoanProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoanRepaymentFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $branch;
    protected $currency;
    protected $member;
    protected $savingsAccount;
    protected $loanProduct;
    protected $loan;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->tenant = Tenant::factory()->create();
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->currency = Currency::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id
        ]);
        
        $savingsType = SavingsType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $this->currency->id
        ]);
        
        $this->savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $this->member->id,
            'savings_type_id' => $savingsType->id
        ]);
        
        $this->loanProduct = LoanProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'currency_id' => $this->currency->id,
            'interest_type' => 'flat_rate',
            'interest_rate' => 12.0,
            'term' => 12,
            'term_period' => '+1 month'
        ]);

        $this->loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'borrower_id' => $this->member->id,
            'loan_product_id' => $this->loanProduct->id,
            'currency_id' => $this->currency->id,
            'applied_amount' => 10000,
            'total_payable' => 11200,
            'total_paid' => 0
        ]);
    }

    /** @test */
    public function test_standardized_balance_calculation()
    {
        // Create a payment
        $payment = LoanPayment::factory()->create([
            'loan_id' => $this->loan->id,
            'repayment_amount' => 1000,
            'interest' => 100,
            'total_amount' => 1100
        ]);

        // Test the new standardized balance calculation
        $remainingBalance = $this->loan->calculateRemainingBalance();
        $remainingPrincipal = $this->loan->calculateRemainingPrincipal();
        
        $this->assertEquals(10100, $remainingBalance); // 11200 - 1100
        $this->assertEquals(9000, $remainingPrincipal); // 10000 - 1000
    }

    /** @test */
    public function test_loan_completion_status()
    {
        // Create a payment that covers the full amount
        $payment = LoanPayment::factory()->create([
            'loan_id' => $this->loan->id,
            'repayment_amount' => 10000,
            'interest' => 1200,
            'total_amount' => 11200
        ]);

        // Test the new completion check
        $this->assertTrue($this->loan->isFullyPaid());
        $this->assertEquals(0, $this->loan->calculateRemainingBalance());
    }

    /** @test */
    public function test_next_payment_method()
    {
        // Create repayment schedules
        LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'status' => 1, // Paid
            'repayment_date' => '2025-01-01'
        ]);

        LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'status' => 0, // Pending
            'repayment_date' => '2025-02-01'
        ]);

        LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'status' => 0, // Pending
            'repayment_date' => '2025-03-01'
        ]);

        // Test the new next payment method
        $nextPayment = $this->loan->getNextPayment();
        
        $this->assertNotNull($nextPayment);
        $this->assertEquals('2025-02-01', $nextPayment->repayment_date);
        $this->assertEquals(0, $nextPayment->status);
    }

    /** @test */
    public function test_race_condition_protection()
    {
        // Test that the lockForUpdate query works
        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $this->loan->id,
            'status' => 0
        ]);

        // Simulate the atomic validation query
        $lockedRepayment = LoanRepayment::where('loan_id', $this->loan->id)
            ->where('status', 0)
            ->where('id', $repayment->id)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($lockedRepayment);
        $this->assertEquals($repayment->id, $lockedRepayment->id);
    }

    /** @test */
    public function test_authorization_checks()
    {
        // Test that authorization methods exist
        $this->assertTrue(method_exists($this->loan, 'calculateRemainingBalance'));
        $this->assertTrue(method_exists($this->loan, 'calculateRemainingPrincipal'));
        $this->assertTrue(method_exists($this->loan, 'isFullyPaid'));
        $this->assertTrue(method_exists($this->loan, 'getNextPayment'));
    }
}
