<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\BankTransaction;
use App\Models\BankAccount;
use App\Models\SavingsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankingService
{
    /**
     * Process a member transaction and update the linked bank account
     *
     * @param Transaction $transaction
     * @return bool
     */
    public function processMemberTransaction(Transaction $transaction): bool
    {
        try {
            DB::beginTransaction();

            // Get the savings account and its product
            $savingsAccount = $transaction->account;
            if (!$savingsAccount) {
                Log::warning('Transaction has no linked savings account', [
                    'transaction_id' => $transaction->id,
                    'savings_account_id' => $transaction->savings_account_id
                ]);
                DB::rollback();
                return false;
            }

            // Get bank account from savings product (account type)
            $savingsProduct = $savingsAccount->savings_type;
            if (!$savingsProduct || !$savingsProduct->bank_account_id) {
                Log::warning('Savings product has no linked bank account', [
                    'transaction_id' => $transaction->id,
                    'savings_product_id' => $savingsProduct->id ?? 'unknown'
                ]);
                DB::rollback();
                return false;
            }

            $bankAccount = $savingsProduct->bank_account;
            if (!$bankAccount) {
                Log::error('Linked bank account not found', [
                    'transaction_id' => $transaction->id,
                    'bank_account_id' => $savingsProduct->bank_account_id
                ]);
                DB::rollback();
                return false;
            }

            // Create bank transaction
            $bankTransaction = new BankTransaction();
            $bankTransaction->trans_date = $transaction->trans_date;
            $bankTransaction->bank_account_id = $bankAccount->id;
            $bankTransaction->amount = $transaction->amount;
            $bankTransaction->dr_cr = $transaction->dr_cr; // Same direction as member transaction
            $bankTransaction->type = $this->mapTransactionType($transaction->type);
            $bankTransaction->status = $transaction->status == 2 ? 1 : 0; // Map status
            $bankTransaction->description = $transaction->description . ' (Member: ' . $transaction->member->first_name . ' ' . $transaction->member->last_name . ')';
            $bankTransaction->created_user_id = $transaction->created_user_id;
            $bankTransaction->tenant_id = $transaction->tenant_id;
            $bankTransaction->save();

            // Update bank account balance
            $this->updateBankAccountBalance($bankAccount, $transaction->amount, $transaction->dr_cr);

            DB::commit();
            
            Log::info('Bank transaction created successfully', [
                'member_transaction_id' => $transaction->id,
                'bank_transaction_id' => $bankTransaction->id,
                'bank_account_id' => $bankAccount->id,
                'amount' => $transaction->amount,
                'dr_cr' => $transaction->dr_cr
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error processing member transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update bank account balance based on transaction
     *
     * @param BankAccount $bankAccount
     * @param float $amount
     * @param string $drCr
     * @return void
     */
    private function updateBankAccountBalance(BankAccount $bankAccount, float $amount, string $drCr): void
    {
        if ($drCr === 'cr') {
            // Credit - increase balance
            $bankAccount->current_balance += $amount;
        } else {
            // Debit - decrease balance
            $bankAccount->current_balance -= $amount;
        }

        $bankAccount->last_balance_update = now();
        $bankAccount->save();
    }

    /**
     * Map member transaction type to bank transaction type
     *
     * @param string $memberTransactionType
     * @return string
     */
    private function mapTransactionType(string $memberTransactionType): string
    {
        $mapping = [
            'Deposit' => 'deposit',
            'Withdraw' => 'withdraw',
            'Shares_Purchase' => 'deposit', // Shares purchase is a deposit to bank
            'Shares_Sale' => 'withdraw', // Shares sale is a withdrawal from bank
            'Loan' => 'loan_disbursement',
            'Loan_Payment' => 'loan_repayment',
            'Interest' => 'deposit', // Interest posting is a deposit
            'Fee' => 'deposit', // Fee collection is a deposit
            'Penalty' => 'deposit' // Penalty collection is a deposit
        ];

        return $mapping[$memberTransactionType] ?? 'deposit';
    }

    /**
     * Process a loan disbursement from bank account
     *
     * @param int $bankAccountId
     * @param float $amount
     * @param int $memberId
     * @param int $loanId
     * @param string $description
     * @return bool
     */
    public function processLoanDisbursement(int $bankAccountId, float $amount, int $memberId, int $loanId, string $description): bool
    {
        try {
            DB::beginTransaction();

            $bankAccount = BankAccount::find($bankAccountId);
            if (!$bankAccount) {
                Log::error('Bank account not found for loan disbursement', ['bank_account_id' => $bankAccountId]);
                DB::rollback();
                return false;
            }

            // Check sufficient balance
            if (!$bankAccount->hasSufficientBalance($amount)) {
                Log::error('Insufficient balance for loan disbursement', [
                    'bank_account_id' => $bankAccountId,
                    'required_amount' => $amount,
                    'available_balance' => $bankAccount->available_balance
                ]);
                DB::rollback();
                return false;
            }

            // Create bank transaction
            $bankTransaction = new BankTransaction();
            $bankTransaction->trans_date = now();
            $bankTransaction->bank_account_id = $bankAccountId;
            $bankTransaction->amount = $amount;
            $bankTransaction->dr_cr = 'dr'; // Debit from bank account
            $bankTransaction->type = 'loan_disbursement';
            $bankTransaction->status = 1; // Approved
            $bankTransaction->description = $description;
            $bankTransaction->created_user_id = auth()->id();
            $bankTransaction->loan_id = $loanId;
            $bankTransaction->save();

            // Update bank account balance
            $bankAccount->current_balance -= $amount;
            $bankAccount->last_balance_update = now();
            $bankAccount->save();

            DB::commit();
            
            Log::info('Loan disbursement processed successfully', [
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'loan_id' => $loanId,
                'bank_transaction_id' => $bankTransaction->id
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error processing loan disbursement', [
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'loan_id' => $loanId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get bank account balance for a member's savings account
     *
     * @param int $savingsAccountId
     * @return float|null
     */
    public function getMemberBankBalance(int $savingsAccountId): ?float
    {
        $savingsAccount = SavingsAccount::with('savings_type.bank_account')->find($savingsAccountId);
        
        if (!$savingsAccount || !$savingsAccount->savings_type || !$savingsAccount->savings_type->bank_account) {
            return null;
        }

        return $savingsAccount->savings_type->bank_account->current_balance;
    }

    /**
     * Reconcile bank account balance with transactions
     *
     * @param int $bankAccountId
     * @return array
     */
    public function reconcileBankAccount(int $bankAccountId): array
    {
        $bankAccount = BankAccount::find($bankAccountId);
        if (!$bankAccount) {
            return ['success' => false, 'message' => 'Bank account not found'];
        }

        $calculatedBalance = $bankAccount->recalculateBalance();
        $currentBalance = $bankAccount->current_balance;
        $difference = $currentBalance - $calculatedBalance;

        return [
            'success' => true,
            'current_balance' => $currentBalance,
            'calculated_balance' => $calculatedBalance,
            'difference' => $difference,
            'is_balanced' => abs($difference) < 0.01
        ];
    }
}
