<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class PackageAdvancedFeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update existing packages with advanced features based on their tier
        $packages = Package::all();
        
        foreach ($packages as $package) {
            $this->updatePackageFeatures($package);
        }
        
        $this->command->info('Package advanced features updated successfully!');
    }

    /**
     * Update package features based on package tier
     */
    private function updatePackageFeatures(Package $package): void
    {
        $packageName = strtolower($package->name);
        $packageType = $package->package_type;
        
        // Determine package tier based on name and cost
        $isBasic = str_contains($packageName, 'basic') || $package->cost <= 2000;
        $isStandard = str_contains($packageName, 'standard') || ($package->cost > 2000 && $package->cost <= 5000);
        $isProfessional = str_contains($packageName, 'professional') || ($package->cost > 5000 && $package->cost < 100000);
        $isLifetime = str_contains($packageName, 'lifetime') || $package->cost >= 100000;
        
        $updates = [];
        
        if ($isBasic) {
            // Basic packages - limited features
            $updates = [
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
            ];
        } elseif ($isStandard) {
            // Standard packages - moderate features
            $updates = [
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
            ];
        } elseif ($isProfessional) {
            // Professional packages - full features
            $updates = [
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
            ];
        } elseif ($isLifetime) {
            // Lifetime packages - unlimited features
            $updates = [
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
            ];
        }
        
        // Apply updates
        if (!empty($updates)) {
            $package->update($updates);
            $this->command->info("Updated package: {$package->name} ({$package->package_type})");
        }
    }
}
