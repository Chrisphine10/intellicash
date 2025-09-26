<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollItem;
use App\Models\PayrollDeduction;
use App\Models\PayrollBenefit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PayrollItemTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $employee;
    protected $payrollPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'basic_salary' => 50000.00,
            'pay_frequency' => 'monthly'
        ]);
        $this->payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function can_create_payroll_item_for_employee()
    {
        $payrollItem = PayrollItem::createForEmployee($this->employee, $this->payrollPeriod);

        $this->assertInstanceOf(PayrollItem::class, $payrollItem);
        $this->assertEquals($this->employee->id, $payrollItem->employee_id);
        $this->assertEquals($this->payrollPeriod->id, $payrollItem->payroll_period_id);
        $this->assertEquals($this->employee->basic_salary, $payrollItem->basic_salary);
    }

    /** @test */
    public function overtime_rate_calculation_works_for_different_frequencies()
    {
        // Test monthly frequency
        $payrollItem = PayrollItem::createForEmployee($this->employee, $this->payrollPeriod);
        $expectedRate = 50000 / 173.33; // Monthly calculation
        $this->assertEquals($expectedRate, $payrollItem->overtime_rate, '', 0.01);

        // Test weekly frequency
        $this->employee->update(['pay_frequency' => 'weekly']);
        $payrollItem = PayrollItem::createForEmployee($this->employee, $this->payrollPeriod);
        $expectedRate = 50000 / 40; // Weekly calculation
        $this->assertEquals($expectedRate, $payrollItem->overtime_rate, '', 0.01);
    }

    /** @test */
    public function calculate_totals_works_correctly()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'basic_salary' => 50000,
            'overtime_pay' => 5000,
            'bonus' => 2000,
            'commission' => 1000,
            'allowances' => 3000,
            'other_earnings' => 1000,
            'income_tax' => 5000,
            'social_security' => 3000,
            'health_insurance' => 500,
            'retirement_contribution' => 2000,
            'loan_deductions' => 1000,
            'other_deductions' => 500,
            'health_benefits' => 2000,
            'retirement_benefits' => 3000,
            'other_benefits' => 1000
        ]);

        $payrollItem->calculateTotals();

        $expectedGrossPay = 50000 + 5000 + 2000 + 1000 + 3000 + 1000; // 62000
        $expectedDeductions = 5000 + 3000 + 500 + 2000 + 1000 + 500; // 12000
        $expectedBenefits = 2000 + 3000 + 1000; // 6000
        $expectedNetPay = $expectedGrossPay - $expectedDeductions + $expectedBenefits; // 56000

        $this->assertEquals($expectedGrossPay, $payrollItem->gross_pay);
        $this->assertEquals($expectedDeductions, $payrollItem->total_deductions);
        $this->assertEquals($expectedBenefits, $payrollItem->total_benefits);
        $this->assertEquals($expectedNetPay, $payrollItem->net_pay);
    }

    /** @test */
    public function calculate_overtime_pay_works()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'overtime_hours' => 10,
            'overtime_rate' => 500
        ]);

        $payrollItem->calculateOvertimePay();

        $expectedOvertimePay = 10 * 500; // 5000
        $this->assertEquals($expectedOvertimePay, $payrollItem->overtime_pay);
    }

    /** @test */
    public function can_add_custom_earning()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'other_earnings' => 0
        ]);

        $payrollItem->addCustomEarning('Performance Bonus', 2000, 'Q4 performance bonus');

        $this->assertEquals(2000, $payrollItem->other_earnings);
        $this->assertCount(1, $payrollItem->custom_earnings);
        $this->assertEquals('Performance Bonus', $payrollItem->custom_earnings[0]['name']);
        $this->assertEquals(2000, $payrollItem->custom_earnings[0]['amount']);
    }

    /** @test */
    public function can_add_custom_deduction()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'other_deductions' => 0
        ]);

        $payrollItem->addCustomDeduction('Advance Payment', 5000, 'Salary advance');

        $this->assertEquals(5000, $payrollItem->other_deductions);
        $this->assertCount(1, $payrollItem->custom_deductions);
        $this->assertEquals('Advance Payment', $payrollItem->custom_deductions[0]['name']);
        $this->assertEquals(5000, $payrollItem->custom_deductions[0]['amount']);
    }

    /** @test */
    public function can_add_custom_benefit()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'other_benefits' => 0
        ]);

        $payrollItem->addCustomBenefit('Transport Allowance', 3000, 'Monthly transport allowance');

        $this->assertEquals(3000, $payrollItem->other_benefits);
        $this->assertCount(1, $payrollItem->custom_benefits);
        $this->assertEquals('Transport Allowance', $payrollItem->custom_benefits[0]['name']);
        $this->assertEquals(3000, $payrollItem->custom_benefits[0]['amount']);
    }

    /** @test */
    public function custom_earning_validation_works()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $payrollItem->addCustomEarning('', 1000); // Empty name

        $this->expectException(\InvalidArgumentException::class);
        $payrollItem->addCustomEarning('Valid Name', -1000); // Negative amount

        $this->expectException(\InvalidArgumentException::class);
        $payrollItem->addCustomEarning('Valid Name', 'invalid'); // Non-numeric amount
    }

    /** @test */
    public function can_approve_payroll_item()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'status' => 'draft'
        ]);

        $result = $payrollItem->approve();

        $this->assertTrue($result);
        $this->assertEquals('approved', $payrollItem->status);
        $this->assertNotNull($payrollItem->approved_at);
    }

    /** @test */
    public function can_mark_payroll_item_as_paid()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'status' => 'approved'
        ]);

        $result = $payrollItem->markAsPaid();

        $this->assertTrue($result);
        $this->assertEquals('paid', $payrollItem->status);
    }

    /** @test */
    public function cannot_approve_non_draft_payroll_item()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'status' => 'approved'
        ]);

        $result = $payrollItem->approve();

        $this->assertFalse($result);
        $this->assertEquals('approved', $payrollItem->status);
    }

    /** @test */
    public function cannot_pay_non_approved_payroll_item()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'status' => 'draft'
        ]);

        $result = $payrollItem->markAsPaid();

        $this->assertFalse($result);
        $this->assertEquals('draft', $payrollItem->status);
    }

    /** @test */
    public function status_badge_accessor_works()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'status' => 'approved'
        ]);

        $badge = $payrollItem->status_badge;
        $this->assertStringContains('badge', $badge);
        $this->assertStringContains('Approved', $badge);
    }

    /** @test */
    public function get_payroll_summary_works()
    {
        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $this->payrollPeriod->id,
            'employee_id' => $this->employee->id,
            'gross_pay' => 50000,
            'total_deductions' => 10000,
            'total_benefits' => 5000,
            'net_pay' => 45000,
            'status' => 'approved'
        ]);

        $summary = $payrollItem->getPayrollSummary();

        $this->assertArrayHasKey('employee', $summary);
        $this->assertArrayHasKey('period', $summary);
        $this->assertEquals(50000, $summary['gross_pay']);
        $this->assertEquals(10000, $summary['total_deductions']);
        $this->assertEquals(5000, $summary['total_benefits']);
        $this->assertEquals(45000, $summary['net_pay']);
        $this->assertEquals('approved', $summary['status']);
    }
}
