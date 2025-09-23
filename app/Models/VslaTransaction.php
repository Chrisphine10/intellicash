<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaTransaction extends Model
{
    use MultiTenant;

    protected $table = 'vsla_transactions';

    protected $fillable = [
        'tenant_id',
        'cycle_id',
        'meeting_id',
        'member_id',
        'transaction_type',
        'amount',
        'shares',
        'description',
        'transaction_id',
        'loan_id',
        'savings_account_id',
        'bank_account_id',
        'status',
        'created_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        // Ensure transaction is assigned to the correct cycle based on date
        static::creating(function ($transaction) {
            static::assignToCorrectCycle($transaction);
        });

        static::updating(function ($transaction) {
            if ($transaction->isDirty('created_at') || $transaction->isDirty('cycle_id')) {
                static::assignToCorrectCycle($transaction);
            }
        });
    }

    /**
     * Assign transaction to the correct cycle based on creation date
     */
    protected static function assignToCorrectCycle($transaction)
    {
        if (!$transaction->cycle_id) {
            // Find the active cycle for this tenant that contains the transaction date
            $cycle = VslaCycle::where('tenant_id', $transaction->tenant_id)
                ->where('status', 'active')
                ->where('start_date', '<=', $transaction->created_at ?? now())
                ->where('end_date', '>=', $transaction->created_at ?? now())
                ->first();

            if ($cycle) {
                $transaction->cycle_id = $cycle->id;
            } else {
                throw new \Exception('No active cycle found for the transaction date. Please ensure there is an active cycle covering this period.');
            }
        }
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cycle()
    {
        return $this->belongsTo(VslaCycle::class, 'cycle_id');
    }

    public function meeting()
    {
        return $this->belongsTo(VslaMeeting::class, 'meeting_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function loan()
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function savingsAccount()
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function createdUser()
    {
        return $this->belongsTo(User::class, 'created_user_id');
    }

    public function scopeSharePurchase($query)
    {
        return $query->where('transaction_type', 'share_purchase');
    }

    public function scopeLoanIssuance($query)
    {
        return $query->where('transaction_type', 'loan_issuance');
    }

    public function scopeLoanRepayment($query)
    {
        return $query->where('transaction_type', 'loan_repayment');
    }

    public function scopePenaltyFine($query)
    {
        return $query->where('transaction_type', 'penalty_fine');
    }

    public function scopeWelfareContribution($query)
    {
        return $query->where('transaction_type', 'welfare_contribution');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
