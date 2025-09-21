<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearAccountTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:clear-account-types {--confirm : Skip confirmation prompt} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all account types (savings products) and related data';

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
            $this->error('⚠️  WARNING: This will permanently delete all account types!');
            $this->warn('This includes:');
            $this->line('  • All savings products (account types)');
            $this->line('  • All savings accounts');
            $this->line('  • All related transactions');
            $this->line('  • All related audit trails');
            
            $this->info('');
            $this->info('✅ KEPT:');
            $this->line('  • User accounts and logins');
            $this->line('  • Member profiles');
            $this->line('  • System settings');
            $this->line('  • Bank accounts');
            $this->line('  • VSLA data');
            $this->line('  • Loan products');
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('🧹 Starting account types cleanup...');
        
        try {
            DB::beginTransaction();
            
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            // Clear account type related data in order
            $this->clearAccountTypeData();
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            DB::commit();
            
            $this->info('✅ Account types cleared successfully!');
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
        $this->info('📊 Account Types Data Summary:');
        
        $tables = [
            'savings_products' => 'Savings Products (Account Types)',
            'savings_accounts' => 'Savings Accounts',
            'transactions' => 'Member Transactions',
            'deposit_requests' => 'Deposit Requests',
            'withdraw_requests' => 'Withdraw Requests',
        ];

        foreach ($tables as $table => $description) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  {$description}: {$count} records");
            }
        }
    }

    /**
     * Clear all account type related data
     */
    private function clearAccountTypeData()
    {
        // 1. Clear transactions first (foreign key dependency)
        $transactionTables = [
            'transactions',
            'deposit_requests',
            'withdraw_requests',
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

        // 2. Clear savings accounts
        if (Schema::hasTable('savings_accounts')) {
            $count = DB::table('savings_accounts')->count();
            if ($count > 0) {
                DB::table('savings_accounts')->truncate();
                $this->line("✅ Cleared {$count} records from savings_accounts");
            } else {
                $this->line("ℹ️  savings_accounts is already empty");
            }
        }

        // 3. Clear savings products (account types)
        if (Schema::hasTable('savings_products')) {
            $count = DB::table('savings_products')->count();
            if ($count > 0) {
                DB::table('savings_products')->truncate();
                $this->line("✅ Cleared {$count} records from savings_products");
            } else {
                $this->line("ℹ️  savings_products is already empty");
            }
        }

        // 4. Reset auto-increment counters
        $this->resetAutoIncrement([
            'savings_products',
            'savings_accounts',
            'transactions',
            'deposit_requests',
            'withdraw_requests',
        ]);

        // 5. Clear audit trails related to account types
        if (Schema::hasTable('audit_trails')) {
            $columns = Schema::getColumnListing('audit_trails');
            
            if (in_array('table_name', $columns)) {
                $auditCount = DB::table('audit_trails')
                    ->whereIn('table_name', [
                        'savings_products',
                        'savings_accounts',
                        'transactions'
                    ])
                    ->count();
                    
                if ($auditCount > 0) {
                    DB::table('audit_trails')
                        ->whereIn('table_name', [
                            'savings_products',
                            'savings_accounts',
                            'transactions'
                        ])
                        ->delete();
                    $this->line("✅ Cleared {$auditCount} related audit trail records");
                }
            } else {
                $this->line("ℹ️  Audit trails table structure different, skipping audit cleanup");
            }
        }

        // 6. Clear notifications related to account types
        if (Schema::hasTable('notifications')) {
            $notificationCount = DB::table('notifications')
                ->where('type', 'like', '%Savings%')
                ->orWhere('type', 'like', '%Deposit%')
                ->orWhere('type', 'like', '%Withdraw%')
                ->count();
                
            if ($notificationCount > 0) {
                DB::table('notifications')
                    ->where('type', 'like', '%Savings%')
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
