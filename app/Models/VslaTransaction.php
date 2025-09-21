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
        'meeting_id',
        'member_id',
        'transaction_type',
        'amount',
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
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
