<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BankTransaction extends Model
{
    use MultiTenant;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bank_transactions';

    /**
     * Transaction status constants
     */
    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_CANCELLED = 3;

    /**
     * Transaction type constants
     */
    const TYPE_DEPOSIT = 'deposit';
    const TYPE_WITHDRAW = 'withdraw';
    const TYPE_TRANSFER = 'transfer';
    const TYPE_CASH_TO_BANK = 'cash_to_bank';
    const TYPE_BANK_TO_CASH = 'bank_to_cash';
    const TYPE_LOAN_DISBURSEMENT = 'loan_disbursement';
    const TYPE_LOAN_REPAYMENT = 'loan_repayment';
    const TYPE_ASSET_PURCHASE = 'asset_purchase';
    const TYPE_ASSET_SALE = 'asset_sale';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenant_id',
        'trans_date',
        'bank_account_id',
        'amount',
        'dr_cr',
        'type',
        'cheque_number',
        'attachment',
        'status',
        'description',
        'created_user_id',
    ];

    protected $casts = [
        'trans_date' => 'date',
        'amount' => 'decimal:2',
        'status' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            $transaction->validateTransaction();
        });

        static::updating(function ($transaction) {
            $transaction->validateTransaction();
        });
    }

    public function getTransDateAttribute($value) {
        $date_format = get_date_format();
        return \Carbon\Carbon::parse($value)->format("$date_format");
    }

    public function bankAccount(){
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withDefault();
    }

    // Keep backward compatibility for existing code
    public function bank_account(){
        return $this->belongsTo(BankAccount::class, 'bank_account_id')->withDefault();
    }

    public function createdBy(){
        return $this->belongsTo(User::class, 'created_user_id')->withDefault(['name' => _lang('N/A')]);
    }

    // Keep backward compatibility for existing code
    public function created_by(){
        return $this->belongsTo(User::class, 'created_user_id')->withDefault(['name' => _lang('N/A')]);
    }

    /**
     * Validate transaction before saving
     */
    protected function validateTransaction()
    {
        $bankAccount = $this->bankAccount;
        
        if (!$bankAccount) {
            throw ValidationException::withMessages([
                'bank_account_id' => ['Bank account not found']
            ]);
        }

        // Check if account is active
        if (!$bankAccount->is_active) {
            throw ValidationException::withMessages([
                'bank_account_id' => ['Bank account is not active']
            ]);
        }

        // Validate transaction date
        $transDate = \Carbon\Carbon::parse($this->trans_date);
        $openingDate = \Carbon\Carbon::parse($bankAccount->opening_date);
        
        if ($transDate->lt($openingDate)) {
            throw ValidationException::withMessages([
                'trans_date' => ['Transaction date cannot be before account opening date']
            ]);
        }

        // Validate amount
        if ($this->amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be greater than zero']
            ]);
        }

        // Check sufficient balance for debit transactions
        if ($this->dr_cr === 'dr' && $this->status == self::STATUS_APPROVED) {
            if (!$bankAccount->hasSufficientBalance($this->amount)) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance. Available: ' . $bankAccount->formatted_balance]
                ]);
            }
        }

        // Validate transaction type
        $validTypes = [
            self::TYPE_DEPOSIT,
            self::TYPE_WITHDRAW,
            self::TYPE_TRANSFER,
            self::TYPE_CASH_TO_BANK,
            self::TYPE_BANK_TO_CASH,
            self::TYPE_LOAN_DISBURSEMENT,
            self::TYPE_LOAN_REPAYMENT,
            self::TYPE_ASSET_PURCHASE,
            self::TYPE_ASSET_SALE
        ];

        if (!in_array($this->type, $validTypes)) {
            throw ValidationException::withMessages([
                'type' => ['Invalid transaction type']
            ]);
        }

        // Validate status
        if (!in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_CANCELLED])) {
            throw ValidationException::withMessages([
                'status' => ['Invalid transaction status']
            ]);
        }
    }

    /**
     * Scope for approved transactions
     */
    public function scopeApproved(Builder $query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for pending transactions
     */
    public function scopePending(Builder $query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for credit transactions
     */
    public function scopeCredits(Builder $query)
    {
        return $query->where('dr_cr', 'cr');
    }

    /**
     * Scope for debit transactions
     */
    public function scopeDebits(Builder $query)
    {
        return $query->where('dr_cr', 'dr');
    }

    /**
     * Scope for transactions by type
     */
    public function scopeByType(Builder $query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for transactions by date range
     */
    public function scopeByDateRange(Builder $query, $startDate, $endDate)
    {
        return $query->whereBetween('trans_date', [$startDate, $endDate]);
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmountAttribute()
    {
        return decimalPlace($this->amount, currency($this->bankAccount->currency->name));
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $statusLabels = [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled'
        ];

        return $statusLabels[$this->status] ?? 'Unknown';
    }

    /**
     * Get transaction type label
     */
    public function getTypeLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->type));
    }

    /**
     * Check if transaction can be edited
     */
    public function canBeEdited()
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if transaction can be cancelled
     */
    public function canBeCancelled()
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Approve transaction
     */
    public function approve()
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending transactions can be approved']
            ]);
        }

        $this->update(['status' => self::STATUS_APPROVED]);
    }

    /**
     * Reject transaction
     */
    public function reject()
    {
        if ($this->status !== self::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Only pending transactions can be rejected']
            ]);
        }

        $this->update(['status' => self::STATUS_REJECTED]);
    }

    /**
     * Cancel transaction
     */
    public function cancel()
    {
        if (!$this->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['Transaction cannot be cancelled']
            ]);
        }

        $this->update(['status' => self::STATUS_CANCELLED]);
    }
}