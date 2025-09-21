<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\BankAccount;
use App\Services\BankBalanceService;

class UpdateBankAccountSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bank:update-system {--test : Run in test mode without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update bank account system with performance improvements and data integrity fixes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testMode = $this->option('test');
        
        if ($testMode) {
            $this->info('Running in TEST mode - no changes will be made');
        }

        $this->info('Starting bank account system update...');

        try {
            // Step 1: Run migrations
            $this->info('Step 1: Running database migrations...');
            if (!$testMode) {
                $this->call('migrate', ['--force' => true]);
            }
            $this->info('✓ Migrations completed');

            // Step 2: Check for balance discrepancies
            $this->info('Step 2: Checking for balance discrepancies...');
            $discrepancies = $this->checkBalanceDiscrepancies();
            
            if (empty($discrepancies)) {
                $this->info('✓ No balance discrepancies found');
            } else {
                $this->warn('Found ' . count($discrepancies) . ' balance discrepancies:');
                foreach ($discrepancies as $discrepancy) {
                    $this->line("  - Account {$discrepancy['account_name']}: Cached={$discrepancy['cached_balance']}, Calculated={$discrepancy['calculated_balance']}, Diff={$discrepancy['difference']}");
                }
            }

            // Step 3: Recalculate balances
            $this->info('Step 3: Recalculating all bank account balances...');
            if (!$testMode) {
                $balanceService = new BankBalanceService();
                $results = $balanceService->recalculateAllBalances();
                
                $this->info('✓ Recalculated ' . count($results) . ' bank account balances');
                
                foreach ($results as $result) {
                    if (abs($result['difference']) > 0.01) {
                        $this->line("  - Account {$result['account_name']}: {$result['old_balance']} → {$result['new_balance']} (diff: {$result['difference']})");
                    }
                }
            } else {
                $this->info('✓ Would recalculate all balances (test mode)');
            }

            // Step 4: Validate data integrity
            $this->info('Step 4: Validating data integrity...');
            $integrityIssues = $this->validateDataIntegrity();
            
            if (empty($integrityIssues)) {
                $this->info('✓ No data integrity issues found');
            } else {
                $this->warn('Found ' . count($integrityIssues) . ' data integrity issues:');
                foreach ($integrityIssues as $issue) {
                    $this->line("  - {$issue}");
                }
            }

            // Step 5: Performance test
            $this->info('Step 5: Testing performance improvements...');
            $performanceResults = $this->testPerformance();
            $this->info("✓ Balance calculation performance: {$performanceResults['old_method']}ms → {$performanceResults['new_method']}ms");

            // Step 6: Summary
            $this->info('Step 6: System update summary...');
            $summary = $this->getSystemSummary();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Bank Accounts', $summary['total_accounts']],
                    ['Active Accounts', $summary['active_accounts']],
                    ['Total Transactions', $summary['total_transactions']],
                    ['Total Balance', $summary['total_balance']],
                    ['Accounts with Issues', $summary['accounts_with_issues']],
                ]
            );

            $this->info('✓ Bank account system update completed successfully!');
            
            if ($testMode) {
                $this->info('Run without --test flag to apply changes');
            }

        } catch (\Exception $e) {
            $this->error('Update failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }

    private function checkBalanceDiscrepancies()
    {
        $discrepancies = [];
        $bankAccounts = BankAccount::all();
        
        foreach ($bankAccounts as $account) {
            $cachedBalance = $account->current_balance;
            $calculatedBalance = $this->calculateBalance($account);
            
            if (abs($cachedBalance - $calculatedBalance) > 0.01) {
                $discrepancies[] = [
                    'account_name' => $account->account_name,
                    'cached_balance' => $cachedBalance,
                    'calculated_balance' => $calculatedBalance,
                    'difference' => $calculatedBalance - $cachedBalance
                ];
            }
        }

        return $discrepancies;
    }

    private function calculateBalance($account)
    {
        $credits = DB::table('bank_transactions')
            ->where('bank_account_id', $account->id)
            ->where('dr_cr', 'cr')
            ->where('status', 1)
            ->sum('amount');

        $debits = DB::table('bank_transactions')
            ->where('bank_account_id', $account->id)
            ->where('dr_cr', 'dr')
            ->where('status', 1)
            ->sum('amount');

        return $credits - $debits;
    }

    private function validateDataIntegrity()
    {
        $issues = [];

        // Check for accounts with negative balances that shouldn't allow it
        $negativeBalanceIssues = DB::table('bank_accounts')
            ->where('allow_negative_balance', 0)
            ->whereRaw('current_balance < minimum_balance')
            ->count();

        if ($negativeBalanceIssues > 0) {
            $issues[] = "{$negativeBalanceIssues} accounts have negative balances but don't allow them";
        }

        // Check for transactions with invalid dates
        $invalidDateIssues = DB::table('bank_transactions')
            ->join('bank_accounts', 'bank_accounts.id', '=', 'bank_transactions.bank_account_id')
            ->whereRaw('bank_transactions.trans_date < bank_accounts.opening_date')
            ->count();

        if ($invalidDateIssues > 0) {
            $issues[] = "{$invalidDateIssues} transactions have dates before account opening";
        }

        // Check for orphaned transactions
        $orphanedTransactions = DB::table('bank_transactions')
            ->leftJoin('bank_accounts', 'bank_accounts.id', '=', 'bank_transactions.bank_account_id')
            ->whereNull('bank_accounts.id')
            ->count();

        if ($orphanedTransactions > 0) {
            $issues[] = "{$orphanedTransactions} transactions reference non-existent accounts";
        }

        return $issues;
    }

    private function testPerformance()
    {
        // Test old method (raw SQL)
        $start = microtime(true);
        $oldMethod = $this->calculateBalanceOldMethod();
        $oldTime = round((microtime(true) - $start) * 1000, 2);

        // Test new method (optimized)
        $start = microtime(true);
        $newMethod = $this->calculateBalanceNewMethod();
        $newTime = round((microtime(true) - $start) * 1000, 2);

        return [
            'old_method' => $oldTime,
            'new_method' => $newTime
        ];
    }

    private function calculateBalanceOldMethod()
    {
        // Simulate old method with multiple queries
        $accounts = DB::table('bank_accounts')->pluck('id');
        $total = 0;
        
        foreach ($accounts as $accountId) {
            $credits = DB::select("SELECT IFNULL(SUM(amount), 0) as total FROM bank_transactions WHERE bank_account_id = {$accountId} AND dr_cr = 'cr' AND status = 1");
            $debits = DB::select("SELECT IFNULL(SUM(amount), 0) as total FROM bank_transactions WHERE bank_account_id = {$accountId} AND dr_cr = 'dr' AND status = 1");
            $total += $credits[0]->total - $debits[0]->total;
        }
        
        return $total;
    }

    private function calculateBalanceNewMethod()
    {
        // Simulate new method with single optimized query
        $result = DB::table('bank_transactions')
            ->where('status', 1)
            ->selectRaw('
                SUM(CASE WHEN dr_cr = "cr" THEN amount ELSE 0 END) as total_credits,
                SUM(CASE WHEN dr_cr = "dr" THEN amount ELSE 0 END) as total_debits
            ')
            ->first();

        return ($result->total_credits ?? 0) - ($result->total_debits ?? 0);
    }

    private function getSystemSummary()
    {
        $totalAccounts = BankAccount::count();
        $activeAccounts = BankAccount::where('is_active', true)->count();
        $totalTransactions = DB::table('bank_transactions')->count();
        $totalBalance = BankAccount::sum('current_balance');
        
        $accountsWithIssues = DB::table('bank_accounts')
            ->where(function($query) {
                $query->where('allow_negative_balance', 0)
                      ->whereRaw('current_balance < minimum_balance');
            })
            ->orWhere('is_active', false)
            ->count();

        return [
            'total_accounts' => $totalAccounts,
            'active_accounts' => $activeAccounts,
            'total_transactions' => $totalTransactions,
            'total_balance' => number_format($totalBalance, 2),
            'accounts_with_issues' => $accountsWithIssues
        ];
    }
}
