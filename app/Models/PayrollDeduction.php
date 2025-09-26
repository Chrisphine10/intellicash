<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollDeduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'type',
        'rate',
        'amount',
        'tiered_rates',
        'minimum_amount',
        'maximum_amount',
        'is_mandatory',
        'is_active',
        'tax_category',
        'applicable_employees',
        'calculation_rules',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'tiered_rates' => 'array',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
        'applicable_employees' => 'array',
        'calculation_rules' => 'array',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeDeduction::class, 'deduction_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function calculateDeduction($grossPay, $employee = null)
    {
        $amount = 0;

        switch ($this->type) {
            case 'percentage':
                $amount = $grossPay * ($this->rate / 100);
                break;
            case 'fixed_amount':
                $amount = $this->amount;
                break;
            case 'tiered':
                $amount = $this->calculateTieredAmount($grossPay);
                break;
        }

        // Apply minimum and maximum limits
        if ($this->minimum_amount > 0 && $amount < $this->minimum_amount) {
            $amount = $this->minimum_amount;
        }

        if ($this->maximum_amount > 0 && $amount > $this->maximum_amount) {
            $amount = $this->maximum_amount;
        }

        return round($amount, 2);
    }

    public function calculateTieredAmount($grossPay)
    {
        if (!$this->tiered_rates) {
            return 0;
        }

        $amount = 0;
        $remainingPay = $grossPay;

        foreach ($this->tiered_rates as $tier) {
            $tierMin = $tier['min'] ?? 0;
            $tierMax = $tier['max'] ?? PHP_FLOAT_MAX;
            $tierRate = $tier['rate'] ?? 0;

            if ($remainingPay <= 0) {
                break;
            }

            $tierAmount = min($remainingPay, $tierMax - $tierMin);
            if ($tierAmount > 0) {
                $amount += $tierAmount * ($tierRate / 100);
                $remainingPay -= $tierAmount;
            }
        }

        return $amount;
    }

    public function isApplicableToEmployee($employeeId)
    {
        if (!$this->applicable_employees) {
            return true; // Apply to all employees if no specific criteria
        }

        return in_array($employeeId, $this->applicable_employees);
    }

    public function getFormattedRate()
    {
        switch ($this->type) {
            case 'percentage':
                return $this->rate . '%';
            case 'fixed_amount':
                return 'KSh ' . number_format($this->amount, 2);
            case 'tiered':
                return 'Tiered';
            default:
                return 'N/A';
        }
    }

    // Static methods
    public static function getTypes()
    {
        return [
            'percentage' => 'Percentage',
            'fixed_amount' => 'Fixed Amount',
            'tiered' => 'Tiered',
        ];
    }

    public static function getTaxCategories()
    {
        return [
            'income_tax' => 'Income Tax (PAYE)',
            'social_security' => 'Social Security (NSSF)',
            'health_insurance' => 'Health Insurance (NHIF)',
            'training_levy' => 'Training Levy (NITA)',
            'education_loan' => 'Education Loan (HELB)',
            'savings' => 'Savings (SACCO)',
            'other' => 'Other',
        ];
    }

    public static function createDefaultDeductions($tenantId, $createdBy)
    {
        $defaultDeductions = [
            [
                'name' => 'PAYE (Pay As You Earn)',
                'code' => 'PAYE',
                'description' => 'Kenyan income tax deduction',
                'type' => 'percentage',
                'rate' => 10.0,
                'is_mandatory' => true,
                'tax_category' => 'income_tax',
            ],
            [
                'name' => 'NSSF (National Social Security Fund)',
                'code' => 'NSSF',
                'description' => 'Kenyan social security contribution',
                'type' => 'percentage',
                'rate' => 6.0,
                'is_mandatory' => true,
                'tax_category' => 'social_security',
            ],
            [
                'name' => 'NHIF (National Hospital Insurance Fund)',
                'code' => 'NHIF',
                'description' => 'Kenyan health insurance premium',
                'type' => 'fixed_amount',
                'amount' => 500.0,
                'is_mandatory' => true,
                'tax_category' => 'health_insurance',
            ],
            [
                'name' => 'NITA (National Industrial Training Authority)',
                'code' => 'NITA',
                'description' => 'Kenyan industrial training levy',
                'type' => 'percentage',
                'rate' => 1.0,
                'is_mandatory' => true,
                'tax_category' => 'training_levy',
            ],
            [
                'name' => 'HELB (Higher Education Loans Board)',
                'code' => 'HELB',
                'description' => 'Kenyan higher education loan repayment',
                'type' => 'percentage',
                'rate' => 2.0,
                'is_mandatory' => false,
                'tax_category' => 'education_loan',
            ],
            [
                'name' => 'Sacco Contribution',
                'code' => 'SACCO',
                'description' => 'Savings and Credit Cooperative contribution',
                'type' => 'percentage',
                'rate' => 5.0,
                'is_mandatory' => false,
                'tax_category' => 'savings',
            ],
        ];

        foreach ($defaultDeductions as $deductionData) {
            // Use updateOrCreate to handle existing records
            self::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => $deductionData['code']
                ],
                array_merge($deductionData, [
                    'tenant_id' => $tenantId,
                    'created_by' => $createdBy,
                ])
            );
        }
    }
}