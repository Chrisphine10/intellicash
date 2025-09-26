<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'payroll_period_id',
        'employee_id',
        'basic_salary',
        'overtime_hours',
        'overtime_rate',
        'overtime_pay',
        'bonus',
        'commission',
        'allowances',
        'other_earnings',
        'gross_pay',
        'income_tax',
        'social_security',
        'health_insurance',
        'retirement_contribution',
        'loan_deductions',
        'other_deductions',
        'total_deductions',
        'health_benefits',
        'retirement_benefits',
        'other_benefits',
        'total_benefits',
        'net_pay',
        'status',
        'custom_earnings',
        'custom_deductions',
        'custom_benefits',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'overtime_rate' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus' => 'decimal:2',
        'commission' => 'decimal:2',
        'allowances' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'income_tax' => 'decimal:2',
        'social_security' => 'decimal:2',
        'health_insurance' => 'decimal:2',
        'retirement_contribution' => 'decimal:2',
        'loan_deductions' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'health_benefits' => 'decimal:2',
        'retirement_benefits' => 'decimal:2',
        'other_benefits' => 'decimal:2',
        'total_benefits' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'custom_earnings' => 'array',
        'custom_deductions' => 'array',
        'custom_benefits' => 'array',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByPeriod($query, $periodId)
    {
        return $query->where('payroll_period_id', $periodId);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    // Accessors & Mutators
    protected function formattedGrossPay(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->gross_pay, 2),
        );
    }

    protected function formattedNetPay(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->net_pay, 2),
        );
    }

    protected function formattedTotalDeductions(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_deductions, 2),
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'draft' => '<span class="badge bg-secondary">Draft</span>',
                'approved' => '<span class="badge bg-success">Approved</span>',
                'paid' => '<span class="badge bg-primary">Paid</span>',
                'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
                default => '<span class="badge bg-secondary">Unknown</span>',
            },
        );
    }

    // Methods
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function canBeApproved()
    {
        return $this->status === 'draft';
    }

    public function canBePaid()
    {
        return $this->status === 'approved';
    }

    public function approve($userId = null)
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->status = 'approved';
        $this->approved_by = $userId ?? auth()->id();
        $this->approved_at = now();
        $this->save();
        
        return true;
    }

    public function markAsPaid()
    {
        if (!$this->canBePaid()) {
            return false;
        }

        $this->status = 'paid';
        $this->save();
        
        return true;
    }

    public function calculateTotals()
    {
        // Calculate gross pay
        $this->gross_pay = $this->basic_salary + 
                          $this->overtime_pay + 
                          $this->bonus + 
                          $this->commission + 
                          $this->allowances + 
                          $this->other_earnings;

        // Calculate total deductions
        $this->total_deductions = $this->income_tax + 
                                $this->social_security + 
                                $this->health_insurance + 
                                $this->retirement_contribution + 
                                $this->loan_deductions + 
                                $this->other_deductions;

        // Calculate total benefits
        $this->total_benefits = $this->health_benefits + 
                              $this->retirement_benefits + 
                              $this->other_benefits;

        // Calculate net pay
        $this->net_pay = $this->gross_pay - $this->total_deductions + $this->total_benefits;

        $this->save();
    }

    public function calculateOvertimePay()
    {
        if ($this->overtime_hours > 0 && $this->overtime_rate > 0) {
            $this->overtime_pay = $this->overtime_hours * $this->overtime_rate;
        } else {
            $this->overtime_pay = 0;
        }
        
        $this->save();
    }

    public function addCustomEarning($name, $amount, $description = null)
    {
        // Validate inputs
        if (empty($name) || !is_string($name)) {
            throw new \InvalidArgumentException('Earning name is required and must be a string');
        }
        
        if (!is_numeric($amount) || $amount < 0) {
            throw new \InvalidArgumentException('Earning amount must be a positive number');
        }
        
        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('Earning name cannot exceed 255 characters');
        }
        
        if (strlen($description ?? '') > 1000) {
            throw new \InvalidArgumentException('Earning description cannot exceed 1000 characters');
        }

        $earnings = $this->custom_earnings ?: [];
        $earnings[] = [
            'name' => $name,
            'amount' => $amount,
            'description' => $description,
            'added_at' => now()->toISOString(),
            'added_by' => auth()->id(),
        ];
        
        $this->custom_earnings = $earnings;
        $this->other_earnings += $amount;
        $this->save();
    }

    public function addCustomDeduction($name, $amount, $description = null)
    {
        // Validate inputs
        if (empty($name) || !is_string($name)) {
            throw new \InvalidArgumentException('Deduction name is required and must be a string');
        }
        
        if (!is_numeric($amount) || $amount < 0) {
            throw new \InvalidArgumentException('Deduction amount must be a positive number');
        }
        
        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('Deduction name cannot exceed 255 characters');
        }
        
        if (strlen($description ?? '') > 1000) {
            throw new \InvalidArgumentException('Deduction description cannot exceed 1000 characters');
        }

        $deductions = $this->custom_deductions ?: [];
        $deductions[] = [
            'name' => $name,
            'amount' => $amount,
            'description' => $description,
            'added_at' => now()->toISOString(),
            'added_by' => auth()->id(),
        ];
        
        $this->custom_deductions = $deductions;
        $this->other_deductions += $amount;
        $this->save();
    }

    public function addCustomBenefit($name, $amount, $description = null)
    {
        // Validate inputs
        if (empty($name) || !is_string($name)) {
            throw new \InvalidArgumentException('Benefit name is required and must be a string');
        }
        
        if (!is_numeric($amount) || $amount < 0) {
            throw new \InvalidArgumentException('Benefit amount must be a positive number');
        }
        
        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('Benefit name cannot exceed 255 characters');
        }
        
        if (strlen($description ?? '') > 1000) {
            throw new \InvalidArgumentException('Benefit description cannot exceed 1000 characters');
        }

        $benefits = $this->custom_benefits ?: [];
        $benefits[] = [
            'name' => $name,
            'amount' => $amount,
            'description' => $description,
            'added_at' => now()->toISOString(),
            'added_by' => auth()->id(),
        ];
        
        $this->custom_benefits = $benefits;
        $this->other_benefits += $amount;
        $this->save();
    }

    public function getCustomEarnings()
    {
        return $this->custom_earnings ?: [];
    }

    public function getCustomDeductions()
    {
        return $this->custom_deductions ?: [];
    }

    public function getCustomBenefits()
    {
        return $this->custom_benefits ?: [];
    }

    public function getPayrollSummary()
    {
        return [
            'employee' => $this->employee->full_name ?? 'Unknown',
            'period' => $this->payrollPeriod->period_name ?? 'Unknown',
            'gross_pay' => $this->gross_pay,
            'total_deductions' => $this->total_deductions,
            'total_benefits' => $this->total_benefits,
            'net_pay' => $this->net_pay,
            'status' => $this->status,
        ];
    }

    // Static methods
    public static function getStatuses()
    {
        return [
            'draft' => 'Draft',
            'approved' => 'Approved',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function createForEmployee($employee, $payrollPeriod, $data = [])
    {
        // Calculate overtime rate based on pay frequency
        $overtimeRate = 0;
        if ($employee->basic_salary > 0) {
            switch ($employee->pay_frequency ?? 'monthly') {
                case 'weekly':
                    $overtimeRate = $employee->basic_salary / 40; // 40 hours per week
                    break;
                case 'bi_weekly':
                    $overtimeRate = $employee->basic_salary / 80; // 80 hours per bi-week
                    break;
                case 'monthly':
                    $overtimeRate = $employee->basic_salary / 173.33; // 173.33 hours per month (40*52/12)
                    break;
                case 'quarterly':
                    $overtimeRate = $employee->basic_salary / 520; // 520 hours per quarter
                    break;
                case 'annually':
                    $overtimeRate = $employee->basic_salary / 2080; // 2080 hours per year
                    break;
                default:
                    $overtimeRate = $employee->basic_salary / 173.33; // Default to monthly
            }
        }

        $defaultData = [
            'tenant_id' => $employee->tenant_id,
            'payroll_period_id' => $payrollPeriod->id,
            'employee_id' => $employee->id,
            'basic_salary' => $employee->basic_salary,
            'overtime_hours' => 0,
            'overtime_rate' => $overtimeRate,
            'overtime_pay' => 0,
            'bonus' => 0,
            'commission' => 0,
            'allowances' => 0,
            'other_earnings' => 0,
            'gross_pay' => $employee->basic_salary,
            'income_tax' => 0,
            'social_security' => 0,
            'health_insurance' => 0,
            'retirement_contribution' => 0,
            'loan_deductions' => 0,
            'other_deductions' => 0,
            'total_deductions' => 0,
            'health_benefits' => 0,
            'retirement_benefits' => 0,
            'other_benefits' => 0,
            'total_benefits' => 0,
            'net_pay' => $employee->basic_salary,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $payrollItem = self::create(array_merge($defaultData, $data));
        $payrollItem->calculateTotals();
        
        return $payrollItem;
    }
}