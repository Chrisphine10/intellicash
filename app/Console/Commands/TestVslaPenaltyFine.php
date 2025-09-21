<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\VslaTransaction;
use App\Models\Tenant;
use App\Models\Member;
use App\Models\SavingsAccount;

class TestVslaPenaltyFine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vsla:test-penalty-fine {--tenant-id=1} {--member-id=1} {--amount=100} {--test : Test mode only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test VSLA penalty fine functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->option('tenant-id');
        $memberId = $this->option('member-id');
        $amount = $this->option('amount');
        $testMode = $this->option('test');
        
        if ($testMode) {
            $this->info('Running in TEST mode - no transactions will be created');
        }

        $this->info('Testing VSLA Penalty Fine functionality...');
        $this->info("Tenant ID: {$tenantId}");
        $this->info("Member ID: {$memberId}");
        $this->info("Amount: {$amount}");

        try {
            // Get tenant
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                $this->error("Tenant with ID {$tenantId} not found");
                return 1;
            }

            // Get member
            $member = Member::where('tenant_id', $tenantId)->where('id', $memberId)->first();
            if (!$member) {
                $this->error("Member with ID {$memberId} not found for tenant {$tenantId}");
                return 1;
            }

            $this->info("Member: {$member->first_name} {$member->last_name}");

            // Check VSLA Share Account
            $shareAccount = SavingsAccount::where('tenant_id', $tenantId)
                ->where('member_id', $memberId)
                ->whereHas('savings_type', function($q) {
                    $q->where('name', 'VSLA Shares Account');
                })
                ->first();

            if (!$shareAccount) {
                $this->error('VSLA Shares Account not found for member');
                $this->info('Available accounts for this member:');
                $accounts = SavingsAccount::where('tenant_id', $tenantId)
                    ->where('member_id', $memberId)
                    ->with('savings_type')
                    ->get();
                
                foreach ($accounts as $account) {
                    $this->line("  - {$account->savings_type->name} (ID: {$account->id})");
                }
                return 1;
            }

            $this->info("VSLA Share Account found: ID {$shareAccount->id}");

            // Check current balance
            $currentBalance = get_account_balance($shareAccount->id, $memberId);
            $this->info("Current Share Account Balance: " . number_format($currentBalance, 2));

            if ($currentBalance < $amount) {
                $this->warn("Warning: Insufficient balance for penalty fine");
                $this->warn("Required: {$amount}, Available: {$currentBalance}");
                if (!$testMode) {
                    return 1;
                }
            }

            // Check VSLA Social Fund Account
            $socialFundAccount = \App\Models\BankAccount::where('tenant_id', $tenantId)
                ->where('account_name', 'VSLA Social Fund Account')
                ->first();

            if (!$socialFundAccount) {
                $this->error('VSLA Social Fund Account not found');
                $this->info('Available VSLA bank accounts:');
                $bankAccounts = \App\Models\BankAccount::where('tenant_id', $tenantId)
                    ->where('bank_name', 'VSLA Internal')
                    ->get();
                
                foreach ($bankAccounts as $account) {
                    $this->line("  - {$account->account_name} (ID: {$account->id})");
                }
                return 1;
            }

            $this->info("VSLA Social Fund Account found: ID {$socialFundAccount->id}");

            if ($testMode) {
                $this->info('✅ All checks passed - penalty fine would be processed successfully');
                $this->info('Transaction flow:');
                $this->line("  1. Debit {$amount} from member's VSLA Share Account");
                $this->line("  2. Credit {$amount} to VSLA Social Fund Account");
                $this->line("  3. Create VSLA transaction record");
                return 0;
            }

            // Create test penalty fine transaction
            $vslaTransaction = VslaTransaction::create([
                'tenant_id' => $tenantId,
                'meeting_id' => 1, // Default meeting
                'member_id' => $memberId,
                'transaction_type' => 'penalty_fine',
                'amount' => $amount,
                'description' => 'Test penalty fine - ' . now(),
                'status' => 'pending',
                'created_user_id' => 1,
            ]);

            $this->info("Created VSLA transaction: ID {$vslaTransaction->id}");

            // Process the transaction
            $controller = new \App\Http\Controllers\VslaTransactionsController();
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('processPenaltyFine');
            $method->setAccessible(true);
            
            $method->invoke($controller, $vslaTransaction, $tenant);

            $this->info('✅ Penalty fine processed successfully!');
            $this->info("VSLA Transaction ID: {$vslaTransaction->id}");

            // Show updated balance
            $newBalance = get_account_balance($shareAccount->id, $memberId);
            $this->info("Updated Share Account Balance: " . number_format($newBalance, 2));

        } catch (\Exception $e) {
            $this->error('Test failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
