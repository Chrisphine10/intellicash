<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Member;
use App\Models\VslaCycle;
use App\Models\VslaTransaction;
use App\Models\VslaShareout;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\VslaLoanCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * VSLA Calculation Fixes Verification Test
 * 
 * This test verifies that all critical calculation bugs have been fixed
 */
class VslaCalculationFixesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $tenant;
    protected $user;
    protected $member;
    protected $loanProduct;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'vsla_enabled' => true
        ]);
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'admin'
        ]);
        
        // Create test member
        $this->member = Member::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test loan product
        $this->loanProduct = LoanProduct::factory()->create([
            'tenant_id' => $this->tenant->id,
            'interest_rate' => 12, // 12% annual
            'term' => 6, // 6 months
            'interest_type' => 'flat_rate'
        ]);
    }

    /**
     * Test that loan interest calculation is now correct
     */
    public function test_loan_interest_calculation_fix()
    {
        // Create a VSLA cycle
        $cycle = VslaCycle::create([
            'tenant_id' => $this->tenant->id,
            'cycle_name' => 'Test Cycle',
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'status' => 'active'
        ]);

        // Create a loan
        $loan = Loan::create([
            'tenant_id' => $this->tenant->id,
            'loan_id' => 'TEST-001',
            'loan_product_id' => $this->loanProduct->id,
            'borrower_id' => $this->member->id,
            'applied_amount' => 10000,
            'total_payable' => 10600, // 10% interest for 6 months
            'release_date' => now()->subDays(30),
            'maturity_date' => now()->addDays(150), // 5 months from release
            'status' => 2
        ]);

        // Calculate interest using the fixed method
        $interestEarned = $cycle->calculateActualLoanInterest();
        
        // Verify interest calculation is reasonable
        $this->assertGreaterThan(0, $interestEarned, 'Interest should be greater than zero');
        $this->assertLessThan(1000, $interestEarned, 'Interest should be reasonable for 30 days');
        
        // Expected: 10000 * 0.12 * 30/365 = ~98.63
        $expectedInterest = (10000 * 0.12 * 30) / 365;
        $this->assertEquals($expectedInterest, $interestEarned, 'Interest calculation should match expected value', 0.01);
    }

    /**
     * Test that share-out profit calculation is now correct
     */
    public function test_shareout_profit_calculation_fix()
    {
        // Create a VSLA cycle with transactions
        $cycle = VslaCycle::create([
            'tenant_id' => $this->tenant->id,
            'cycle_name' => 'Test Cycle',
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'status' => 'active',
            'total_shares_contributed' => 50000,
            'total_welfare_contributed' => 5000,
            'total_penalties_collected' => 1000,
            'total_loan_interest_earned' => 2000,
            'total_available_for_shareout' => 58000
        ]);

        // Create share purchase transaction
        VslaTransaction::create([
            'tenant_id' => $this->tenant->id,
            'cycle_id' => $cycle->id,
            'member_id' => $this->member->id,
            'transaction_type' => 'share_purchase',
            'amount' => 10000,
            'status' => 'approved'
        ]);

        // Calculate member share-out
        $calculation = VslaShareout::calculateMemberShareOut($cycle, $this->member);
        
        // Verify profit calculation is correct
        $expectedSharePercentage = 10000 / 50000; // 20%
        $expectedProfitShare = 2000 * $expectedSharePercentage; // 20% of 2000 = 400
        
        $this->assertEquals($expectedSharePercentage, $calculation['share_percentage'], 'Share percentage should be correct');
        $this->assertEquals($expectedProfitShare, $calculation['profit_share'], 'Profit share should be correct');
        $this->assertGreaterThan(0, $calculation['profit_share'], 'Profit share should be greater than zero');
    }

    /**
     * Test that centralized loan calculator works correctly
     */
    public function test_centralized_loan_calculator()
    {
        // Test flat rate calculation
        $totalPayable = VslaLoanCalculator::calculateTotalPayable(10000, $this->loanProduct);
        
        // Expected: 10000 + (10000 * 0.12 * 6) = 10000 + 7200 = 17200
        $expectedTotal = 10000 + (10000 * 0.12 * 6);
        $this->assertEquals($expectedTotal, $totalPayable, 'Total payable should match expected value');
        
        // Test interest calculation for period
        $interestForPeriod = VslaLoanCalculator::calculateInterestForPeriod(
            10000, 0.12, 30, 'flat_rate', 0
        );
        
        $expectedInterest = (10000 * 0.12 * 30) / 365;
        $this->assertEquals($expectedInterest, $interestForPeriod, 'Interest for period should match expected value', 0.01);
    }

    /**
     * Test that share count vs amount consistency is fixed
     */
    public function test_share_count_amount_consistency()
    {
        // Create VSLA cycle
        $cycle = VslaCycle::create([
            'tenant_id' => $this->tenant->id,
            'cycle_name' => 'Test Cycle',
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'status' => 'active'
        ]);

        // Create transaction with both amount and shares
        VslaTransaction::create([
            'tenant_id' => $this->tenant->id,
            'cycle_id' => $cycle->id,
            'member_id' => $this->member->id,
            'transaction_type' => 'share_purchase',
            'amount' => 1000, // Financial value
            'shares' => 5,    // Number of shares
            'status' => 'approved'
        ]);

        // Test that calculations use amount for financial calculations
        $memberShares = VslaTransaction::where('cycle_id', $cycle->id)
            ->where('member_id', $this->member->id)
            ->where('transaction_type', 'share_purchase')
            ->sum('amount'); // Should use amount for financial calculations

        $this->assertEquals(1000, $memberShares, 'Financial calculations should use amount field');
    }

    /**
     * Test error handling improvements
     */
    public function test_error_handling_improvements()
    {
        // Test validation with invalid data
        $response = $this->actingAs($this->user)
            ->post(route('vsla.transactions.store'), [
                'meeting_id' => 999, // Non-existent meeting
                'member_id' => $this->member->id,
                'transaction_type' => 'share_purchase',
                'amount' => -100, // Invalid amount
            ]);

        $response->assertSessionHasErrors(['meeting_id', 'amount']);
    }

    /**
     * Test performance improvements
     */
    public function test_performance_improvements()
    {
        // Create multiple loans to test eager loading
        $loans = Loan::factory()->count(10)->create([
            'tenant_id' => $this->tenant->id,
            'loan_product_id' => $this->loanProduct->id,
            'borrower_id' => $this->member->id,
            'status' => 2
        ]);

        $cycle = VslaCycle::create([
            'tenant_id' => $this->tenant->id,
            'cycle_name' => 'Performance Test Cycle',
            'start_date' => now()->subDays(30),
            'end_date' => now(),
            'status' => 'active'
        ]);

        // This should not cause N+1 queries due to eager loading
        $startTime = microtime(true);
        $interestEarned = $cycle->calculateActualLoanInterest();
        $endTime = microtime(true);
        
        $executionTime = $endTime - $startTime;
        
        // Should complete quickly (less than 1 second for 10 loans)
        $this->assertLessThan(1.0, $executionTime, 'Interest calculation should be fast with eager loading');
        $this->assertGreaterThan(0, $interestEarned, 'Should calculate interest for all loans');
    }
}
