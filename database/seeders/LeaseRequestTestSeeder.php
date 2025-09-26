<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\Currency;
use Illuminate\Database\Seeder;

class LeaseRequestTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Get the first tenant
        $tenant = \App\Models\Tenant::first();
        
        if (!$tenant) {
            $this->command->info('No tenants found. Skipping lease request test seeding.');
            return;
        }
        
        $tenantId = $tenant->id;

        // Create currency if not exists
        $currency = Currency::firstOrCreate(
            ['id' => 1],
            [
                'name' => 'USD',
                'symbol' => '$',
                'exchange_rate' => 1.00,
                'status' => 1
            ]
        );

        // Create savings product if not exists
        $savingsProduct = SavingsProduct::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => 'Regular Savings'
            ],
            [
                'account_number_prefix' => 'SAV',
                'starting_account_number' => 1000,
                'currency_id' => $currency->id,
                'status' => 1,
                'auto_create' => 0
            ]
        );

        // Create test members
        $member1 = Member::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'member_no' => 'MEM001'
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@test.com',
                'mobile' => '+1234567890',
                'status' => 1,
                'branch_id' => 1
            ]
        );

        $member2 = Member::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'member_no' => 'MEM002'
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@test.com',
                'mobile' => '+1234567891',
                'status' => 1,
                'branch_id' => 1
            ]
        );

        // Create savings accounts for test members
        SavingsAccount::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'account_number' => 'SAV1001'
            ],
            [
                'member_id' => $member1->id,
                'savings_product_id' => $savingsProduct->id,
                'status' => 1,
                'opening_balance' => 5000.00,
                'created_user_id' => 1
            ]
        );

        SavingsAccount::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'account_number' => 'SAV1002'
            ],
            [
                'member_id' => $member2->id,
                'savings_product_id' => $savingsProduct->id,
                'status' => 1,
                'opening_balance' => 3000.00,
                'created_user_id' => 1
            ]
        );

        $this->command->info('Lease request test data created successfully!');
    }
}
