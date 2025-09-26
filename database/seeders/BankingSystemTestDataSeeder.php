<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Currency;
use App\Models\BankAccount;
use App\Models\SavingsProduct;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\LoanProduct;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\BankTransaction;
use Illuminate\Support\Facades\DB;

class BankingSystemTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            // Get the first tenant
            $tenant = Tenant::first();
            if (!$tenant) {
                $this->command->info('No tenant found. Skipping banking system test data seeding.');
                return;
            }

            // Get or create currencies
            $kesCurrency = Currency::firstOrCreate([
                'name' => 'KES',
                'tenant_id' => $tenant->id
            ], [
                'full_name' => 'Kenyan Shilling',
                'exchange_rate' => 1.0,
                'base_currency' => 1,
                'status' => 1
            ]);

            // Create bank accounts
            $mainBankAccount = BankAccount::firstOrCreate([
                'bank_name' => 'Equity Bank',
                'account_number' => '1234567890',
                'tenant_id' => $tenant->id
            ], [
                'opening_date' => now()->subMonths(6),
                'currency_id' => $kesCurrency->id,
                'account_name' => 'Main Operations Account',
                'opening_balance' => 1000000.00,
                'current_balance' => 1000000.00,
                'is_active' => true,
                'allow_negative_balance' => false,
                'minimum_balance' => 100000.00,
                'description' => 'Primary bank account for all operations'
            ]);

            $savingsBankAccount = BankAccount::firstOrCreate([
                'bank_name' => 'KCB Bank',
                'account_number' => '0987654321',
                'tenant_id' => $tenant->id
            ], [
                'opening_date' => now()->subMonths(3),
                'currency_id' => $kesCurrency->id,
                'account_name' => 'Members Savings Pool',
                'opening_balance' => 500000.00,
                'current_balance' => 500000.00,
                'is_active' => true,
                'allow_negative_balance' => false,
                'minimum_balance' => 50000.00,
                'description' => 'Bank account for member savings deposits'
            ]);

            DB::commit();
            
            $this->command->info('Banking system test data created successfully!');

        } catch (\Exception $e) {
            DB::rollback();
            $this->command->error('Error creating test data: ' . $e->getMessage());
        }
    }
}
