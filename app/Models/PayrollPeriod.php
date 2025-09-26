<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Carbon\Carbon;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'period_name',
        'start_date',
        'end_date',
        'period_type',
        'status',
        'pay_date',
        'total_gross_pay',
        'total_deductions',
        'total_benefits',
        'total_net_pay',
        'total_employees',
        'notes',
        'processing_log',
        'processed_by',
        'processed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pay_date' => 'date',
        'total_gross_pay' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'total_benefits' => 'decimal:2',
        'total_net_pay' => 'decimal:2',
        'processing_log' => 'array',
        'processed_at' => 'datetime',
    ];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'processing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByPeriodType($query, $type)
    {
        return $query->where('period_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('start_date', [$startDate, $endDate]);
    }

    public function scopeCurrent($query)
    {
        $now = Carbon::now();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
    }

    // Accessors & Mutators
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_date && $this->end_date 
                ? Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1 
                : 0,
        );
    }

    protected function formattedTotalGrossPay(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_gross_pay, 2),
        );
    }

    protected function formattedTotalNetPay(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_net_pay, 2),
        );
    }

    protected function statusBadge(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->status) {
                'draft' => '<span class="badge bg-secondary">Draft</span>',
                'processing' => '<span class="badge bg-warning">Processing</span>',
                'completed' => '<span class="badge bg-success">Completed</span>',
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

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function canBeProcessed()
    {
        return $this->status === 'draft' && $this->payrollItems()->count() > 0;
    }

    public function canBeCancelled()
    {
        return in_array($this->status, ['draft', 'processing']);
    }

    public function process()
    {
        if (!$this->canBeProcessed()) {
            return false;
        }

        $this->status = 'processing';
        $this->processed_by = auth()->id();
        $this->processed_at = now();
        
        // Calculate totals
        $this->calculateTotals();
        
        $this->save();
        
        return true;
    }

    public function complete()
    {
        if ($this->status !== 'processing') {
            return false;
        }

        $this->status = 'completed';
        $this->save();
        
        return true;
    }

    public function cancel()
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->status = 'cancelled';
        $this->save();
        
        return true;
    }

    public function calculateTotals()
    {
        $items = $this->payrollItems;
        
        $this->total_gross_pay = $items->sum('gross_pay');
        $this->total_deductions = $items->sum('total_deductions');
        $this->total_benefits = $items->sum('total_benefits');
        $this->total_net_pay = $items->sum('net_pay');
        $this->total_employees = $items->count();
        
        $this->save();
    }

    public function addProcessingLog($action, $details = null)
    {
        $log = $this->processing_log ?: [];
        $log[] = [
            'action' => $action,
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'user_name' => auth()->user()->name ?? 'System',
        ];
        
        $this->processing_log = $log;
        $this->save();
    }

    public function getProcessingHistory()
    {
        return $this->processing_log ?: [];
    }

    // Static methods
    public static function createMonthlyPeriod($tenantId, $year, $month, $createdBy)
    {
        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();
        $payDate = $endDate->copy()->addDays(7); // Pay 7 days after period end
        
        $periodName = $startDate->format('F Y');
        
        return self::create([
            'tenant_id' => $tenantId,
            'period_name' => $periodName,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_type' => 'monthly',
            'status' => 'draft',
            'pay_date' => $payDate,
            'created_by' => $createdBy,
        ]);
    }

    public static function createWeeklyPeriod($tenantId, $startDate, $createdBy)
    {
        $start = Carbon::parse($startDate);
        $end = $start->copy()->addDays(6);
        $payDate = $end->copy()->addDays(3); // Pay 3 days after period end
        
        $periodName = $start->format('M d') . ' - ' . $end->format('M d, Y');
        
        return self::create([
            'tenant_id' => $tenantId,
            'period_name' => $periodName,
            'start_date' => $start,
            'end_date' => $end,
            'period_type' => 'weekly',
            'status' => 'draft',
            'pay_date' => $payDate,
            'created_by' => $createdBy,
        ]);
    }

    public static function getPeriodTypes()
    {
        return [
            'weekly' => 'Weekly',
            'bi_weekly' => 'Bi-Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'annually' => 'Annually',
        ];
    }

    public static function getStatuses()
    {
        return [
            'draft' => 'Draft',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
    }
}