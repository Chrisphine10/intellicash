<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class BankAccount extends Model
{
    use MultiTenant;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bank_accounts';

    protected $fillable = [
        'tenant_id',
        'opening_date',
        'bank_name',
        'currency_id',
        'account_name',
        'account_number',
        'opening_balance',
        'current_balance',
        'blocked_balance',
        'last_balance_update',
        'is_active',
        'allow_negative_balance',
        'minimum_balance',
        'maximum_balance',
        'description',
    ];

    protected $casts = [
        'opening_date' => 'date',
        'current_balance' => 'decimal:2',
        'blocked_balance' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'maximum_balance' => 'decimal:2',
        'is_active' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'last_balance_update' => 'datetime',
    ];

    public function currency() {
		return $this->belongsTo('App\Models\Currency', 'currency_id')->withDefault([
			'name' => 'KES',
			'full_name' => 'Kenyan Shilling'
		]);
	}

    public function bankTransactions()
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function vslaTransactions()
    {
        return $this->hasMany(VslaTransaction::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function getFormattedOpeningDateAttribute() {
        $date_format = get_date_format();
        return $this->opening_date->format("$date_format");
    }

    /**
     * Get available balance (current balance - blocked balance)
     */
    public function getAvailableBalanceAttribute()
    {
        return $this->current_balance - $this->blocked_balance;
    }

    /**
     * Check if account has sufficient balance for a transaction
     */
    public function hasSufficientBalance($amount, $includeBlocked = false)
    {
        $balance = $includeBlocked ? $this->current_balance : $this->available_balance;
        
        if (!$this->allow_negative_balance) {
            return $balance >= $amount;
        }
        
        return true; // Allow negative balances
    }

    /**
     * Get balance with currency formatting
     */
    public function getFormattedBalanceAttribute()
    {
        return decimalPlace($this->current_balance, currency($this->currency->name));
    }

    /**
     * Scope for active accounts
     */
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for accounts allowing negative balances
     */
    public function scopeAllowNegative(Builder $query)
    {
        return $query->where('allow_negative_balance', true);
    }

    /**
     * Scope for accounts by currency
     */
    public function scopeByCurrency(Builder $query, $currencyId)
    {
        return $query->where('currency_id', $currencyId);
    }

    /**
     * Scope for VSLA internal accounts
     */
    public function scopeVslaInternal(Builder $query)
    {
        return $query->where('bank_name', 'VSLA Internal');
    }

    /**
     * Recalculate balance from transactions (for reconciliation)
     */
    public function recalculateBalance()
    {
        $credits = $this->bankTransactions()
            ->where('dr_cr', 'cr')
            ->where('status', 1)
            ->sum('amount');

        $debits = $this->bankTransactions()
            ->where('dr_cr', 'dr')
            ->where('status', 1)
            ->sum('amount');

        $calculatedBalance = $credits - $debits;
        
        // Update only if there's a discrepancy
        if (abs($this->current_balance - $calculatedBalance) > 0.01) {
            $this->update([
                'current_balance' => $calculatedBalance,
                'last_balance_update' => now()
            ]);
        }

        return $calculatedBalance;
    }

    /**
     * Check if account can be deleted (no transactions)
     */
    public function canBeDeleted()
    {
        return $this->bankTransactions()->count() === 0 && 
               $this->transactions()->count() === 0 &&
               $this->vslaTransactions()->count() === 0;
    }

    /**
     * Get balance history for a date range
     */
    public function getBalanceHistory($startDate, $endDate)
    {
        return $this->bankTransactions()
            ->whereBetween('trans_date', [$startDate, $endDate])
            ->where('status', 1)
            ->orderBy('trans_date')
            ->get();
    }
}