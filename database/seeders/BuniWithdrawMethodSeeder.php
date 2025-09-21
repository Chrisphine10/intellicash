<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuniWithdrawMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get KES currency ID
        $kesCurrency = DB::table('currency')->where('name', 'KES')->first();
        
        if (!$kesCurrency) {
            // Create KES currency if it doesn't exist
            $kesCurrencyId = DB::table('currency')->insertGetId([
                'full_name' => 'Kenyan Shilling',
                'name' => 'KES',
                'exchange_rate' => 1.000000,
                'base_currency' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $kesCurrencyId = $kesCurrency->id;
        }

        // Get all tenants
        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            // Check if Buni withdraw method already exists for this tenant
            $existingMethod = DB::table('withdraw_methods')
                ->where('name', 'KCB Buni Mobile Money')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$existingMethod) {
                DB::table('withdraw_methods')->insert([
                    'name' => 'KCB Buni Mobile Money',
                    'image' => 'buni.png',
                    'currency_id' => $kesCurrencyId,
                    'descriptions' => 'Withdraw money directly to your mobile phone number via KCB Buni. Funds will be sent to your registered mobile number instantly.',
                    'status' => 1,
                    'requirements' => json_encode([
                        'mobile_number' => 'Your mobile phone number (e.g., 2547XXXXXXXX)',
                        'amount' => 'Amount to withdraw (minimum 10 KES)',
                        'description' => 'Reason for withdrawal (optional)'
                    ]),
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
