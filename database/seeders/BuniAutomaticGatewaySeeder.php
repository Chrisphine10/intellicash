<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuniAutomaticGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get all tenants
        $tenants = DB::table('tenants')->get();

        foreach ($tenants as $tenant) {
            // Check if Buni automatic gateway already exists for this tenant
            $existingGateway = DB::table('automatic_gateways')
                ->where('slug', 'Buni')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (!$existingGateway) {
                DB::table('automatic_gateways')->insert([
                    'name' => 'KCB Buni',
                    'slug' => 'Buni',
                    'image' => 'buni.png',
                    'status' => 0, // Disabled by default - tenant needs to configure
                    'is_crypto' => 0,
                    'parameters' => json_encode([
                        'buni_base_url' => '',
                        'buni_client_id' => '',
                        'buni_client_secret' => '',
                        'till_number' => ''
                    ]),
                    'currency' => 'KES',
                    'supported_currencies' => json_encode(['KES' => 'KES']),
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Also add system-wide Buni gateway (for super admin)
        $systemGateway = DB::table('automatic_gateways')
            ->where('slug', 'Buni')
            ->whereNull('tenant_id')
            ->first();

        if (!$systemGateway) {
            DB::table('automatic_gateways')->insert([
                'name' => 'KCB Buni (System)',
                'slug' => 'Buni',
                'image' => 'buni.png',
                'status' => 0, // Disabled by default
                'is_crypto' => 0,
                'parameters' => json_encode([
                    'buni_base_url' => '',
                    'buni_client_id' => '',
                    'buni_client_secret' => '',
                    'till_number' => ''
                ]),
                'currency' => 'KES',
                'supported_currencies' => json_encode(['KES' => 'KES']),
                'tenant_id' => null, // System-wide gateway
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($this->command) {
            $this->command->info('Buni automatic gateway seeded successfully!');
        }
    }
}
