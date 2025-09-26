<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeBenefit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'benefit_id',
        'rate',
        'amount',
        'minimum_amount',
        'maximum_amount',
        'is_active',
        'effective_date',
        'expiry_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'amount' => 'decimal:2',
        'minimum_amount' => 'decimal:2',
        'maximum_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_date' => 'date',
        'expiry_date' => 'date',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function benefit()
    {
        return $this->belongsTo(PayrollBenefit::class, 'benefit_id');
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
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByBenefit($query, $benefitId)
    {
        return $query->where('benefit_id', $benefitId);
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
    public function calculateBenefit($grossPay)
    {
        $benefit = $this->benefit;
        if (!$benefit) {
            return 0;
        }

        $amount = 0;

        // Use employee-specific overrides if available
        $rate = $this->rate ?? $benefit->rate;
        $fixedAmount = $this->amount ?? $benefit->amount;
        $minAmount = $this->minimum_amount ?? $benefit->minimum_amount;
        $maxAmount = $this->maximum_amount ?? $benefit->maximum_amount;

        switch ($benefit->type) {
            case 'percentage':
                $amount = $grossPay * ($rate / 100);
                break;
            case 'fixed_amount':
                $amount = $fixedAmount;
                break;
            case 'tiered':
                $amount = $this->calculateTieredAmount($grossPay, $benefit);
                break;
        }

        // Apply minimum and maximum limits
        if ($minAmount > 0 && $amount < $minAmount) {
            $amount = $minAmount;
        }

        if ($maxAmount > 0 && $amount > $maxAmount) {
            $amount = $maxAmount;
        }

        return round($amount, 2);
    }

    public function calculateTieredAmount($grossPay, $benefit)
    {
        $tieredRates = $benefit->tiered_rates;
        if (!$tieredRates) {
            return 0;
        }

        $amount = 0;
        $remainingPay = $grossPay;

        foreach ($tieredRates as $tier) {
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

    public function isEffective()
    {
        $now = now()->toDateString();
        
        if ($this->effective_date && $this->effective_date > $now) {
            return false;
        }
        
        if ($this->expiry_date && $this->expiry_date < $now) {
            return false;
        }
        
        return $this->is_active;
    }

    public function getFormattedRate()
    {
        if ($this->rate !== null) {
            return $this->rate . '%';
        }
        
        return $this->benefit ? $this->benefit->getFormattedRate() : 'N/A';
    }

    public function getFormattedAmount()
    {
        if ($this->amount !== null) {
            return '$' . number_format($this->amount, 2);
        }
        
        return $this->benefit ? $this->benefit->getFormattedAmount() : 'N/A';
    }

    // Static methods
    public static function assignBenefitToEmployee($employeeId, $benefitId, $overrides = [])
    {
        $benefit = PayrollBenefit::find($benefitId);
        if (!$benefit) {
            return false;
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return false;
        }

        // Check if assignment already exists
        $existing = self::where('employee_id', $employeeId)
                      ->where('benefit_id', $benefitId)
                      ->first();

        if ($existing) {
            return $existing;
        }

        $data = [
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employeeId,
            'benefit_id' => $benefitId,
            'created_by' => auth()->id(),
        ];

        // Apply overrides
        if (isset($overrides['rate'])) {
            $data['rate'] = $overrides['rate'];
        }
        if (isset($overrides['amount'])) {
            $data['amount'] = $overrides['amount'];
        }
        if (isset($overrides['minimum_amount'])) {
            $data['minimum_amount'] = $overrides['minimum_amount'];
        }
        if (isset($overrides['maximum_amount'])) {
            $data['maximum_amount'] = $overrides['maximum_amount'];
        }
        if (isset($overrides['effective_date'])) {
            $data['effective_date'] = $overrides['effective_date'];
        }
        if (isset($overrides['expiry_date'])) {
            $data['expiry_date'] = $overrides['expiry_date'];
        }
        if (isset($overrides['notes'])) {
            $data['notes'] = $overrides['notes'];
        }

        return self::create($data);
    }

    public static function removeBenefitFromEmployee($employeeId, $benefitId)
    {
        return self::where('employee_id', $employeeId)
                  ->where('benefit_id', $benefitId)
                  ->delete();
    }
}