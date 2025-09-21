<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Temporarily drop the constraint to allow balance initialization
        try {
            DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT chk_bank_account_balance');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        // Initialize balances for existing accounts
        $bankAccounts = DB::table('bank_accounts')->get();
        
        foreach ($bankAccounts as $account) {
            // Calculate current balance from transactions
            $calculatedBalance = DB::table('bank_transactions')
                ->where('bank_account_id', $account->id)
                ->where('status', 1) // Approved transactions only
                ->selectRaw('
                    COALESCE(SUM(
                        CASE 
                            WHEN dr_cr = "cr" THEN amount 
                            WHEN dr_cr = "dr" THEN -amount 
                            ELSE 0 
                        END
                    ), 0) as balance
                ')
                ->value('balance');

            // Update the account with calculated balance
            DB::table('bank_accounts')
                ->where('id', $account->id)
                ->update([
                    'current_balance' => $calculatedBalance,
                    'last_balance_update' => now(),
                    'is_active' => true,
                    'allow_negative_balance' => $calculatedBalance < 0 ? true : false,
                    'minimum_balance' => 0,
                ]);
        }

        // Re-add the constraint
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_balance 
            CHECK (current_balance >= CASE WHEN allow_negative_balance = 0 THEN minimum_balance ELSE -999999999.99 END)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the constraint
        try {
            DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT chk_bank_account_balance');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }
    }
};
