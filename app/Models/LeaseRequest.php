<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class LeaseRequest extends Model
{
    use HasFactory, MultiTenant;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'asset_id',
        'request_number',
        'start_date',
        'end_date',
        'requested_days',
        'daily_rate',
        'total_amount',
        'deposit_amount',
        'payment_account_id',
        'status',
        'reason',
        'terms_accepted',
        'admin_notes',
        'rejection_reason',
        'processed_by',
        'processed_at',
        'created_user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_days' => 'integer',
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'terms_accepted' => 'boolean',
        'processed_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function paymentAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'payment_account_id');
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
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

    protected function processedAt(): Attribute
    {
        $date_format = get_date_format();
        $time_format = get_time_format();
        return Attribute::make(
            get: fn($value) => $value ? \Carbon\Carbon::parse($value)->format("$date_format $time_format") : null,
        );
    }

    public function getDurationInDaysAttribute()
    {
        if (!$this->end_date) {
            return $this->requested_days;
        }
        
        return \Carbon\Carbon::parse($this->start_date)->diffInDays($this->end_date);
    }

    public function getTotalCostAttribute()
    {
        if ($this->total_amount) {
            return $this->total_amount;
        }

        $days = $this->duration_in_days;
        return $days * $this->daily_rate;
    }

    public function calculateTotalAmount()
    {
        $days = $this->duration_in_days;
        $this->total_amount = $days * $this->daily_rate;
        $this->save();
    }

    public function approve($processedBy, $adminNotes = null)
    {
        $this->status = 'approved';
        $this->processed_by = $processedBy;
        $this->processed_at = now();
        $this->admin_notes = $adminNotes;
        $this->save();
    }

    public function reject($processedBy, $rejectionReason, $adminNotes = null)
    {
        $this->status = 'rejected';
        $this->processed_by = $processedBy;
        $this->processed_at = now();
        $this->rejection_reason = $rejectionReason;
        $this->admin_notes = $adminNotes;
        $this->save();
    }

    public function canBeProcessed()
    {
        return $this->status === 'pending';
    }

    public function canBeApproved()
    {
        return $this->canBeProcessed() && 
               $this->asset->isAvailableForLease() &&
               $this->member->status == 1;
    }

    public static function generateRequestNumber($tenantId)
    {
        $prefix = 'LR-';
        $lastRequest = static::where('tenant_id', $tenantId)
                          ->where('request_number', 'like', $prefix . '%')
                          ->orderBy('id', 'desc')
                          ->first();

        if ($lastRequest) {
            $lastNumber = (int) str_replace($prefix, '', $lastRequest->request_number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function convertToLease()
    {
        if ($this->status !== 'approved') {
            throw new \Exception('Only approved lease requests can be converted to leases');
        }

        $lease = AssetLease::create([
            'tenant_id' => $this->tenant_id,
            'asset_id' => $this->asset_id,
            'member_id' => $this->member_id,
            'lease_number' => AssetLease::generateLeaseNumber($this->tenant_id),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'daily_rate' => $this->daily_rate,
            'total_amount' => $this->total_amount,
            'deposit_amount' => $this->deposit_amount,
            'terms_conditions' => $this->reason,
            'notes' => $this->admin_notes,
            'created_by' => $this->processed_by,
        ]);

        return $lease;
    }
}
