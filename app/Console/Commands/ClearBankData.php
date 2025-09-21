<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearBankData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:clear-bank-data {--confirm : Skip confirmation prompt} {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all bank transactions and bank accounts data';

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
            $this->error('⚠️  WARNING: This will permanently delete all bank data!');
            $this->warn('This includes:');
            $this->line('  • All bank transactions');
            $this->line('  • All bank accounts');
            $this->line('  • All related audit trails');
            $this->line('  • All related notifications');
            
            $this->info('');
            $this->info('✅ KEPT:');
            $this->line('  • User accounts and logins');
            $this->line('  • Member profiles');
            $this->line('  • System settings');
            $this->line('  • Savings accounts');
            $this->line('  • VSLA data');
            $this->line('  • Loan products');
            $this->line('  • Expense categories');
            
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('🧹 Starting bank data cleanup...');
        
        try {
            DB::beginTransaction();
            
            // Disable foreign key checks temporarily
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            
            // Clear bank data in order
            $this->clearBankData();
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            
            DB::commit();
            
            $this->info('✅ Bank data cleared successfully!');
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
        $this->info('📊 Bank Data Summary:');
        
        $tables = [
            'bank_transactions' => 'Bank Transactions',
            'bank_accounts' => 'Bank Accounts',
            'transactions' => 'Member Transactions (with bank_account_id)',
            'vsla_transactions' => 'VSLA Transactions (with bank_account_id)',
        ];

        foreach ($tables as $table => $description) {
            if (Schema::hasTable($table)) {
                $count = DB::table($table)->count();
                $this->line("  {$description}: {$count} records");
            }
        }
    }

    /**
     * Clear all bank-related data
     */
    private function clearBankData()
    {
        // 1. Clear bank transactions first (foreign key dependency)
        if (Schema::hasTable('bank_transactions')) {
            $count = DB::table('bank_transactions')->count();
            if ($count > 0) {
                DB::table('bank_transactions')->truncate();
                $this->line("✅ Cleared {$count} records from bank_transactions");
            } else {
                $this->line("ℹ️  bank_transactions is already empty");
            }
        }

        // 2. Clear bank accounts
        if (Schema::hasTable('bank_accounts')) {
            $count = DB::table('bank_accounts')->count();
            if ($count > 0) {
                DB::table('bank_accounts')->truncate();
                $this->line("✅ Cleared {$count} records from bank_accounts");
            } else {
                $this->line("ℹ️  bank_accounts is already empty");
            }
        }

        // 3. Clear bank_account_id references in other tables
        $tablesWithBankAccountId = [
            'transactions' => 'Member Transactions',
            'vsla_transactions' => 'VSLA Transactions',
        ];

        foreach ($tablesWithBankAccountId as $table => $description) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'bank_account_id')) {
                $count = DB::table($table)->whereNotNull('bank_account_id')->count();
                if ($count > 0) {
                    DB::table($table)->whereNotNull('bank_account_id')->update(['bank_account_id' => null]);
                    $this->line("✅ Cleared bank_account_id references from {$count} {$description}");
                } else {
                    $this->line("ℹ️  No bank_account_id references in {$table}");
                }
            }
        }

        // 4. Reset auto-increment counters
        $this->resetAutoIncrement([
            'bank_transactions',
            'bank_accounts',
        ]);

        // 5. Clear audit trails related to bank data
        if (Schema::hasTable('audit_trails')) {
            $columns = Schema::getColumnListing('audit_trails');
            
            if (in_array('table_name', $columns)) {
                $auditCount = DB::table('audit_trails')
                    ->whereIn('table_name', [
                        'bank_transactions',
                        'bank_accounts'
                    ])
                    ->count();
                    
                if ($auditCount > 0) {
                    DB::table('audit_trails')
                        ->whereIn('table_name', [
                            'bank_transactions',
                            'bank_accounts'
                        ])
                        ->delete();
                    $this->line("✅ Cleared {$auditCount} related audit trail records");
                }
            } else {
                $this->line("ℹ️  Audit trails table structure different, skipping audit cleanup");
            }
        }

        // 6. Clear notifications related to bank transactions
        if (Schema::hasTable('notifications')) {
            $notificationCount = DB::table('notifications')
                ->where('type', 'like', '%Bank%')
                ->orWhere('type', 'like', '%Transaction%')
                ->count();
                
            if ($notificationCount > 0) {
                DB::table('notifications')
                    ->where('type', 'like', '%Bank%')
                    ->orWhere('type', 'like', '%Transaction%')
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
