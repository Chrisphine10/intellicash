<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearTransactionData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:clear-transaction-data {--confirm : Skip confirmation prompt} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all transaction data while preserving user logins and system data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $confirmed = $this->option('confirm');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No data will be deleted');
            $this->showDataSummary();
            return 0;
        }

        if (!$confirmed) {
            $this->error('⚠️  WARNING: This will permanently delete all transaction data!');
            $this->warn('This includes:');
            $this->line('  • All member transactions');
            $this->line('  • All bank transactions');
            $this->line('  • All VSLA transactions');
            $this->line('  • All loans and loan payments');
            $this->line('  • All savings accounts');
            $this->line('  • All bank accounts');
            $this->line('  • All deposit/withdraw requests');
            $this->line('  • All funds transfer requests');
            $this->line('  • All expense records');
            $this->line('  • All interest postings');
            
            $this->info('');
            $this->info('✅ KEPT:');
            $this->line('  • User accounts and logins');
            $this->line('  • Member profiles');
            $this->line('  • System settings');
            $this->line('  • Tenants and branches');
            $this->line('  • Currencies and basic configurations');
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('🧹 Starting transaction data cleanup...');
        
        try {
            DB::beginTransaction();
            
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            // Clear transaction-related data in order
            $this->clearTransactionData();
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            DB::commit();
            
            $this->info('✅ Transaction data cleared successfully!');
            $this->info('🔄 Run "php artisan cache:clear" to clear application caches.');
            
        } catch (\Exception $e) {
            try {
                DB::rollback();
            } catch (\Exception $rollbackException) {
                // Transaction might already be committed, ignore rollback error
            }
            $this->error('❌ Error occurred: ' . $e->getMessage());
            $this->error('All changes have been rolled back.');
            return 1;
        }

        return 0;
    }

    /**
     * Show summary of data that would be affected
     */
    private function showDataSummary()
    {
        $this->info('📊 Data Summary:');
        
        $tables = [
            'transactions' => 'Member Transactions',
            'bank_transactions' => 'Bank Transactions', 
            'vsla_transactions' => 'VSLA Transactions',
            'loan_payments' => 'Loan Payments',
            'loan_repayments' => 'Loan Repayments',
            'loans' => 'Loans',
            'savings_accounts' => 'Savings Accounts',
            'bank_accounts' => 'Bank Accounts',
            'deposit_requests' => 'Deposit Requests',
            'withdraw_requests' => 'Withdraw Requests',
            'funds_transfer_requests' => 'Funds Transfer Requests',
            'expenses' => 'Expenses',
            'interest_posting' => 'Interest Postings',
            'vsla_meetings' => 'VSLA Meetings',
            'vsla_meeting_attendance' => 'VSLA Meeting Attendance',
        ];

        foreach ($tables as $table => $description) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  {$description}: {$count} records");
            }
        }
    }

    /**
     * Clear all transaction-related data
     */
    private function clearTransactionData()
    {
        // 1. Clear transaction-related tables (in dependency order)
        $transactionTables = [
            'vsla_transactions',
            'loan_payments', 
            'loan_repayments',
            'transactions',
            'bank_transactions',
            'interest_posting',
            'deposit_requests',
            'withdraw_requests',
            'funds_transfer_requests',
            'expenses',
        ];

        foreach ($transactionTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                if ($count > 0) {
                    DB::table($table)->truncate();
                    $this->line("✅ Cleared {$count} records from {$table}");
                } else {
                    $this->line("ℹ️  {$table} is already empty");
                }
            }
        }

        // 2. Clear account-related tables
        $accountTables = [
            'savings_accounts',
            'bank_accounts',
        ];

        foreach ($accountTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                if ($count > 0) {
                    DB::table($table)->truncate();
                    $this->line("✅ Cleared {$count} records from {$table}");
                } else {
                    $this->line("ℹ️  {$table} is already empty");
                }
            }
        }

        // 3. Clear loan-related tables
        $loanTables = [
            'loan_collaterals',
            'guarantors', 
            'loans',
        ];

        foreach ($loanTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                if ($count > 0) {
                    DB::table($table)->truncate();
                    $this->line("✅ Cleared {$count} records from {$table}");
                } else {
                    $this->line("ℹ️  {$table} is already empty");
                }
            }
        }

        // 4. Clear VSLA-related tables
        $vslaTables = [
            'vsla_meeting_attendance',
            'vsla_meetings',
            'vsla_role_assignments',
        ];

        foreach ($vslaTables as $table) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                if ($count > 0) {
                    DB::table($table)->truncate();
                    $this->line("✅ Cleared {$count} records from {$table}");
                } else {
                    $this->line("ℹ️  {$table} is already empty");
                }
            }
        }

        // 5. Reset auto-increment counters
        $this->resetAutoIncrement([
            'transactions',
            'bank_transactions',
            'vsla_transactions',
            'loans',
            'loan_payments',
            'loan_repayments',
            'savings_accounts',
            'bank_accounts',
            'deposit_requests',
            'withdraw_requests',
            'funds_transfer_requests',
            'expenses',
            'interest_posting',
            'vsla_meetings',
            'vsla_meeting_attendance',
            'loan_collaterals',
            'guarantors',
            'vsla_role_assignments',
        ]);

        // 6. Clear audit trails related to transactions
        if (Schema::hasTable('audit_trails')) {
            // Check if table_name column exists
            $columns = Schema::getColumnListing('audit_trails');
            
            if (in_array('table_name', $columns)) {
                $auditCount = DB::table('audit_trails')
                    ->whereIn('table_name', [
                        'transactions',
                        'bank_transactions', 
                        'vsla_transactions',
                        'loans',
                        'savings_accounts',
                        'bank_accounts'
                    ])
                    ->count();
                    
                if ($auditCount > 0) {
                    DB::table('audit_trails')
                        ->whereIn('table_name', [
                            'transactions',
                            'bank_transactions',
                            'vsla_transactions', 
                            'loans',
                            'savings_accounts',
                            'bank_accounts'
                        ])
                        ->delete();
                    $this->line("✅ Cleared {$auditCount} related audit trail records");
                }
            } else {
                $this->line("ℹ️  Audit trails table structure different, skipping audit cleanup");
            }
        }

        // 7. Clear notifications related to transactions
        if (Schema::hasTable('notifications')) {
            $notificationCount = DB::table('notifications')
                ->where('type', 'like', '%Transaction%')
                ->orWhere('type', 'like', '%Loan%')
                ->orWhere('type', 'like', '%Deposit%')
                ->orWhere('type', 'like', '%Withdraw%')
                ->count();
                
            if ($notificationCount > 0) {
                DB::table('notifications')
                    ->where('type', 'like', '%Transaction%')
                    ->orWhere('type', 'like', '%Loan%')
                    ->orWhere('type', 'like', '%Deposit%')
                    ->orWhere('type', 'like', '%Withdraw%')
                    ->delete();
                $this->line("✅ Cleared {$notificationCount} related notification records");
            }
        }
    }

    /**
     * Reset auto-increment counters
     */
    private function resetAutoIncrement(array $tables)
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = 1");
                $this->line("🔄 Reset auto-increment for {$table}");
            }
        }
    }
}
