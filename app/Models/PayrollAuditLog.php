<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PayrollAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
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

    // Scopes
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByModel($query, $modelType, $modelId = null)
    {
        $query = $query->where('model_type', $modelType);
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        return $query;
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Static methods for logging
    public static function logAction($action, $modelType, $modelId, $oldValues = null, $newValues = null, $description = null, $metadata = [])
    {
        $user = Auth::user();
        $tenant = app('tenant');

        return self::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public static function logPayrollPeriodAction($action, $payrollPeriod, $description = null, $metadata = [])
    {
        return self::logAction(
            $action,
            'PayrollPeriod',
            $payrollPeriod->id,
            null,
            $payrollPeriod->toArray(),
            $description,
            $metadata
        );
    }

    public static function logPayrollItemAction($action, $payrollItem, $oldValues = null, $description = null, $metadata = [])
    {
        return self::logAction(
            $action,
            'PayrollItem',
            $payrollItem->id,
            $oldValues,
            $payrollItem->toArray(),
            $description,
            $metadata
        );
    }

    public static function logDeductionAction($action, $deduction, $oldValues = null, $description = null, $metadata = [])
    {
        return self::logAction(
            $action,
            'PayrollDeduction',
            $deduction->id,
            $oldValues,
            $deduction->toArray(),
            $description,
            $metadata
        );
    }

    public static function logBenefitAction($action, $benefit, $oldValues = null, $description = null, $metadata = [])
    {
        return self::logAction(
            $action,
            'PayrollBenefit',
            $benefit->id,
            $oldValues,
            $benefit->toArray(),
            $description,
            $metadata
        );
    }

    public static function logEmployeeAction($action, $employee, $oldValues = null, $description = null, $metadata = [])
    {
        return self::logAction(
            $action,
            'Employee',
            $employee->id,
            $oldValues,
            $employee->toArray(),
            $description,
            $metadata
        );
    }

    // Helper methods
    public function getFormattedAction()
    {
        return ucfirst(str_replace('_', ' ', $this->action));
    }

    public function getChangesSummary()
    {
        if (!$this->old_values || !$this->new_values) {
            return 'No changes recorded';
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[] = "{$key}: {$oldValue} → {$newValue}";
            }
        }

        return implode(', ', $changes);
    }

    public function isFinancialChange()
    {
        $financialFields = [
            'basic_salary', 'overtime_pay', 'bonus', 'commission', 'allowances',
            'gross_pay', 'income_tax', 'social_security', 'health_insurance',
            'retirement_contribution', 'loan_deductions', 'total_deductions',
            'health_benefits', 'retirement_benefits', 'total_benefits', 'net_pay',
            'rate', 'amount', 'minimum_amount', 'maximum_amount'
        ];

        if ($this->old_values && $this->new_values) {
            foreach ($financialFields as $field) {
                if (isset($this->old_values[$field]) && isset($this->new_values[$field]) &&
                    $this->old_values[$field] !== $this->new_values[$field]) {
                    return true;
                }
            }
        }

        return false;
    }
}
