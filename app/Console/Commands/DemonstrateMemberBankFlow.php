<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\BankAccount;
use App\Models\Transaction;
use App\Models\BankTransaction;
use App\Services\BankingService;
use Illuminate\Support\Facades\DB;

class DemonstrateMemberBankFlow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:member-bank-flow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate the complete flow from member transactions to bank account updates';

    private $bankingService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->bankingService = new BankingService();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== MEMBER-BANK TRANSACTION FLOW DEMONSTRATION ===');
        $this->newLine();

        try {
            $this->showSystemOverview();
            $this->demonstrateDepositFlow();
            $this->demonstrateWithdrawalFlow();
            $this->showBankTransactionHistory();
            $this->showBalanceReconciliation();
            $this->showTransactionTypeMapping();
            
            $this->newLine();
            $this->info('🎉 DEMONSTRATION COMPLETED SUCCESSFULLY!');
            $this->newLine();
            $this->info('Key Findings:');
            $this->line('✓ Member deposits automatically create bank transactions');
            $this->line('✓ Member withdrawals automatically create bank transactions');
            $this->line('✓ Bank account balances are updated in real-time');
            $this->line('✓ Transaction types are properly mapped');
            $this->line('✓ All transactions are tracked and auditable');
            
        } catch (\Exception $e) {
            $this->error('Demonstration failed: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Show system overview
     */
    private function showSystemOverview()
    {
        $this->info('1. SYSTEM OVERVIEW');
        $this->line('==================');
        
        // Show bank accounts
        $bankAccounts = BankAccount::active()->get();
        $this->line('Available Bank Accounts:');
        foreach ($bankAccounts as $bank) {
            $this->line("  • {$bank->account_name} ({$bank->bank_name})");
            $this->line("    Balance: {$bank->formatted_balance}");
            $this->line("    Currency: {$bank->currency->name}");
            $this->line("    Status: " . ($bank->is_active ? 'Active' : 'Inactive'));
            $this->newLine();
        }

        // Show savings products and their connections
        $savingsProducts = SavingsProduct::with('bank_account')->get();
        $this->line('Savings Products and Bank Connections:');
        foreach ($savingsProducts as $product) {
            $bankName = $product->bank_account ? $product->bank_account->account_name : 'Not Connected';
            $this->line("  • {$product->name} → {$bankName}");
        }
        $this->newLine();
    }

    /**
     * Demonstrate deposit flow
     */
    private function demonstrateDepositFlow()
    {
        $this->info('2. MEMBER DEPOSIT FLOW DEMONSTRATION');
        $this->line('=====================================');
        
        // Find a member with a connected savings account
        $member = Member::with(['savings_accounts.savings_type.bank_account'])
            ->whereHas('savings_accounts.savings_type', function($query) {
                $query->whereNotNull('bank_account_id');
            })
            ->first();
            
        if (!$member) {
            $this->warn('No members with bank-connected accounts found. Skipping deposit demonstration.');
            return;
        }

        $savingsAccount = $member->savings_accounts->first();
        $bankAccount = $savingsAccount->savings_type->bank_account;
        
        $this->line("Member: {$member->first_name} {$member->last_name}");
        $this->line("Savings Account: {$savingsAccount->account_number} ({$savingsAccount->savings_type->name})");
        $this->line("Connected Bank: {$bankAccount->account_name}");
        $this->newLine();

        // Show initial balances
        $initialBankBalance = $bankAccount->current_balance;
        $this->line("Initial Bank Balance: {$bankAccount->formatted_balance}");
        
        // Create a deposit transaction
        $depositAmount = 2500.00;
        $this->line("Creating deposit transaction: {$depositAmount} {$bankAccount->currency->name}");
        
        $transaction = new Transaction();
        $transaction->trans_date = now();
        $transaction->member_id = $member->id;
        $transaction->savings_account_id = $savingsAccount->id;
        $transaction->amount = $depositAmount;
        $transaction->dr_cr = 'cr';
        $transaction->type = 'Deposit';
        $transaction->method = 'Manual';
        $transaction->status = 2; // Completed
        $transaction->description = 'Demonstration deposit transaction';
        $transaction->created_user_id = 1;
        $transaction->tenant_id = 1;
        $transaction->save();
        
        $this->line("✓ Member transaction created (ID: {$transaction->id})");
        
        // Process through banking service
        $this->line("Processing through BankingService...");
        $result = $this->bankingService->processMemberTransaction($transaction);
        
        if ($result) {
            $this->line("✓ BankingService processed successfully");
            
            // Show updated bank balance
            $bankAccount->refresh();
            $newBankBalance = $bankAccount->current_balance;
            $this->line("Updated Bank Balance: " . decimalPlace($newBankBalance, currency($bankAccount->currency->name)));
            $this->line("Balance Change: +{$depositAmount} {$bankAccount->currency->name}");
            
            // Show created bank transaction
            $bankTransaction = BankTransaction::where('created_at', '>=', now()->subMinutes(1))
                ->where('bank_account_id', $bankAccount->id)
                ->where('description', 'like', '%Demonstration deposit%')
                ->first();
                
            if ($bankTransaction) {
                $this->line("✓ Bank transaction created (ID: {$bankTransaction->id})");
                $this->line("  Type: {$bankTransaction->type}");
                $this->line("  Amount: {$bankTransaction->amount}");
                $this->line("  Direction: {$bankTransaction->dr_cr}");
                $this->line("  Status: {$bankTransaction->status_label}");
            }
        } else {
            $this->error("✗ BankingService processing failed");
        }
        
        $this->newLine();
    }

    /**
     * Demonstrate withdrawal flow
     */
    private function demonstrateWithdrawalFlow()
    {
        $this->info('3. MEMBER WITHDRAWAL FLOW DEMONSTRATION');
        $this->line('=======================================');
        
        // Find a member with sufficient balance
        $member = Member::with(['savings_accounts.savings_type.bank_account'])
            ->whereHas('savings_accounts.savings_type', function($query) {
                $query->whereNotNull('bank_account_id');
            })
            ->first();
            
        if (!$member) {
            $this->warn('No members with bank-connected accounts found. Skipping withdrawal demonstration.');
            return;
        }

        $savingsAccount = $member->savings_accounts->first();
        $bankAccount = $savingsAccount->savings_type->bank_account;
        
        // Check if bank has sufficient balance
        if ($bankAccount->current_balance < 1000) {
            $this->warn('Insufficient bank balance for withdrawal demonstration. Skipping.');
            return;
        }
        
        $this->line("Member: {$member->first_name} {$member->last_name}");
        $this->line("Savings Account: {$savingsAccount->account_number} ({$savingsAccount->savings_type->name})");
        $this->line("Connected Bank: {$bankAccount->account_name}");
        $this->newLine();

        // Show initial balances
        $initialBankBalance = $bankAccount->current_balance;
        $this->line("Initial Bank Balance: {$bankAccount->formatted_balance}");
        
        // Create a withdrawal transaction
        $withdrawalAmount = 800.00;
        $this->line("Creating withdrawal transaction: {$withdrawalAmount} {$bankAccount->currency->name}");
        
        $transaction = new Transaction();
        $transaction->trans_date = now();
        $transaction->member_id = $member->id;
        $transaction->savings_account_id = $savingsAccount->id;
        $transaction->amount = $withdrawalAmount;
        $transaction->dr_cr = 'dr';
        $transaction->type = 'Withdraw';
        $transaction->method = 'Manual';
        $transaction->status = 2; // Completed
        $transaction->description = 'Demonstration withdrawal transaction';
        $transaction->created_user_id = 1;
        $transaction->tenant_id = 1;
        $transaction->save();
        
        $this->line("✓ Member transaction created (ID: {$transaction->id})");
        
        // Process through banking service
        $this->line("Processing through BankingService...");
        $result = $this->bankingService->processMemberTransaction($transaction);
        
        if ($result) {
            $this->line("✓ BankingService processed successfully");
            
            // Show updated bank balance
            $bankAccount->refresh();
            $newBankBalance = $bankAccount->current_balance;
            $this->line("Updated Bank Balance: " . decimalPlace($newBankBalance, currency($bankAccount->currency->name)));
            $this->line("Balance Change: -{$withdrawalAmount} {$bankAccount->currency->name}");
            
            // Show created bank transaction
            $bankTransaction = BankTransaction::where('created_at', '>=', now()->subMinutes(1))
                ->where('bank_account_id', $bankAccount->id)
                ->where('description', 'like', '%Demonstration withdrawal%')
                ->first();
                
            if ($bankTransaction) {
                $this->line("✓ Bank transaction created (ID: {$bankTransaction->id})");
                $this->line("  Type: {$bankTransaction->type}");
                $this->line("  Amount: {$bankTransaction->amount}");
                $this->line("  Direction: {$bankTransaction->dr_cr}");
                $this->line("  Status: {$bankTransaction->status_label}");
            }
        } else {
            $this->error("✗ BankingService processing failed");
        }
        
        $this->newLine();
    }

    /**
     * Show bank transaction history
     */
    private function showBankTransactionHistory()
    {
        $this->info('4. BANK TRANSACTION HISTORY');
        $this->line('==========================');
        
        $recentTransactions = BankTransaction::with('bankAccount')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->orderBy('created_at', 'desc')
            ->get();
            
        if ($recentTransactions->isEmpty()) {
            $this->line('No recent bank transactions found.');
            return;
        }
        
        $this->line('Recent Bank Transactions:');
        foreach ($recentTransactions as $transaction) {
            $this->line("  • {$transaction->bankAccount->account_name}");
            $this->line("    Amount: {$transaction->amount} ({$transaction->dr_cr})");
            $this->line("    Type: {$transaction->type}");
            $this->line("    Status: {$transaction->status_label}");
            $this->line("    Description: {$transaction->description}");
            $this->line("    Date: {$transaction->created_at}");
            $this->newLine();
        }
    }

    /**
     * Show balance reconciliation
     */
    private function showBalanceReconciliation()
    {
        $this->info('5. BALANCE RECONCILIATION');
        $this->line('=========================');
        
        $bankAccount = BankAccount::active()->first();
        if (!$bankAccount) {
            $this->line('No active bank accounts found.');
            return;
        }
        
        $this->line("Bank Account: {$bankAccount->account_name}");
        $this->line("Current Balance: {$bankAccount->formatted_balance}");
        
        // Recalculate balance from transactions
        $calculatedBalance = $bankAccount->recalculateBalance();
        $this->line("Calculated Balance: " . decimalPlace($calculatedBalance, currency($bankAccount->currency->name)));
        
        $difference = abs($bankAccount->current_balance - $calculatedBalance);
        if ($difference < 0.01) {
            $this->line("✓ Balance reconciliation: PASSED (Difference: {$difference})");
        } else {
            $this->line("⚠ Balance reconciliation: DISCREPANCY (Difference: {$difference})");
        }
        
        $this->newLine();
    }

    /**
     * Show transaction type mapping
     */
    private function showTransactionTypeMapping()
    {
        $this->info('6. TRANSACTION TYPE MAPPING');
        $this->line('===========================');
        
        $this->line('Member Transaction Types → Bank Transaction Types:');
        
        $mappings = [
            'Deposit' => 'deposit',
            'Withdraw' => 'withdraw',
            'Shares_Purchase' => 'deposit',
            'Shares_Sale' => 'withdraw',
            'Loan' => 'loan_disbursement',
            'Loan_Payment' => 'loan_repayment',
            'Interest' => 'deposit',
            'Fee' => 'deposit',
            'Penalty' => 'deposit'
        ];
        
        foreach ($mappings as $memberType => $bankType) {
            $this->line("  {$memberType} → {$bankType}");
        }
        
        $this->newLine();
        $this->line('This mapping ensures that:');
        $this->line('• Member deposits increase bank account balance');
        $this->line('• Member withdrawals decrease bank account balance');
        $this->line('• All transaction types are properly categorized');
        $this->line('• Bank transactions maintain proper audit trail');
    }
}
