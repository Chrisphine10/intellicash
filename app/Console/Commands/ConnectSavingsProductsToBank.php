<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SavingsProduct;
use App\Models\BankAccount;

class ConnectSavingsProductsToBank extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connect:savings-products-to-bank {--bank-id=} {--all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect savings products to bank accounts for proper transaction flow';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('=== CONNECTING SAVINGS PRODUCTS TO BANK ACCOUNTS ===');
        $this->newLine();

        // Get available bank accounts
        $bankAccounts = BankAccount::active()->get();
        
        if ($bankAccounts->isEmpty()) {
            $this->error('No active bank accounts found!');
            return 1;
        }

        $this->info('Available Bank Accounts:');
        foreach ($bankAccounts as $bank) {
            $this->line("  {$bank->id}. {$bank->account_name} ({$bank->bank_name}) - {$bank->formatted_balance}");
        }
        $this->newLine();

        // Get savings products without bank connections
        $savingsProducts = SavingsProduct::whereNull('bank_account_id')->get();
        
        if ($savingsProducts->isEmpty()) {
            $this->info('All savings products are already connected to bank accounts!');
            return 0;
        }

        $this->info('Savings Products without bank connections:');
        foreach ($savingsProducts as $product) {
            $this->line("  {$product->id}. {$product->name}");
        }
        $this->newLine();

        if ($this->option('all')) {
            // Connect all products to the first bank account
            $bankAccount = $bankAccounts->first();
            $this->connectAllProducts($savingsProducts, $bankAccount);
        } elseif ($this->option('bank-id')) {
            // Connect all products to specified bank account
            $bankAccount = BankAccount::find($this->option('bank-id'));
            if (!$bankAccount) {
                $this->error('Bank account not found!');
                return 1;
            }
            $this->connectAllProducts($savingsProducts, $bankAccount);
        } else {
            // Interactive mode
            $this->interactiveConnection($savingsProducts, $bankAccounts);
        }

        $this->newLine();
        $this->info('✅ Connection process completed!');
        
        // Run the test to verify connections
        $this->newLine();
        $this->info('Running verification test...');
        $this->call('test:member-bank-connection');

        return 0;
    }

    /**
     * Connect all products to a single bank account
     */
    private function connectAllProducts($savingsProducts, $bankAccount)
    {
        $this->info("Connecting all products to: {$bankAccount->account_name}");
        
        foreach ($savingsProducts as $product) {
            $product->bank_account_id = $bankAccount->id;
            $product->save();
            $this->line("  ✓ {$product->name} → {$bankAccount->account_name}");
        }
    }

    /**
     * Interactive connection mode
     */
    private function interactiveConnection($savingsProducts, $bankAccounts)
    {
        foreach ($savingsProducts as $product) {
            $this->info("Connecting: {$product->name}");
            
            $bankId = $this->choice(
                'Select bank account:',
                $bankAccounts->pluck('account_name', 'id')->toArray()
            );
            
            $bankAccount = $bankAccounts->find($bankId);
            $product->bank_account_id = $bankAccount->id;
            $product->save();
            
            $this->line("  ✓ Connected to: {$bankAccount->account_name}");
            $this->newLine();
        }
    }
}
