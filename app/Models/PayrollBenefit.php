<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollBenefit extends Model
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
        'is_employer_paid',
        'is_active',
        'category',
        'applicable_employees',
        'calculation_rules',
        'effective_date',
        'expiry_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'tiered_rates' => 'array',
        'is_employer_paid' => 'boolean',
        'is_active' => 'boolean',
        'applicable_employees' => 'array',
        'calculation_rules' => 'array',
        'effective_date' => 'date',
        'expiry_date' => 'date',
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

    public function employeeBenefits()
    {
        return $this->hasMany(EmployeeBenefit::class, 'benefit_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEmployerPaid($query)
    {
        return $query->where('is_employer_paid', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeEffective($query)
    {
        $now = now()->toDateString();
        return $query->where(function($q) use ($now) {
            $q->whereNull('effective_date')
              ->orWhere('effective_date', '<=', $now);
        })->where(function($q) use ($now) {
            $q->whereNull('expiry_date')
              ->orWhere('expiry_date', '>=', $now);
        });
    }

    // Methods
    public function calculateBenefit($grossPay, $employee = null)
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

    public function isEffective()
    {
        $now = now()->toDateString();
        
        if ($this->effective_date && $this->effective_date > $now) {
            return false;
        }
        
        if ($this->expiry_date && $this->expiry_date < $now) {
            return false;
        }
        
        return true;
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

    public function getFormattedAmount()
    {
        return 'KSh ' . number_format($this->amount, 2);
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

    public static function getCategories()
    {
        return [
            'health' => 'Health Insurance',
            'retirement' => 'Retirement Plan',
            'life' => 'Life Insurance',
            'transportation' => 'Transportation Allowance',
            'meal' => 'Meal Allowance',
            'housing' => 'Housing Allowance',
            'communication' => 'Communication Allowance',
            'education' => 'Education Assistance',
            'bonus' => 'Performance Bonus',
            'other' => 'Other',
        ];
    }

    public static function createDefaultBenefits($tenantId, $createdBy)
    {
        $defaultBenefits = [
            [
                'name' => 'Housing Allowance',
                'code' => 'HA',
                'description' => 'Monthly housing allowance',
                'type' => 'percentage',
                'rate' => 15.0,
                'is_employer_paid' => true,
                'category' => 'housing',
            ],
            [
                'name' => 'Transportation Allowance',
                'code' => 'TA',
                'description' => 'Monthly transportation allowance',
                'type' => 'fixed_amount',
                'amount' => 5000.00,
                'is_employer_paid' => true,
                'category' => 'transportation',
            ],
            [
                'name' => 'Communication Allowance',
                'code' => 'CA',
                'description' => 'Monthly communication allowance',
                'type' => 'fixed_amount',
                'amount' => 2000.00,
                'is_employer_paid' => true,
                'category' => 'communication',
            ],
            [
                'name' => 'Meal Allowance',
                'code' => 'MA',
                'description' => 'Daily meal allowance',
                'type' => 'fixed_amount',
                'amount' => 500.00,
                'is_employer_paid' => true,
                'category' => 'meal',
            ],
            [
                'name' => 'Medical Cover',
                'code' => 'MC',
                'description' => 'Company medical insurance coverage',
                'type' => 'fixed_amount',
                'amount' => 3000.00,
                'is_employer_paid' => true,
                'category' => 'health',
            ],
            [
                'name' => 'Life Insurance',
                'code' => 'LI',
                'description' => 'Company life insurance coverage',
                'type' => 'fixed_amount',
                'amount' => 1000.00,
                'is_employer_paid' => true,
                'category' => 'life',
            ],
            [
                'name' => 'Performance Bonus',
                'code' => 'PB',
                'description' => 'Annual performance bonus',
                'type' => 'percentage',
                'rate' => 10.0,
                'is_employer_paid' => true,
                'category' => 'bonus',
            ],
        ];

        foreach ($defaultBenefits as $benefitData) {
            // Use updateOrCreate to handle existing records
            self::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'code' => $benefitData['code']
                ],
                array_merge($benefitData, [
                    'tenant_id' => $tenantId,
                    'created_by' => $createdBy,
                ])
            );
        }
    }
}