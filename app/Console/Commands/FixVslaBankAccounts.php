<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BankAccount;
use App\Models\Tenant;

class FixVslaBankAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vsla:fix-bank-accounts {--test : Test mode only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix VSLA bank accounts to ensure they have all required fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testMode = $this->option('test');
        
        if ($testMode) {
            $this->info('Running in TEST mode - no changes will be made');
        }

        $this->info('Fixing VSLA bank accounts...');

        try {
            $fixedCount = 0;
            $totalCount = 0;

            // Get all VSLA internal accounts
            $vslaAccounts = BankAccount::where('bank_name', 'VSLA Internal')->get();
            
            $this->info("Found {$vslaAccounts->count()} VSLA bank accounts to check");

            foreach ($vslaAccounts as $account) {
                $totalCount++;
                $updates = [];
                $needsUpdate = false;

                // Check and fix is_active field
                if (is_null($account->is_active)) {
                    $updates['is_active'] = true;
                    $needsUpdate = true;
                    $this->line("  - Account '{$account->account_name}': Setting is_active = true");
                }

                // Check and fix current_balance field
                if (is_null($account->current_balance)) {
                    $updates['current_balance'] = $account->opening_balance ?? 0;
                    $needsUpdate = true;
                    $this->line("  - Account '{$account->account_name}': Setting current_balance = {$account->opening_balance}");
                }

                // Check and fix minimum_balance field
                if (is_null($account->minimum_balance)) {
                    $updates['minimum_balance'] = 0;
                    $needsUpdate = true;
                    $this->line("  - Account '{$account->account_name}': Setting minimum_balance = 0");
                }

                // Check and fix allow_negative_balance field
                if (is_null($account->allow_negative_balance)) {
                    $updates['allow_negative_balance'] = false;
                    $needsUpdate = true;
                    $this->line("  - Account '{$account->account_name}': Setting allow_negative_balance = false");
                }

                // Check and fix last_balance_update field
                if (is_null($account->last_balance_update)) {
                    $updates['last_balance_update'] = now();
                    $needsUpdate = true;
                    $this->line("  - Account '{$account->account_name}': Setting last_balance_update = now()");
                }

                // Apply updates if needed
                if ($needsUpdate && !$testMode) {
                    $account->update($updates);
                    $fixedCount++;
                    $this->info("  ✓ Fixed account '{$account->account_name}'");
                } elseif ($needsUpdate && $testMode) {
                    $this->info("  ✓ Would fix account '{$account->account_name}' (test mode)");
                } else {
                    $this->line("  - Account '{$account->account_name}': No fixes needed");
                }
            }

            $this->info('');
            $this->info("Summary:");
            $this->line("  - Total accounts checked: {$totalCount}");
            
            if ($testMode) {
                $this->line("  - Accounts that would be fixed: {$fixedCount}");
                $this->info("  - Run without --test flag to apply fixes");
            } else {
                $this->line("  - Accounts fixed: {$fixedCount}");
            }

            if ($fixedCount > 0) {
                $this->info('✓ VSLA bank accounts fixed successfully!');
            } else {
                $this->info('✓ All VSLA bank accounts are already properly configured');
            }

        } catch (\Exception $e) {
            $this->error('Fix failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
