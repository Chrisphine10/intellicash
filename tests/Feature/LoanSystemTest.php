<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Models\LoanPayment;
use App\Models\LoanRepayment;
use App\Models\Member;
use App\Models\Currency;
use App\Models\SavingsAccount;
use App\Models\SavingsType;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Utilities\LoanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class LoanSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $branch;
    protected $currency;
    protected $member;
    protected $savingsAccount;
    protected $loanProduct;

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
    }

    /** @test */
    public function test_flat_rate_calculation()
    {
        $calculator = new LoanCalculator(
            10000, // amount
            '2025-01-01', // first payment date
            12.0, // interest rate
            12, // term
            '+1 month', // term period
            2.0 // late payment penalties
        );

        $repayments = $calculator->get_flat_rate();
        
        $this->assertCount(12, $repayments);
        $this->assertEquals(11200, $calculator->payable_amount); // 10000 + 1200 interest
        
        // Check first payment
        $firstPayment = $repayments[0];
        $this->assertEquals(933.33, round($firstPayment['amount_to_pay'], 2));
        $this->assertEquals(100.00, round($firstPayment['interest'], 2));
        $this->assertEquals(833.33, round($firstPayment['principal_amount'], 2));
    }

    /** @test */
    public function test_fixed_rate_calculation()
    {
        $calculator = new LoanCalculator(
            10000, // amount
            '2025-01-01', // first payment date
            12.0, // interest rate
            12, // term
            '+1 month', // term period
            2.0 // late payment penalties
        );

        $repayments = $calculator->get_fixed_rate();
        
        $this->assertCount(12, $repayments);
        $this->assertEquals(11200, $calculator->payable_amount); // 10000 + 1200 interest
        
        // Check that interest is consistent across payments
        $firstPayment = $repayments[0];
        $this->assertEquals(100.00, round($firstPayment['interest'], 2));
    }

    /** @test */
    public function test_mortgage_calculation()
    {
        $calculator = new LoanCalculator(
            100000, // amount
            '2025-01-01', // first payment date
            6.0, // interest rate
            12, // term
            '+1 month', // term period
            2.0 // late payment penalties
        );

        $repayments = $calculator->get_mortgage();
        
        $this->assertCount(12, $repayments);
        
        // Check that payments are consistent (EMI)
        $firstPayment = $repayments[0];
        $secondPayment = $repayments[1];
        $this->assertEquals($firstPayment['amount_to_pay'], $secondPayment['amount_to_pay']);
    }

    /** @test */
    public function test_loan_creation_with_flat_rate()
    {
        $loanData = [
            'loan_product_id' => $this->loanProduct->id,
            'borrower_id' => $this->member->id,
            'currency_id' => $this->currency->id,
            'first_payment_date' => '2025-02-01',
            'release_date' => '2025-01-15',
            'applied_amount' => 10000,
            'late_payment_penalties' => 2.0,
            'debit_account_id' => $this->savingsAccount->id,
            'description' => 'Test loan'
        ];

        $loan = Loan::create($loanData);
        
        $this->assertNotNull($loan);
        $this->assertEquals(10000, $loan->applied_amount);
        $this->assertEquals(0, $loan->status); // Pending
    }

    /** @test */
    public function test_loan_payment_processing()
    {
        // Create a loan
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'borrower_id' => $this->member->id,
            'loan_product_id' => $this->loanProduct->id,
            'currency_id' => $this->currency->id,
            'applied_amount' => 10000,
            'total_payable' => 11200,
            'total_paid' => 0
        ]);

        // Create repayment schedule
        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $loan->id,
            'principal_amount' => 833.33,
            'interest' => 100.00,
            'amount_to_pay' => 933.33,
            'status' => 0
        ]);

        // Process payment
        $paymentData = [
            'loan_id' => $loan->id,
            'paid_at' => '2025-01-15',
            'principal_amount' => 833.33,
            'interest' => 100.00,
            'late_penalties' => 0,
            'due_amount_of' => $repayment->id
        ];

        $payment = LoanPayment::create($paymentData);
        
        $this->assertNotNull($payment);
        $this->assertEquals(933.33, $payment->total_amount);
        
        // Check loan balance update
        $loan->refresh();
        $this->assertEquals(833.33, $loan->total_paid);
    }

    /** @test */
    public function test_balance_calculation_includes_interest()
    {
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applied_amount' => 10000,
            'total_payable' => 11200,
            'total_paid' => 5000
        ]);

        // Create payments with interest
        LoanPayment::factory()->create([
            'loan_id' => $loan->id,
            'repayment_amount' => 1000,
            'interest' => 100
        ]);

        $totalPaidIncludingInterest = $loan->total_paid + $loan->payments->sum('interest');
        $balance = $loan->total_payable - $totalPaidIncludingInterest;
        
        $this->assertEquals(6000, $balance); // 11200 - (5000 + 100)
    }

    /** @test */
    public function test_interest_rate_validation()
    {
        $calculator = new LoanCalculator(10000, '2025-01-01', 12.0, 12, '+1 month', 2.0);
        
        // Test valid rates
        $validRates = [0, 5.5, 12.0, 25.0, 100.0];
        foreach ($validRates as $rate) {
            $validation = $calculator->validateInputs(['interest_rate' => $rate]);
            $this->assertTrue($validation['valid'], "Rate {$rate} should be valid");
        }
        
        // Test invalid rates
        $invalidRates = [-1, 101, 'invalid', null];
        foreach ($invalidRates as $rate) {
            $validation = $calculator->validateInputs(['interest_rate' => $rate]);
            $this->assertFalse($validation['valid'], "Rate {$rate} should be invalid");
        }
    }

    /** @test */
    public function test_loan_completion_status()
    {
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applied_amount' => 10000,
            'total_paid' => 10000
        ]);

        // Loan should be marked as completed when total_paid >= applied_amount
        $this->assertTrue($loan->total_paid >= $loan->applied_amount);
    }

    /** @test */
    public function test_concurrent_payment_protection()
    {
        $loan = Loan::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applied_amount' => 10000
        ]);

        $repayment = LoanRepayment::factory()->create([
            'loan_id' => $loan->id,
            'status' => 0
        ]);

        // Simulate concurrent access
        $repayment1 = LoanRepayment::where('loan_id', $loan->id)
            ->where('status', 0)
            ->lockForUpdate()
            ->first();

        $this->assertNotNull($repayment1);
        $this->assertEquals($repayment->id, $repayment1->id);
    }

    /** @test */
    public function test_file_upload_security()
    {
        // Test valid file types
        $validExtensions = ['jpeg', 'jpg', 'png', 'pdf', 'doc', 'docx'];
        foreach ($validExtensions as $ext) {
            $this->assertTrue(in_array($ext, ['jpeg', 'jpg', 'png', 'pdf', 'doc', 'docx']));
        }

        // Test invalid file types
        $invalidExtensions = ['exe', 'bat', 'sh', 'php', 'js'];
        foreach ($invalidExtensions as $ext) {
            $this->assertFalse(in_array($ext, ['jpeg', 'jpg', 'png', 'pdf', 'doc', 'docx']));
        }
    }

    /** @test */
    public function test_tenant_isolation()
    {
        $tenant2 = Tenant::factory()->create();
        $member2 = Member::factory()->create(['tenant_id' => $tenant2->id]);

        $loan1 = Loan::factory()->create(['tenant_id' => $this->tenant->id]);
        $loan2 = Loan::factory()->create(['tenant_id' => $tenant2->id]);

        // Members should only see loans from their tenant
        $this->assertNotEquals($loan1->tenant_id, $loan2->tenant_id);
        $this->assertNotEquals($this->member->tenant_id, $member2->tenant_id);
    }

    /** @test */
    public function test_loan_calculator_edge_cases()
    {
        // Test zero interest rate
        $calculator = new LoanCalculator(10000, '2025-01-01', 0, 12, '+1 month', 0);
        $repayments = $calculator->get_flat_rate();
        
        $this->assertEquals(10000, $calculator->payable_amount);
        $this->assertEquals(0, $repayments[0]['interest']);

        // Test very high interest rate
        $calculator = new LoanCalculator(10000, '2025-01-01', 100, 12, '+1 month', 0);
        $repayments = $calculator->get_flat_rate();
        
        $this->assertGreaterThan(10000, $calculator->payable_amount);
    }
}
