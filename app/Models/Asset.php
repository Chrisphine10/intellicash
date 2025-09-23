<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Asset extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'asset_code',
        'description',
        'purchase_value',
        'current_value',
        'purchase_date',
        'warranty_expiry',
        'location',
        'status',
        'is_leasable',
        'lease_rate',
        'lease_rate_type',
        'notes',
        'metadata',
        'depreciation_method',
        'useful_life',
        'salvage_value',
    ];

    protected $casts = [
        'purchase_value' => 'decimal:2',
        'current_value' => 'decimal:2',
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'is_leasable' => 'boolean',
        'lease_rate' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'useful_life' => 'integer',
        'metadata' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function leases()
    {
        return $this->hasMany(AssetLease::class, 'asset_id');
    }

    public function activeLeases()
    {
        return $this->hasMany(AssetLease::class, 'asset_id')->where('status', 'active');
    }

    public function maintenance()
    {
        return $this->hasMany(AssetMaintenance::class, 'asset_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLeasable($query)
    {
        return $query->where('is_leasable', true);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeAvailableForLease($query)
    {
        return $query->where('is_leasable', true)
                    ->where('status', 'active')
                    ->whereDoesntHave('activeLeases');
    }

    protected function purchaseDate(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => \Carbon\Carbon::parse($value)->format("$date_format"),
        );
    }

    protected function warrantyExpiry(): Attribute
    {
        $date_format = get_date_format();
        return Attribute::make(
            get: fn($value) => $value ? \Carbon\Carbon::parse($value)->format("$date_format") : null,
        );
    }

    public function getTotalLeaseRevenueAttribute()
    {
        return $this->leases()->where('status', 'completed')->sum('total_amount');
    }

    public function getCurrentLeaseAttribute()
    {
        return $this->activeLeases()->first();
    }

    public function isAvailableForLease()
    {
        return $this->is_leasable && 
               $this->status === 'active' && 
               $this->activeLeases()->count() === 0;
    }

    public function getDepreciationValue($years = null)
    {
        if (!$this->purchase_date || !$this->purchase_value) {
            return 0;
        }

        $purchaseDate = \Carbon\Carbon::parse($this->purchase_date);
        $ageInYears = $years ?? $purchaseDate->diffInYears(now());
        
        // Simple straight-line depreciation (5% per year)
        $depreciationRate = 0.05;
        $depreciatedValue = $this->purchase_value * (1 - ($depreciationRate * $ageInYears));
        
        return max(0, $depreciatedValue);
    }

    /**
     * Calculate current value based on depreciation method
     */
    public function calculateCurrentValue($asOfDate = null)
    {
        if (!$this->purchase_date || !$this->purchase_value) {
            return $this->purchase_value ?? 0;
        }

        $asOfDate = $asOfDate ? \Carbon\Carbon::parse($asOfDate) : now();
        $purchaseDate = \Carbon\Carbon::parse($this->purchase_date);
        $yearsOwned = $purchaseDate->diffInYears($asOfDate);

        if ($yearsOwned <= 0) {
            return $this->purchase_value;
        }

        switch ($this->depreciation_method) {
            case 'straight_line':
                if (!$this->useful_life) return $this->purchase_value;
                $annualDepreciation = ($this->purchase_value - ($this->salvage_value ?? 0)) / $this->useful_life;
                $totalDepreciation = $annualDepreciation * min($yearsOwned, $this->useful_life);
                break;
            case 'declining_balance':
                if (!$this->useful_life) return $this->purchase_value;
                $rate = 2 / $this->useful_life; // Double declining balance
                $totalDepreciation = $this->purchase_value * (1 - pow(1 - $rate, $yearsOwned));
                break;
            default:
                // Default to 5% per year
                $totalDepreciation = $this->purchase_value * 0.05 * $yearsOwned;
        }

        $currentValue = $this->purchase_value - $totalDepreciation;
        return max($currentValue, $this->salvage_value ?? 0);
    }

    /**
     * Get utilization rate for a period
     */
    public function getUtilizationRate($startDate, $endDate)
    {
        if (!$this->is_leasable) {
            return 0;
        }

        $totalDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1;
        $leasedDays = $this->leases()
            ->where('status', 'completed')
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->get()
            ->sum(function($lease) use ($startDate, $endDate) {
                $leaseStart = max(\Carbon\Carbon::parse($lease->start_date), \Carbon\Carbon::parse($startDate));
                $leaseEnd = min(\Carbon\Carbon::parse($lease->end_date), \Carbon\Carbon::parse($endDate));
                return $leaseStart->diffInDays($leaseEnd) + 1;
            });

        return $totalDays > 0 ? ($leasedDays / $totalDays) * 100 : 0;
    }

    /**
     * Get total revenue from leases
     */
    public function getTotalRevenue($startDate = null, $endDate = null)
    {
        $query = $this->leases()->where('status', 'completed');
        
        if ($startDate && $endDate) {
            $query->whereBetween('end_date', [$startDate, $endDate]);
        }
        
        return $query->sum('total_amount');
    }

    /**
     * Get maintenance cost for a period
     */
    public function getMaintenanceCost($startDate = null, $endDate = null)
    {
        $query = $this->maintenance()->where('status', 'completed');
        
        if ($startDate && $endDate) {
            $query->whereBetween('completed_date', [$startDate, $endDate]);
        }
        
        return $query->sum('cost');
    }
}
