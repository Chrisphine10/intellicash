<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class BankBalanceService
{
    /**
     * Get real-time balance for a bank account
     */
    public function getBalance(BankAccount $bankAccount): float
    {
        // Use cached balance if available and recent (within 1 minute)
        $cacheKey = "bank_balance_{$bankAccount->id}";
        
        return Cache::remember($cacheKey, 60, function () use ($bankAccount) {
            return $this->calculateBalance($bankAccount);
        });
    }

    /**
     * Calculate balance from transactions
     */
    public function calculateBalance(BankAccount $bankAccount): float
    {
        $result = DB::table('bank_transactions')
            ->where('bank_account_id', $bankAccount->id)
            ->where('status', BankTransaction::STATUS_APPROVED)
            ->selectRaw('
                SUM(CASE WHEN dr_cr = "cr" THEN amount ELSE 0 END) as credits,
                SUM(CASE WHEN dr_cr = "dr" THEN amount ELSE 0 END) as debits
            ')
            ->first();

        $credits = $result->credits ?? 0;
        $debits = $result->debits ?? 0;

        return $credits - $debits;
    }

    /**
     * Update cached balance for a bank account
     */
    public function updateCachedBalance(BankAccount $bankAccount): float
    {
        $balance = $this->calculateBalance($bankAccount);
        
        // Update the cached balance
        $cacheKey = "bank_balance_{$bankAccount->id}";
        Cache::put($cacheKey, $balance, 3600); // Cache for 1 hour

        // Update the database balance
        $bankAccount->update([
            'current_balance' => $balance,
            'last_balance_update' => now()
        ]);

        return $balance;
    }

    /**
     * Recalculate all bank account balances
     */
    public function recalculateAllBalances(): array
    {
        $results = [];
        
        DB::transaction(function () use (&$results) {
            $bankAccounts = BankAccount::all();
            
            foreach ($bankAccounts as $bankAccount) {
                $oldBalance = $bankAccount->current_balance;
                $newBalance = $this->updateCachedBalance($bankAccount);
                
                $results[] = [
                    'account_id' => $bankAccount->id,
                    'account_name' => $bankAccount->account_name,
                    'old_balance' => $oldBalance,
                    'new_balance' => $newBalance,
                    'difference' => $newBalance - $oldBalance
                ];
            }
        });

        return $results;
    }

    /**
     * Check for balance discrepancies
     */
    public function checkBalanceDiscrepancies(): array
    {
        $discrepancies = [];
        
        $bankAccounts = BankAccount::all();
        
        foreach ($bankAccounts as $bankAccount) {
            $cachedBalance = $bankAccount->current_balance;
            $calculatedBalance = $this->calculateBalance($bankAccount);
            
            if (abs($cachedBalance - $calculatedBalance) > 0.01) {
                $discrepancies[] = [
                    'account_id' => $bankAccount->id,
                    'account_name' => $bankAccount->account_name,
                    'cached_balance' => $cachedBalance,
                    'calculated_balance' => $calculatedBalance,
                    'difference' => $calculatedBalance - $cachedBalance
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * Get balance history for an account
     */
    public function getBalanceHistory(BankAccount $bankAccount, $startDate = null, $endDate = null): array
    {
        $query = DB::table('bank_transactions')
            ->where('bank_account_id', $bankAccount->id)
            ->where('status', BankTransaction::STATUS_APPROVED)
            ->orderBy('trans_date');

        if ($startDate) {
            $query->where('trans_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('trans_date', '<=', $endDate);
        }

        $transactions = $query->get();
        $balance = $bankAccount->opening_balance;
        $history = [];

        foreach ($transactions as $transaction) {
            if ($transaction->dr_cr === 'cr') {
                $balance += $transaction->amount;
            } else {
                $balance -= $transaction->amount;
            }

            $history[] = [
                'date' => $transaction->trans_date,
                'type' => $transaction->type,
                'dr_cr' => $transaction->dr_cr,
                'amount' => $transaction->amount,
                'balance' => $balance,
                'description' => $transaction->description
            ];
        }

        return $history;
    }

    /**
     * Validate transaction against account balance
     */
    public function validateTransaction(BankAccount $bankAccount, $amount, $drCr, $includePending = false): array
    {
        $availableBalance = $this->getBalance($bankAccount);
        
        if (!$includePending) {
            // Subtract pending debit transactions
            $pendingDebits = DB::table('bank_transactions')
                ->where('bank_account_id', $bankAccount->id)
                ->where('dr_cr', 'dr')
                ->where('status', BankTransaction::STATUS_PENDING)
                ->sum('amount');
            
            $availableBalance -= $pendingDebits;
        }

        $isValid = true;
        $message = '';

        if ($drCr === 'dr') {
            $requiredBalance = $amount;
            
            if (!$bankAccount->allow_negative_balance) {
                $requiredBalance += $bankAccount->minimum_balance;
            }

            if ($availableBalance < $requiredBalance) {
                $isValid = false;
                $message = "Insufficient balance. Available: " . number_format($availableBalance, 2) . 
                          ", Required: " . number_format($requiredBalance, 2);
            }
        }

        return [
            'is_valid' => $isValid,
            'message' => $message,
            'available_balance' => $availableBalance,
            'required_balance' => $drCr === 'dr' ? $amount : 0
        ];
    }

    /**
     * Clear balance cache for an account
     */
    public function clearBalanceCache(BankAccount $bankAccount): void
    {
        $cacheKey = "bank_balance_{$bankAccount->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all balance caches
     */
    public function clearAllBalanceCaches(): void
    {
        $bankAccounts = BankAccount::all();
        
        foreach ($bankAccounts as $bankAccount) {
            $this->clearBalanceCache($bankAccount);
        }
    }

    /**
     * Get account balance summary
     */
    public function getBalanceSummary(BankAccount $bankAccount): array
    {
        $currentBalance = $this->getBalance($bankAccount);
        $blockedBalance = $bankAccount->blocked_balance;
        $availableBalance = $currentBalance - $blockedBalance;

        // Get transaction counts
        $transactionCounts = DB::table('bank_transactions')
            ->where('bank_account_id', $bankAccount->id)
            ->where('status', BankTransaction::STATUS_APPROVED)
            ->selectRaw('
                COUNT(CASE WHEN dr_cr = "cr" THEN 1 END) as credit_count,
                COUNT(CASE WHEN dr_cr = "dr" THEN 1 END) as debit_count,
                SUM(CASE WHEN dr_cr = "cr" THEN amount ELSE 0 END) as total_credits,
                SUM(CASE WHEN dr_cr = "dr" THEN amount ELSE 0 END) as total_debits
            ')
            ->first();

        return [
            'current_balance' => $currentBalance,
            'blocked_balance' => $blockedBalance,
            'available_balance' => $availableBalance,
            'opening_balance' => $bankAccount->opening_balance,
            'credit_count' => $transactionCounts->credit_count ?? 0,
            'debit_count' => $transactionCounts->debit_count ?? 0,
            'total_credits' => $transactionCounts->total_credits ?? 0,
            'total_debits' => $transactionCounts->total_debits ?? 0,
            'last_update' => $bankAccount->last_balance_update,
            'is_active' => $bankAccount->is_active,
            'allow_negative' => $bankAccount->allow_negative_balance,
            'minimum_balance' => $bankAccount->minimum_balance,
            'maximum_balance' => $bankAccount->maximum_balance
        ];
    }
}
