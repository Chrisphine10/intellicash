<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\PayrollDeduction;
use App\Models\PayrollBenefit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollDeductionTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    /** @test */
    public function can_calculate_percentage_deduction()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 10.0
        ]);

        $amount = $deduction->calculateDeduction(50000);
        $this->assertEquals(5000, $amount);
    }

    /** @test */
    public function can_calculate_fixed_amount_deduction()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'fixed_amount',
            'amount' => 5000
        ]);

        $amount = $deduction->calculateDeduction(50000);
        $this->assertEquals(5000, $amount);
    }

    /** @test */
    public function can_calculate_tiered_deduction()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'tiered',
            'tiered_rates' => [
                ['min' => 0, 'max' => 10000, 'rate' => 5],
                ['min' => 10000, 'max' => 50000, 'rate' => 10],
                ['min' => 50000, 'max' => PHP_FLOAT_MAX, 'rate' => 15]
            ]
        ]);

        $amount = $deduction->calculateDeduction(60000);
        // 10000 * 0.05 + 40000 * 0.10 + 10000 * 0.15 = 500 + 4000 + 1500 = 6000
        $this->assertEquals(6000, $amount);
    }

    /** @test */
    public function minimum_amount_constraint_works()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 5.0,
            'minimum_amount' => 1000
        ]);

        $amount = $deduction->calculateDeduction(10000); // 5% of 10000 = 500
        $this->assertEquals(1000, $amount); // Should be minimum amount
    }

    /** @test */
    public function maximum_amount_constraint_works()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 20.0,
            'maximum_amount' => 5000
        ]);

        $amount = $deduction->calculateDeduction(50000); // 20% of 50000 = 10000
        $this->assertEquals(5000, $amount); // Should be maximum amount
    }

    /** @test */
    public function can_create_default_deductions()
    {
        PayrollDeduction::createDefaultDeductions($this->tenant->id, 1);

        $this->assertDatabaseHas('payroll_deductions', [
            'tenant_id' => $this->tenant->id,
            'code' => 'PAYE'
        ]);

        $this->assertDatabaseHas('payroll_deductions', [
            'tenant_id' => $this->tenant->id,
            'code' => 'NSSF'
        ]);

        $this->assertDatabaseHas('payroll_deductions', [
            'tenant_id' => $this->tenant->id,
            'code' => 'NHIF'
        ]);
    }

    /** @test */
    public function formatted_rate_accessor_works()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 15.5
        ]);

        $this->assertEquals('15.5%', $deduction->getFormattedRate());

        $deduction->update(['type' => 'fixed_amount', 'amount' => 5000]);
        $this->assertEquals('KSh 5,000.00', $deduction->getFormattedRate());

        $deduction->update(['type' => 'tiered']);
        $this->assertEquals('Tiered', $deduction->getFormattedRate());
    }

    /** @test */
    public function is_applicable_to_employee_works()
    {
        $deduction = PayrollDeduction::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicable_employees' => [1, 2, 3]
        ]);

        $this->assertTrue($deduction->isApplicableToEmployee(1));
        $this->assertTrue($deduction->isApplicableToEmployee(2));
        $this->assertFalse($deduction->isApplicableToEmployee(4));

        // Test with no specific employees (applies to all)
        $deduction->update(['applicable_employees' => null]);
        $this->assertTrue($deduction->isApplicableToEmployee(999));
    }
}

class PayrollBenefitTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    /** @test */
    public function can_calculate_percentage_benefit()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 15.0
        ]);

        $amount = $benefit->calculateBenefit(50000);
        $this->assertEquals(7500, $amount);
    }

    /** @test */
    public function can_calculate_fixed_amount_benefit()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'fixed_amount',
            'amount' => 10000
        ]);

        $amount = $benefit->calculateBenefit(50000);
        $this->assertEquals(10000, $amount);
    }

    /** @test */
    public function can_calculate_tiered_benefit()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'tiered',
            'tiered_rates' => [
                ['min' => 0, 'max' => 20000, 'rate' => 10],
                ['min' => 20000, 'max' => 50000, 'rate' => 15],
                ['min' => 50000, 'max' => PHP_FLOAT_MAX, 'rate' => 20]
            ]
        ]);

        $amount = $benefit->calculateBenefit(60000);
        // 20000 * 0.10 + 30000 * 0.15 + 10000 * 0.20 = 2000 + 4500 + 2000 = 8500
        $this->assertEquals(8500, $amount);
    }

    /** @test */
    public function minimum_amount_constraint_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 5.0,
            'minimum_amount' => 2000
        ]);

        $amount = $benefit->calculateBenefit(20000); // 5% of 20000 = 1000
        $this->assertEquals(2000, $amount); // Should be minimum amount
    }

    /** @test */
    public function maximum_amount_constraint_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 25.0,
            'maximum_amount' => 8000
        ]);

        $amount = $benefit->calculateBenefit(50000); // 25% of 50000 = 12500
        $this->assertEquals(8000, $amount); // Should be maximum amount
    }

    /** @test */
    public function can_create_default_benefits()
    {
        PayrollBenefit::createDefaultBenefits($this->tenant->id, 1);

        $this->assertDatabaseHas('payroll_benefits', [
            'tenant_id' => $this->tenant->id,
            'code' => 'HA'
        ]);

        $this->assertDatabaseHas('payroll_benefits', [
            'tenant_id' => $this->tenant->id,
            'code' => 'TA'
        ]);

        $this->assertDatabaseHas('payroll_benefits', [
            'tenant_id' => $this->tenant->id,
            'code' => 'CA'
        ]);
    }

    /** @test */
    public function is_effective_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'effective_date' => now()->subDays(10),
            'expiry_date' => now()->addDays(10)
        ]);

        $this->assertTrue($benefit->isEffective());

        // Test expired benefit
        $benefit->update(['expiry_date' => now()->subDays(1)]);
        $this->assertFalse($benefit->isEffective());

        // Test future benefit
        $benefit->update([
            'effective_date' => now()->addDays(1),
            'expiry_date' => now()->addDays(10)
        ]);
        $this->assertFalse($benefit->isEffective());
    }

    /** @test */
    public function formatted_rate_accessor_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'percentage',
            'rate' => 12.5
        ]);

        $this->assertEquals('12.5%', $benefit->getFormattedRate());

        $benefit->update(['type' => 'fixed_amount', 'amount' => 8000]);
        $this->assertEquals('KSh 8,000.00', $benefit->getFormattedRate());

        $benefit->update(['type' => 'tiered']);
        $this->assertEquals('Tiered', $benefit->getFormattedRate());
    }

    /** @test */
    public function formatted_amount_accessor_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'amount' => 15000
        ]);

        $this->assertEquals('KSh 15,000.00', $benefit->getFormattedAmount());
    }

    /** @test */
    public function is_applicable_to_employee_works()
    {
        $benefit = PayrollBenefit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'applicable_employees' => [1, 2, 3]
        ]);

        $this->assertTrue($benefit->isApplicableToEmployee(1));
        $this->assertTrue($benefit->isApplicableToEmployee(2));
        $this->assertFalse($benefit->isApplicableToEmployee(4));

        // Test with no specific employees (applies to all)
        $benefit->update(['applicable_employees' => null]);
        $this->assertTrue($benefit->isApplicableToEmployee(999));
    }
}
