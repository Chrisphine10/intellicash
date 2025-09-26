<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollItem;
use App\Models\PayrollDeduction;
use App\Models\PayrollBenefit;
use App\Models\PayrollAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

class PayrollControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $tenant;
    protected $admin;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['user_type' => 'admin']);
        $this->employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'basic_salary' => 50000.00,
            'pay_frequency' => 'monthly'
        ]);
    }

    /** @test */
    public function admin_can_create_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $response = $this->post(route('payroll.periods.store'), [
            'period_name' => 'January 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'period_type' => 'monthly',
            'pay_date' => '2025-02-01',
            'notes' => 'Monthly payroll for January',
            'employee_ids' => [$this->employee->id]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'tenant_id' => $this->tenant->id,
            'period_name' => 'January 2025',
            'period_type' => 'monthly'
        ]);
    }

    /** @test */
    public function non_admin_cannot_create_payroll_period()
    {
        $user = User::factory()->create(['user_type' => 'user']);
        $this->actingAs($user);
        
        $response = $this->post(route('payroll.periods.store'), [
            'period_name' => 'January 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'period_type' => 'monthly'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function payroll_period_validation_works()
    {
        $this->actingAs($this->admin);
        
        $response = $this->post(route('payroll.periods.store'), [
            'period_name' => '',
            'start_date' => 'invalid-date',
            'end_date' => '2025-01-01', // Before start date
            'period_type' => 'invalid_type'
        ]);

        $response->assertSessionHasErrors(['period_name', 'start_date', 'end_date', 'period_type']);
    }

    /** @test */
    public function admin_can_process_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'employee_id' => $this->employee->id
        ]);

        $response = $this->post(route('payroll.periods.process', $payrollPeriod->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $payrollPeriod->id,
            'status' => 'processing'
        ]);
    }

    /** @test */
    public function admin_can_complete_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'processing'
        ]);

        $response = $this->post(route('payroll.periods.complete', $payrollPeriod->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $payrollPeriod->id,
            'status' => 'completed'
        ]);
    }

    /** @test */
    public function admin_can_cancel_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $response = $this->post(route('payroll.periods.cancel', $payrollPeriod->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_periods', [
            'id' => $payrollPeriod->id,
            'status' => 'cancelled'
        ]);
    }

    /** @test */
    public function admin_can_add_employee_to_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $response = $this->post(route('payroll.periods.add-employee', $payrollPeriod->id), [
            'employee_id' => $this->employee->id
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payroll_items', [
            'payroll_period_id' => $payrollPeriod->id,
            'employee_id' => $this->employee->id
        ]);
    }

    /** @test */
    public function admin_can_remove_employee_from_payroll_period()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $payrollItem = PayrollItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'employee_id' => $this->employee->id
        ]);

        $response = $this->post(route('payroll.periods.remove-employee', $payrollPeriod->id), [
            'employee_id' => $this->employee->id
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('payroll_items', [
            'id' => $payrollItem->id
        ]);
    }

    /** @test */
    public function payroll_period_cannot_be_processed_without_employees()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $response = $this->post(route('payroll.periods.process', $payrollPeriod->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function completed_payroll_period_cannot_be_modified()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed'
        ]);

        $response = $this->post(route('payroll.periods.add-employee', $payrollPeriod->id), [
            'employee_id' => $this->employee->id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    /** @test */
    public function audit_log_is_created_for_payroll_actions()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $this->post(route('payroll.periods.process', $payrollPeriod->id));

        $this->assertDatabaseHas('payroll_audit_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'action' => 'processed',
            'model_type' => 'PayrollPeriod',
            'model_id' => $payrollPeriod->id
        ]);
    }

    /** @test */
    public function rate_limiting_works_for_sensitive_operations()
    {
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        // Make multiple requests to trigger rate limiting
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post(route('payroll.periods.process', $payrollPeriod->id));
        }

        $response->assertStatus(429);
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_access()
    {
        $otherTenant = Tenant::factory()->create();
        $otherEmployee = Employee::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $this->actingAs($this->admin);
        
        $payrollPeriod = PayrollPeriod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft'
        ]);

        $response = $this->post(route('payroll.periods.add-employee', $payrollPeriod->id), [
            'employee_id' => $otherEmployee->id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
