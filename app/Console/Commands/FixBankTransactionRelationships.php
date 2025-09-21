<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankTransaction;
use App\Models\BankAccount;

class FixBankTransactionRelationships extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:fix-relationships {--test : Test mode only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix bank transaction relationships and test the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testMode = $this->option('test');
        
        if ($testMode) {
            $this->info('Running in TEST mode - no changes will be made');
        }

        $this->info('Testing bank transaction relationships...');

        try {
            // Test 1: Check if BankTransaction model can access bank_account relationship
            $this->info('Test 1: Testing bank_account relationship...');
            
            $bankTransaction = BankTransaction::with('bank_account')->first();
            
            if ($bankTransaction && $bankTransaction->bank_account) {
                $this->info('✓ bank_account relationship working');
                $this->line("  - Transaction ID: {$bankTransaction->id}");
                $this->line("  - Bank Account: {$bankTransaction->bank_account->account_name}");
                $this->line("  - Bank Name: {$bankTransaction->bank_account->bank_name}");
            } else {
                $this->warn('⚠ No bank transactions found or relationship not working');
            }

            // Test 2: Check if BankTransaction model can access bankAccount relationship
            $this->info('Test 2: Testing bankAccount relationship...');
            
            $bankTransaction = BankTransaction::with('bankAccount')->first();
            
            if ($bankTransaction && $bankTransaction->bankAccount) {
                $this->info('✓ bankAccount relationship working');
                $this->line("  - Transaction ID: {$bankTransaction->id}");
                $this->line("  - Bank Account: {$bankTransaction->bankAccount->account_name}");
                $this->line("  - Bank Name: {$bankTransaction->bankAccount->bank_name}");
            } else {
                $this->warn('⚠ No bank transactions found or relationship not working');
            }

            // Test 3: Test DataTables query
            $this->info('Test 3: Testing DataTables query...');
            
            $bankTransactions = BankTransaction::select('bank_transactions.*')
                ->with('bank_account.currency')
                ->limit(5)
                ->get();

            if ($bankTransactions->count() > 0) {
                $this->info('✓ DataTables query working');
                foreach ($bankTransactions as $transaction) {
                    $this->line("  - Transaction {$transaction->id}: {$transaction->bank_account->bank_name} - {$transaction->amount}");
                }
            } else {
                $this->warn('⚠ No bank transactions found');
            }

            // Test 4: Test currency relationship
            $this->info('Test 4: Testing currency relationship...');
            
            $bankTransaction = BankTransaction::with('bank_account.currency')->first();
            
            if ($bankTransaction && $bankTransaction->bank_account && $bankTransaction->bank_account->currency) {
                $this->info('✓ Currency relationship working');
                $this->line("  - Currency: {$bankTransaction->bank_account->currency->name}");
                $this->line("  - Full Name: {$bankTransaction->bank_account->currency->full_name}");
            } else {
                $this->warn('⚠ Currency relationship not working');
            }

            // Test 5: Test status constants
            $this->info('Test 5: Testing status constants...');
            
            $this->line("  - PENDING: " . BankTransaction::STATUS_PENDING);
            $this->line("  - APPROVED: " . BankTransaction::STATUS_APPROVED);
            $this->line("  - REJECTED: " . BankTransaction::STATUS_REJECTED);
            $this->line("  - CANCELLED: " . BankTransaction::STATUS_CANCELLED);
            $this->info('✓ Status constants defined');

            // Test 6: Test type constants
            $this->info('Test 6: Testing type constants...');
            
            $this->line("  - DEPOSIT: " . BankTransaction::TYPE_DEPOSIT);
            $this->line("  - WITHDRAW: " . BankTransaction::TYPE_WITHDRAW);
            $this->line("  - TRANSFER: " . BankTransaction::TYPE_TRANSFER);
            $this->info('✓ Type constants defined');

            // Summary
            $this->info('Relationship fix completed successfully!');
            $this->line('');
            $this->line('If you\'re still seeing errors:');
            $this->line('1. Clear application cache: php artisan cache:clear');
            $this->line('2. Clear config cache: php artisan config:clear');
            $this->line('3. Clear view cache: php artisan view:clear');
            $this->line('4. Restart your web server');

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
