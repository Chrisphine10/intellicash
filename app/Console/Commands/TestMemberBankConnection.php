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

class TestMemberBankConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:member-bank-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the connection between member transactions and bank accounts';

    private $bankingService;
    private $testResults = [];

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
        $this->info('=== MEMBER-BANK CONNECTION TEST ===');
        $this->newLine();
        
        $this->testBankAccountSetup();
        $this->testSavingsProductBankConnection();
        $this->testMemberDepositFlow();
        $this->testMemberWithdrawalFlow();
        $this->testBankTransactionDetection();
        $this->testBalanceUpdates();
        $this->testMultipleTransactions();
        
        $this->displayResults();
        
        return 0;
    }

    /**
     * Test 1: Verify bank account setup and basic properties
     */
    private function testBankAccountSetup()
    {
        $this->info('1. Testing Bank Account Setup...');
        
        try {
            // Get a sample bank account
            $bankAccount = BankAccount::active()->first();
            
            if (!$bankAccount) {
                $this->addResult('Bank Account Setup', 'FAILED', 'No active bank accounts found');
                return;
            }

            // Test basic properties
            $hasCurrency = !is_null($bankAccount->currency);
            $hasBalance = isset($bankAccount->current_balance);
            $hasOpeningDate = !is_null($bankAccount->opening_date);
            
            if ($hasCurrency && $hasBalance && $hasOpeningDate) {
                $this->addResult('Bank Account Setup', 'PASSED', 
                    "Bank Account: {$bankAccount->account_name} (Balance: {$bankAccount->formatted_balance})");
            } else {
                $this->addResult('Bank Account Setup', 'FAILED', 'Missing required bank account properties');
            }
            
        } catch (\Exception $e) {
            $this->addResult('Bank Account Setup', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 2: Verify savings products are connected to bank accounts
     */
    private function testSavingsProductBankConnection()
    {
        $this->info('2. Testing Savings Product-Bank Connection...');
        
        try {
            $savingsProducts = SavingsProduct::with('bank_account')->get();
            $connectedProducts = 0;
            $totalProducts = $savingsProducts->count();
            
            foreach ($savingsProducts as $product) {
                if ($product->bank_account_id && $product->bank_account) {
                    $connectedProducts++;
                    $this->line("   ✓ {$product->name} → {$product->bank_account->account_name}");
                } else {
                    $this->line("   ✗ {$product->name} → No bank account connected");
                }
            }
            
            $connectionRate = $totalProducts > 0 ? ($connectedProducts / $totalProducts) * 100 : 0;
            
            if ($connectionRate >= 50) {
                $this->addResult('Savings-Bank Connection', 'PASSED', 
                    "{$connectedProducts}/{$totalProducts} products connected ({$connectionRate}%)");
            } else {
                $this->addResult('Savings-Bank Connection', 'WARNING', 
                    "Only {$connectedProducts}/{$totalProducts} products connected ({$connectionRate}%)");
            }
            
        } catch (\Exception $e) {
            $this->addResult('Savings-Bank Connection', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 3: Test member deposit flow
     */
    private function testMemberDepositFlow()
    {
        $this->info('3. Testing Member Deposit Flow...');
        
        try {
            // Find a member with a savings account connected to a bank account
            $member = Member::with(['savings_accounts.savings_type.bank_account'])
                ->whereHas('savings_accounts.savings_type', function($query) {
                    $query->whereNotNull('bank_account_id');
                })
                ->first();
                
            if (!$member) {
                $this->addResult('Member Deposit Flow', 'SKIPPED', 'No members with bank-connected accounts found');
                return;
            }

            $savingsAccount = $member->savings_accounts->first();
            $bankAccount = $savingsAccount->savings_type->bank_account;
            
            $initialBankBalance = $bankAccount->current_balance;
            $depositAmount = 1000.00;
            
            // Create a test deposit transaction
            $transaction = new Transaction();
            $transaction->trans_date = now();
            $transaction->member_id = $member->id;
            $transaction->savings_account_id = $savingsAccount->id;
            $transaction->amount = $depositAmount;
            $transaction->dr_cr = 'cr';
            $transaction->type = 'Deposit';
            $transaction->method = 'Manual';
            $transaction->status = 2; // Completed
            $transaction->description = 'Test deposit transaction';
            $transaction->created_user_id = 1;
            $transaction->tenant_id = 1;
            $transaction->save();
            
            // Process through banking service
            $result = $this->bankingService->processMemberTransaction($transaction);
            
            // Refresh bank account to get updated balance
            $bankAccount->refresh();
            $newBankBalance = $bankAccount->current_balance;
            
            if ($result && $newBankBalance == ($initialBankBalance + $depositAmount)) {
                $this->addResult('Member Deposit Flow', 'PASSED', 
                    "Deposit processed: Bank balance increased from {$initialBankBalance} to {$newBankBalance}");
            } else {
                $this->addResult('Member Deposit Flow', 'FAILED', 
                    "Deposit not processed correctly. Expected: " . ($initialBankBalance + $depositAmount) . ", Got: {$newBankBalance}");
            }
            
            // Clean up test transaction
            $transaction->delete();
            
        } catch (\Exception $e) {
            $this->addResult('Member Deposit Flow', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 4: Test member withdrawal flow
     */
    private function testMemberWithdrawalFlow()
    {
        $this->info('4. Testing Member Withdrawal Flow...');
        
        try {
            // Find a member with sufficient balance
            $member = Member::with(['savings_accounts.savings_type.bank_account'])
                ->whereHas('savings_accounts.savings_type', function($query) {
                    $query->whereNotNull('bank_account_id');
                })
                ->first();
                
            if (!$member) {
                $this->addResult('Member Withdrawal Flow', 'SKIPPED', 'No members with bank-connected accounts found');
                return;
            }

            $savingsAccount = $member->savings_accounts->first();
            $bankAccount = $savingsAccount->savings_type->bank_account;
            
            // Ensure bank account has sufficient balance
            if ($bankAccount->current_balance < 500) {
                $this->addResult('Member Withdrawal Flow', 'SKIPPED', 'Insufficient bank balance for withdrawal test');
                return;
            }
            
            $initialBankBalance = $bankAccount->current_balance;
            $withdrawalAmount = 500.00;
            
            // Create a test withdrawal transaction
            $transaction = new Transaction();
            $transaction->trans_date = now();
            $transaction->member_id = $member->id;
            $transaction->savings_account_id = $savingsAccount->id;
            $transaction->amount = $withdrawalAmount;
            $transaction->dr_cr = 'dr';
            $transaction->type = 'Withdraw';
            $transaction->method = 'Manual';
            $transaction->status = 2; // Completed
            $transaction->description = 'Test withdrawal transaction';
            $transaction->created_user_id = 1;
            $transaction->tenant_id = 1;
            $transaction->save();
            
            // Process through banking service
            $result = $this->bankingService->processMemberTransaction($transaction);
            
            // Refresh bank account to get updated balance
            $bankAccount->refresh();
            $newBankBalance = $bankAccount->current_balance;
            
            if ($result && $newBankBalance == ($initialBankBalance - $withdrawalAmount)) {
                $this->addResult('Member Withdrawal Flow', 'PASSED', 
                    "Withdrawal processed: Bank balance decreased from {$initialBankBalance} to {$newBankBalance}");
            } else {
                $this->addResult('Member Withdrawal Flow', 'FAILED', 
                    "Withdrawal not processed correctly. Expected: " . ($initialBankBalance - $withdrawalAmount) . ", Got: {$newBankBalance}");
            }
            
            // Clean up test transaction
            $transaction->delete();
            
        } catch (\Exception $e) {
            $this->addResult('Member Withdrawal Flow', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 5: Test bank transaction detection
     */
    private function testBankTransactionDetection()
    {
        $this->info('5. Testing Bank Transaction Detection...');
        
        try {
            // Check if bank transactions are created for member transactions
            $recentBankTransactions = BankTransaction::where('created_at', '>=', now()->subMinutes(10))
                ->whereNotNull('description')
                ->where('description', 'like', '%Member:%')
                ->count();
                
            if ($recentBankTransactions > 0) {
                $this->addResult('Bank Transaction Detection', 'PASSED', 
                    "Found {$recentBankTransactions} recent bank transactions from member activities");
            } else {
                $this->addResult('Bank Transaction Detection', 'INFO', 
                    "No recent member-generated bank transactions found (this is normal if no recent activity)");
            }
            
            // Test transaction type mapping
            $testMappings = [
                'Deposit' => 'deposit',
                'Withdraw' => 'withdraw',
                'Shares_Purchase' => 'deposit',
                'Loan' => 'loan_disbursement',
                'Loan_Payment' => 'loan_repayment'
            ];
            
            $this->line('   Transaction Type Mappings:');
            foreach ($testMappings as $memberType => $expectedBankType) {
                $reflection = new \ReflectionClass($this->bankingService);
                $method = $reflection->getMethod('mapTransactionType');
                $method->setAccessible(true);
                $result = $method->invoke($this->bankingService, $memberType);
                
                if ($result === $expectedBankType) {
                    $this->line("   ✓ {$memberType} → {$result}");
                } else {
                    $this->line("   ✗ {$memberType} → {$result} (expected {$expectedBankType})");
                }
            }
            
        } catch (\Exception $e) {
            $this->addResult('Bank Transaction Detection', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 6: Test balance update mechanisms
     */
    private function testBalanceUpdates()
    {
        $this->info('6. Testing Balance Update Mechanisms...');
        
        try {
            $bankAccount = BankAccount::active()->first();
            
            if (!$bankAccount) {
                $this->addResult('Balance Updates', 'SKIPPED', 'No active bank accounts found');
                return;
            }
            
            // Test balance recalculation
            $calculatedBalance = $bankAccount->recalculateBalance();
            $currentBalance = $bankAccount->current_balance;
            $difference = abs($currentBalance - $calculatedBalance);
            
            if ($difference < 0.01) {
                $this->addResult('Balance Updates', 'PASSED', 
                    "Balance reconciliation successful. Current: {$currentBalance}, Calculated: {$calculatedBalance}");
            } else {
                $this->addResult('Balance Updates', 'WARNING', 
                    "Balance discrepancy found. Difference: {$difference}");
            }
            
            // Test available balance calculation
            $availableBalance = $bankAccount->available_balance;
            $expectedAvailable = $currentBalance - $bankAccount->blocked_balance;
            
            if (abs($availableBalance - $expectedAvailable) < 0.01) {
                $this->line("   ✓ Available balance calculation correct: {$availableBalance}");
            } else {
                $this->line("   ✗ Available balance calculation incorrect. Expected: {$expectedAvailable}, Got: {$availableBalance}");
            }
            
        } catch (\Exception $e) {
            $this->addResult('Balance Updates', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Test 7: Test multiple transactions and cumulative effects
     */
    private function testMultipleTransactions()
    {
        $this->info('7. Testing Multiple Transactions...');
        
        try {
            $bankAccount = BankAccount::active()->first();
            
            if (!$bankAccount) {
                $this->addResult('Multiple Transactions', 'SKIPPED', 'No active bank accounts found');
                return;
            }
            
            // Get recent bank transactions
            $recentTransactions = BankTransaction::where('bank_account_id', $bankAccount->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->where('status', 1) // Approved only
                ->get();
                
            $totalCredits = $recentTransactions->where('dr_cr', 'cr')->sum('amount');
            $totalDebits = $recentTransactions->where('dr_cr', 'dr')->sum('amount');
            $netEffect = $totalCredits - $totalDebits;
            
            $this->line("   Recent 7-day activity:");
            $this->line("   - Total Credits: {$totalCredits}");
            $this->line("   - Total Debits: {$totalDebits}");
            $this->line("   - Net Effect: {$netEffect}");
            $this->line("   - Transaction Count: {$recentTransactions->count()}");
            
            $this->addResult('Multiple Transactions', 'INFO', 
                "Recent activity: {$recentTransactions->count()} transactions, Net effect: {$netEffect}");
            
        } catch (\Exception $e) {
            $this->addResult('Multiple Transactions', 'ERROR', $e->getMessage());
        }
    }

    /**
     * Add test result
     */
    private function addResult($test, $status, $message)
    {
        $this->testResults[] = [
            'test' => $test,
            'status' => $status,
            'message' => $message
        ];
    }

    /**
     * Display test results
     */
    private function displayResults()
    {
        $this->newLine();
        $this->info('=== TEST RESULTS SUMMARY ===');
        $this->newLine();
        
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $errors = 0;
        $skipped = 0;
        
        foreach ($this->testResults as $result) {
            $status = $result['status'];
            $icon = '';
            
            switch ($status) {
                case 'PASSED':
                    $icon = '✓';
                    $passed++;
                    break;
                case 'FAILED':
                    $icon = '✗';
                    $failed++;
                    break;
                case 'WARNING':
                    $icon = '⚠';
                    $warnings++;
                    break;
                case 'ERROR':
                    $icon = '!';
                    $errors++;
                    break;
                case 'SKIPPED':
                    $icon = '-';
                    $skipped++;
                    break;
                case 'INFO':
                    $icon = 'ℹ';
                    break;
            }
            
            $this->line("{$icon} {$result['test']}: {$result['status']}");
            $this->line("   {$result['message']}");
            $this->newLine();
        }
        
        $this->info('=== SUMMARY ===');
        $this->line("✓ Passed: {$passed}");
        $this->line("✗ Failed: {$failed}");
        $this->line("⚠ Warnings: {$warnings}");
        $this->line("! Errors: {$errors}");
        $this->line("- Skipped: {$skipped}");
        
        $total = $passed + $failed + $warnings + $errors;
        $successRate = $total > 0 ? ($passed / $total) * 100 : 0;
        
        $this->newLine();
        $this->line("Success Rate: {$successRate}%");
        
        if ($successRate >= 80) {
            $this->newLine();
            $this->info('🎉 OVERALL RESULT: SYSTEM IS WORKING WELL!');
        } elseif ($successRate >= 60) {
            $this->newLine();
            $this->warn('⚠️  OVERALL RESULT: SYSTEM NEEDS ATTENTION');
        } else {
            $this->newLine();
            $this->error('❌ OVERALL RESULT: SYSTEM HAS ISSUES');
        }
    }
}
