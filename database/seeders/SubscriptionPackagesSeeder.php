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
            // MONTHLY PACKAGES (3)
            [
                'name' => 'Basic VSLA',
                'package_type' => 'monthly',
                'cost' => 800.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 0.00,
                'trial_days' => 7,
                'user_limit' => '2',
                'member_limit' => '25',
                'branch_limit' => '1',
                'account_type_limit' => '2',
                'account_limit' => '25',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '10',
                    'asset_limit' => '5',
                    'election_limit' => '2',
                    'employee_limit' => '2',
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
                    'features' => [
                        'VSLA Group Management',
                        'Basic Member Registration',
                        'Savings Tracking',
                        'Share-out Calculations',
                        'Basic Reporting',
                        'Email Support',
                        'Mobile App Access'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Standard Cooperative',
                'package_type' => 'monthly',
                'cost' => 2999.00,
                'status' => 1,
                'is_popular' => 1,
                'discount' => 300.00,
                'trial_days' => 14,
                'user_limit' => '8',
                'member_limit' => '200',
                'branch_limit' => '3',
                'account_type_limit' => '5',
                'account_limit' => '400',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '100',
                    'asset_limit' => '25',
                    'election_limit' => '10',
                    'employee_limit' => '20',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 0,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 250,
                    'file_upload_limit_mb' => 8,
                    'priority_support' => 0,
                    'custom_branding' => 0,
                    'features' => [
                        'Full Member Management',
                        'Loan Management System',
                        'VSLA Module',
                        'Multi-Branch Support',
                        'Advanced Reporting',
                        'Transaction Management',
                        'Priority Support',
                        'API Access'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional Credit Union',
                'package_type' => 'monthly',
                'cost' => 7999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 800.00,
                'trial_days' => 30,
                'user_limit' => '25',
                'member_limit' => '1000',
                'branch_limit' => '8',
                'account_type_limit' => '10',
                'account_limit' => '2000',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '500',
                    'asset_limit' => '100',
                    'election_limit' => '50',
                    'employee_limit' => '100',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 1,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 1000,
                    'file_upload_limit_mb' => 20,
                    'priority_support' => 1,
                    'custom_branding' => 1,
                    'features' => [
                        'Complete Financial Management',
                        'Advanced Loan System',
                        'Asset Management',
                        'Payroll Integration',
                        'E-Signature Module',
                        'Voting System',
                        'Advanced Analytics',
                        'Custom Reports',
                        'API Integration',
                        '24/7 Support'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // YEARLY PACKAGES (3)
            [
                'name' => 'Basic VSLA Yearly',
                'package_type' => 'yearly',
                'cost' => 8000.00, // 10 months price
                'status' => 1,
                'is_popular' => 0,
                'discount' => 1600.00, // 2 months free
                'trial_days' => 14,
                'user_limit' => '2',
                'member_limit' => '25',
                'branch_limit' => '1',
                'account_type_limit' => '2',
                'account_limit' => '25',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '10',
                    'asset_limit' => '5',
                    'election_limit' => '2',
                    'employee_limit' => '2',
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
                    'features' => [
                        'VSLA Group Management',
                        'Basic Member Registration',
                        'Savings Tracking',
                        'Share-out Calculations',
                        'Basic Reporting',
                        'Email Support',
                        'Mobile App Access',
                        '2 months free'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Standard Cooperative Yearly',
                'package_type' => 'yearly',
                'cost' => 29990.00, // 10 months price
                'status' => 1,
                'is_popular' => 0,
                'discount' => 6000.00, // 2 months free
                'trial_days' => 30,
                'user_limit' => '8',
                'member_limit' => '200',
                'branch_limit' => '3',
                'account_type_limit' => '5',
                'account_limit' => '400',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '100',
                    'asset_limit' => '25',
                    'election_limit' => '10',
                    'employee_limit' => '20',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 0,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 250,
                    'file_upload_limit_mb' => 8,
                    'priority_support' => 0,
                    'custom_branding' => 0,
                    'features' => [
                        'Full Member Management',
                        'Loan Management System',
                        'VSLA Module',
                        'Multi-Branch Support',
                        'Advanced Reporting',
                        'Transaction Management',
                        'Priority Support',
                        'API Access',
                        '2 months free'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Professional Credit Union Yearly',
                'package_type' => 'yearly',
                'cost' => 79990.00, // 10 months price
                'status' => 1,
                'is_popular' => 0,
                'discount' => 16000.00, // 2 months free
                'trial_days' => 30,
                'user_limit' => '25',
                'member_limit' => '1000',
                'branch_limit' => '8',
                'account_type_limit' => '10',
                'account_limit' => '2000',
                'member_portal' => 1,
                'others' => json_encode([
                    'loan_limit' => '500',
                    'asset_limit' => '100',
                    'election_limit' => '50',
                    'employee_limit' => '100',
                    'vsla_enabled' => 1,
                    'asset_management_enabled' => 1,
                    'payroll_enabled' => 1,
                    'voting_enabled' => 1,
                    'api_enabled' => 1,
                    'qr_code_enabled' => 1,
                    'esignature_enabled' => 1,
                    'storage_limit_mb' => 1000,
                    'file_upload_limit_mb' => 20,
                    'priority_support' => 1,
                    'custom_branding' => 1,
                    'features' => [
                        'Complete Financial Management',
                        'Advanced Loan System',
                        'Asset Management',
                        'Payroll Integration',
                        'E-Signature Module',
                        'Voting System',
                        'Advanced Analytics',
                        'Custom Reports',
                        'API Integration',
                        '24/7 Support',
                        '2 months free'
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // LIFETIME PACKAGE (1)
            [
                'name' => 'Lifetime Premium',
                'package_type' => 'lifetime',
                'cost' => 199999.00,
                'status' => 1,
                'is_popular' => 0,
                'discount' => 0.00,
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
                    'features' => [
                        'Unlimited Everything',
                        'All Premium Features',
                        'VSLA Management',
                        'Multi-Currency Support',
                        'Advanced Security',
                        'Custom Integrations',
                        'White-Label Options',
                        'Dedicated Support',
                        'Lifetime Updates',
                        'Priority Development'
                    ]
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
