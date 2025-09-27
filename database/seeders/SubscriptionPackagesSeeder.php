<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class SubscriptionPackagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only show info if running from command line
        if ($this->command) {
            $this->command->info('Seeding subscription packages...');
        }
        
        // Skip interactive confirmation in web context to avoid STDIN issues
        // Only allow package clearing in actual console context
        if ($this->command && app()->runningInConsole() && php_sapi_name() === 'cli') {
            try {
                if ($this->command->confirm('Do you want to clear existing packages?', false)) {
                    Package::truncate();
                    $this->command->info('Existing packages cleared.');
                }
            } catch (\Exception $e) {
                $this->command->info('Skipping package clearing due to context limitations.');
            }
        }
        
        $packages = [
            [
                'name' => 'Basic Plan',
                'package_type' => 'basic',
                'cost' => 1999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 0.00,
                'trial_days' => 7,
                'user_limit' => '5',
                'member_limit' => '100',
                'branch_limit' => '2',
                'account_type_limit' => '3',
                'account_limit' => '200',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '50',
                    'asset_limit' => '10',
                    'election_limit' => '5',
                    'employee_limit' => '10',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 0,
                    'payroll_enabled' => 0,
                    'voting_enabled' => 1,
                    'api_enabled' => 0,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 0,
                    'storage_limit_mb' => 100,
                    'file_upload_limit_mb' => 5,
                    'priority_support' => 0,
                    'custom_branding' => 0,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Standard Plan',
                'package_type' => 'standard',
                'cost' => 4999.00,
                'status' => 1,
                'is_popular' => 1,
                'discount' => 500.00,
                'trial_days' => 14,
                'user_limit' => '15',
                'member_limit' => '500',
                'branch_limit' => '5',
                'account_type_limit' => '5',
                'account_limit' => '1000',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '200',
                    'asset_limit' => '50',
                    'election_limit' => '20',
                    'employee_limit' => '50',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 0,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 500,
                    'file_upload_limit_mb' => 10,
                    'priority_support' => 0,
                    'custom_branding' => 0,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional Plan',
                'package_type' => 'professional',
                'cost' => 9999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 1000.00,
                'trial_days' => 30,
                'user_limit' => '50',
                'member_limit' => '2000',
                'branch_limit' => '10',
                'account_type_limit' => '10',
                'account_limit' => '5000',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '1000',
                    'asset_limit' => '200',
                    'election_limit' => '100',
                    'employee_limit' => '200',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 1,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 2000,
                    'file_upload_limit_mb' => 25,
                    'priority_support' => 1,
                    'custom_branding' => 1,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise Plan',
                'package_type' => 'enterprise',
                'cost' => 19999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 2000.00,
                'trial_days' => 30,
                'user_limit' => '100',
                'member_limit' => '5000',
                'branch_limit' => '25',
                'account_type_limit' => '15',
                'account_limit' => '10000',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '2500',
                    'asset_limit' => '500',
                    'election_limit' => '250',
                    'employee_limit' => '500',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 1,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 5000,
                    'file_upload_limit_mb' => 50,
                    'priority_support' => 1,
                    'custom_branding' => 1,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lifetime Plan',
                'package_type' => 'lifetime',
                'cost' => 99999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 10000.00,
                'trial_days' => 0,
                'user_limit' => '-1',
                'member_limit' => '-1',
                'branch_limit' => '-1',
                'account_type_limit' => '-1',
                'account_limit' => '-1',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '-1',
                    'asset_limit' => '-1',
                    'election_limit' => '-1',
                    'employee_limit' => '-1',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 1,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 10000,
                    'file_upload_limit_mb' => 100,
                    'priority_support' => 1,
                    'custom_branding' => 1,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Demo Package',
                'package_type' => 'demo',
                'cost' => 0.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 0.00,
                'trial_days' => 365,
                'user_limit' => '3',
                'member_limit' => '50',
                'branch_limit' => '1',
                'account_type_limit' => '2',
                'account_limit' => '100',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '25',
                    'asset_limit' => '5',
                    'election_limit' => '3',
                    'employee_limit' => '5',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 0,
                    'payroll_enabled' => 0,
                    'voting_enabled' => 1,
                    'api_enabled' => 0,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 0,
                    'storage_limit_mb' => 50,
                    'file_upload_limit_mb' => 2,
                    'priority_support' => 0,
                    'custom_branding' => 0,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($packages as $packageData) {
            $package = Package::firstOrCreate(
                ['name' => $packageData['name'], 'package_type' => $packageData['package_type']],
                $packageData
            );
            
            if ($package->wasRecentlyCreated) {
                if ($this->command) {
                    $this->command->info("Created package: {$package->name} ({$package->package_type})");
                }
            } else {
                if ($this->command) {
                    $this->command->info("Package already exists: {$package->name} ({$package->package_type})");
                }
            }
        }
        
        if ($this->command) {
            $this->command->info('Subscription packages seeded successfully!');
            $this->command->info('Total packages available: ' . Package::count());
        }
    }
}
