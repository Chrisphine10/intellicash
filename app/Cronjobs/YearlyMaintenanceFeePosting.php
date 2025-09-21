<?php

namespace App\Cronjobs;

use App\Models\SavingsProduct;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YearlyMaintenanceFeePosting {

    public function __invoke() {
        @ini_set('max_execution_time', 0);
        @set_time_limit(0);

        $year  = date('Y');
        $month = date('m');

        $accountType = SavingsProduct::withoutGlobalScopes()
            ->where('maintenance_fee', '>', 0)
            ->where('maintenance_fee_posting_period', $month)
            ->first();

        if (! $accountType) {
            return;
        }

        // Process accounts in batches of 50
        $accountsProcessed = 0;
        do {
            // Fetch 50 accounts that have not been deducted in the current year
            $accounts = $accountType->accounts()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($year) {
                    $query->whereNull('last_deducted_year')
                        ->orWhere('last_deducted_year', '<', $year);
                })
                ->limit(50)
                ->get();

            if ($accounts->isEmpty()) {
                break; // Stop if no more accounts to process
            }

            DB::beginTransaction();
            try {
                foreach ($accounts as $account) {
                    // Create Transaction
                    $transaction = new Transaction();
                    $transaction->trans_date = now();
                    $transaction->member_id = $account->member_id;
                    $transaction->savings_account_id = $account->id;
                    $transaction->amount = $accountType->maintenance_fee;
                    $transaction->dr_cr = 'dr';
                    $transaction->type = 'Account_Maintenance_Fee';
                    $transaction->method = 'Automatic';
                    $transaction->status = 2;
                    $transaction->description = _lang('Account Maintenance Fee');
                    $transaction->tenant_id = $account->tenant_id;
                    $transaction->saveQuietly();

                    // Update the last deducted year
                    $account->update(['last_deducted_year' => $year]);
                    $accountsProcessed++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error("Maintenance fee deduction failed: " . $e->getMessage());
            }
        } while ($accounts->count() > 0); // Continue looping until no more accounts to process

        Log::info("$accountsProcessed accounts processed for maintenance fee deduction.");
    }
}
