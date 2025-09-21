<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Initialize current_balance for existing bank accounts
        DB::statement('
            UPDATE bank_accounts 
            SET current_balance = opening_balance,
                last_balance_update = NOW(),
                is_active = 1,
                allow_negative_balance = 0,
                minimum_balance = 0
            WHERE current_balance IS NULL OR current_balance = 0
        ');

        // Recalculate balances from transactions for accounts that have transactions
        $bankAccounts = DB::table('bank_accounts')->get();
        
        foreach ($bankAccounts as $account) {
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

            $calculatedBalance = $credits - $debits;

            // Update with calculated balance if there are transactions
            if ($credits > 0 || $debits > 0) {
                DB::table('bank_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'current_balance' => $calculatedBalance,
                        'last_balance_update' => NOW()
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset current_balance to opening_balance
        DB::statement('
            UPDATE bank_accounts 
            SET current_balance = opening_balance,
                last_balance_update = NULL
        ');
    }
};
