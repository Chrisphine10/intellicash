<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class AssetLease extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'member_id',
        'lease_number',
        'start_date',
        'end_date',
        'daily_rate',
        'total_amount',
        'deposit_amount',
        'status',
        'terms_conditions',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeByMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    protected function startDate(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format"),
        );
    }

    protected function endDate(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => $value ? \Carbon\Carbon::parse($value)->format("$date_format") : null,
        );
    }

    public function getDurationInDaysAttribute()
    {
        if (!$this->end_date) {
            return null;
        }
        
        // Use diffInDays + 1 for inclusive day counting (both start and end dates count)
        return \Carbon\Carbon::parse($this->start_date)->diffInDays(\Carbon\Carbon::parse($this->end_date)) + 1;
    }

    public function getTotalCostAttribute()
    {
        if ($this->total_amount) {
            return $this->total_amount;
        }

        if ($this->end_date) {
            $days = $this->duration_in_days;
            return $days * $this->daily_rate;
        }

        return 0;
    }

    public function getIsOverdueAttribute()
    {
        if (!$this->end_date || $this->status !== 'active') {
            return false;
        }

        return \Carbon\Carbon::parse($this->end_date)->isPast();
    }

    public function calculateTotalAmount()
    {
        if ($this->end_date) {
            $days = $this->duration_in_days;
            $this->total_amount = $days * $this->daily_rate;
            $this->save();
        }
    }

    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->calculateTotalAmount();
        $this->save();
    }

    public function markAsOverdue()
    {
        $this->status = 'overdue';
        $this->save();
    }

    public function cancel()
    {
        $this->status = 'cancelled';
        $this->save();
    }

    public static function generateLeaseNumber($tenantId)
    {
        $prefix = 'LEASE-';
        $lastLease = static::where('tenant_id', $tenantId)
                          ->where('lease_number', 'like', $prefix . '%')
                          ->orderBy('id', 'desc')
                          ->first();

        if ($lastLease) {
            $lastNumber = (int) str_replace($prefix, '', $lastLease->lease_number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }
}
