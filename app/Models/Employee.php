<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'member_id',
        'employee_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'address',
        'date_of_birth',
        'gender',
        'national_id',
        'passport_number',
        'hire_date',
        'termination_date',
        'employment_status',
        'job_title',
        'department',
        'employment_type',
        'basic_salary',
        'salary_currency',
        'pay_frequency',
        'bank_name',
        'bank_account_number',
        'bank_code',
        'tax_number',
        'insurance_number',
        'emergency_contact',
        'benefits',
        'deductions',
        'notes',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'basic_salary' => 'decimal:2',
        'emergency_contact' => 'array',
        'benefits' => 'array',
        'deductions' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function employeeBenefits()
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('employment_status', 'active');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByEmploymentType($query, $type)
    {
        return $query->where('employment_type', $type);
    }

    // Accessors & Mutators
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim($this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name),
        );
    }

    protected function age(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->date_of_birth ? Carbon::parse($this->date_of_birth)->age : null,
        );
    }

    protected function yearsOfService(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->hire_date ? Carbon::parse($this->hire_date)->diffInYears(Carbon::now()) : 0,
        );
    }

    protected function formattedSalary(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->basic_salary ? number_format($this->basic_salary, 2) . ' ' . $this->salary_currency : '0.00 ' . $this->salary_currency,
        );
    }

    // Methods
    public function isActive()
    {
        return $this->is_active && $this->employment_status === 'active';
    }

    public function getCurrentPayrollItem($periodId)
    {
        return $this->payrollItems()->where('payroll_period_id', $periodId)->first();
    }

    public function getActiveDeductions()
    {
        return $this->employeeDeductions()->where('is_active', true)->with('deduction')->get();
    }

    public function getActiveBenefits()
    {
        return $this->employeeBenefits()->where('is_active', true)->with('benefit')->get();
    }

    public function calculateGrossPay($overtimeHours = 0, $bonus = 0, $allowances = 0)
    {
        $overtimePay = $overtimeHours * ($this->basic_salary / 160); // Assuming 40 hours per week
        return $this->basic_salary + $overtimePay + $bonus + $allowances;
    }

    public function getBankDetails()
    {
        return [
            'bank_name' => $this->bank_name,
            'account_number' => $this->bank_account_number,
            'bank_code' => $this->bank_code,
        ];
    }

    public function getEmergencyContact()
    {
        return $this->emergency_contact ?: [];
    }

    // Static methods
    public static function generateEmployeeId($tenantId)
    {
        $lastEmployee = self::where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->first();
        
        $nextNumber = $lastEmployee ? (intval(substr($lastEmployee->employee_id, -4)) + 1) : 1;
        
        return 'EMP' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public static function getDepartments($tenantId)
    {
        return self::where('tenant_id', $tenantId)
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department')
            ->filter()
            ->sort()
            ->values();
    }

    public static function getEmploymentTypes()
    {
        return [
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'intern' => 'Intern',
        ];
    }

    public static function getEmploymentStatuses()
    {
        return [
            'active' => 'Active',
            'terminated' => 'Terminated',
            'on_leave' => 'On Leave',
            'suspended' => 'Suspended',
        ];
    }

    public static function getPayFrequencies()
    {
        return [
            'weekly' => 'Weekly',
            'bi_weekly' => 'Bi-Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
        ];
    }

    /**
     * Get the employee account type setting for the current tenant
     */
    public static function getEmployeeAccountType($tenantId = null)
    {
        if (!$tenantId) {
            $tenantId = app('tenant')->id ?? null;
        }
        
        return get_tenant_option('employee_account_type', 'system_users', $tenantId);
    }

    /**
     * Check if this employee is linked to a system user
     */
    public function isSystemUser()
    {
        return !is_null($this->user_id);
    }

    /**
     * Check if this employee is linked to a member account
     */
    public function isMemberAccount()
    {
        return !is_null($this->member_id);
    }

    /**
     * Get the linked account (user or member)
     */
    public function getLinkedAccount()
    {
        if ($this->isSystemUser()) {
            return $this->user;
        } elseif ($this->isMemberAccount()) {
            return $this->member;
        }
        
        return null;
    }
}