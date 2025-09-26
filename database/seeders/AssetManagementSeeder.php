<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\Asset;
use Illuminate\Database\Seeder;

class AssetManagementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run($tenantId = null)
    {
        if (!$tenantId) {
            // Get the first tenant
            $tenant = \App\Models\Tenant::first();
            
            if (!$tenant) {
                $this->command->info('No tenants found. Skipping asset management seeding.');
                return;
            }
            
            $tenantId = $tenant->id;
        }

        $this->seedAssetCategories($tenantId);
        $this->seedSampleAssets($tenantId);
        
        $this->command->info('Asset management data seeded successfully!');
    }

    /**
     * Seed asset categories
     */
    private function seedAssetCategories($tenantId)
    {
        $categories = [
            [
                'name' => 'Vehicles',
                'description' => 'Cars, motorcycles, trucks, and other vehicles',
                'type' => 'leasable',
            ],
            [
                'name' => 'Office Equipment',
                'description' => 'Computers, printers, furniture, and office supplies',
                'type' => 'fixed',
            ],
            [
                'name' => 'Investment Portfolio',
                'description' => 'Stocks, bonds, mutual funds, and other investments',
                'type' => 'investment',
            ],
            [
                'name' => 'Event Equipment',
                'description' => 'Tents, chairs, tables, and event supplies',
                'type' => 'leasable',
            ],
            [
                'name' => 'Real Estate',
                'description' => 'Buildings, land, and property investments',
                'type' => 'fixed',
            ],
            [
                'name' => 'Agricultural Equipment',
                'description' => 'Farming tools, tractors, and agricultural machinery',
                'type' => 'leasable',
            ],
            [
                'name' => 'Technology Equipment',
                'description' => 'Servers, networking equipment, and IT infrastructure',
                'type' => 'fixed',
            ],
            [
                'name' => 'Financial Instruments',
                'description' => 'Investment accounts, fixed deposits, and securities',
                'type' => 'investment',
            ],
        ];

        foreach ($categories as $categoryData) {
            AssetCategory::firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'name' => $categoryData['name']
                ],
                [
                    'description' => $categoryData['description'],
                    'type' => $categoryData['type'],
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Seed sample assets
     */
    private function seedSampleAssets($tenantId)
    {
        // Get created categories
        $vehicleCategory = AssetCategory::where('tenant_id', $tenantId)
                                      ->where('name', 'Vehicles')
                                      ->first();

        $officeCategory = AssetCategory::where('tenant_id', $tenantId)
                                     ->where('name', 'Office Equipment')
                                     ->first();

        $eventCategory = AssetCategory::where('tenant_id', $tenantId)
                                    ->where('name', 'Event Equipment')
                                    ->first();

        $realEstateCategory = AssetCategory::where('tenant_id', $tenantId)
                                         ->where('name', 'Real Estate')
                                         ->first();

        $investmentCategory = AssetCategory::where('tenant_id', $tenantId)
                                         ->where('name', 'Investment Portfolio')
                                         ->first();

        $sampleAssets = [];

        // Vehicle assets
        if ($vehicleCategory) {
            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $vehicleCategory->id,
                'name' => 'Toyota Corolla 2020',
                'asset_code' => 'VEH-0001',
                'description' => 'Reliable sedan for transportation and lease',
                'purchase_value' => 25000.00,
                'current_value' => 22000.00,
                'purchase_date' => '2020-01-15',
                'warranty_expiry' => '2025-01-15',
                'location' => 'Main Parking Lot',
                'status' => 'active',
                'is_leasable' => true,
                'lease_rate' => 50.00,
                'lease_rate_type' => 'daily',
                'notes' => 'Well-maintained vehicle suitable for member rentals',
            ];

            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $vehicleCategory->id,
                'name' => 'Honda Motorcycle CBR150',
                'asset_code' => 'VEH-0002',
                'description' => 'Fuel-efficient motorcycle for quick transportation',
                'purchase_value' => 3500.00,
                'current_value' => 3000.00,
                'purchase_date' => '2021-03-10',
                'warranty_expiry' => '2024-03-10',
                'location' => 'Motorcycle Parking',
                'status' => 'active',
                'is_leasable' => true,
                'lease_rate' => 25.00,
                'lease_rate_type' => 'daily',
                'notes' => 'Popular choice for short-distance travel',
            ];
        }

        // Office equipment
        if ($officeCategory) {
            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $officeCategory->id,
                'name' => 'Dell OptiPlex 7090 Desktop',
                'asset_code' => 'OFF-0001',
                'description' => 'High-performance desktop computer for office work',
                'purchase_value' => 1200.00,
                'current_value' => 800.00,
                'purchase_date' => '2022-05-20',
                'warranty_expiry' => '2025-05-20',
                'location' => 'Main Office - Desk 1',
                'status' => 'active',
                'is_leasable' => false,
                'lease_rate' => null,
                'lease_rate_type' => 'daily',
                'notes' => 'Primary workstation for administrative tasks',
            ];

            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $officeCategory->id,
                'name' => 'HP LaserJet Pro Printer',
                'asset_code' => 'OFF-0002',
                'description' => 'Professional laser printer for office documents',
                'purchase_value' => 450.00,
                'current_value' => 300.00,
                'purchase_date' => '2021-08-15',
                'warranty_expiry' => '2024-08-15',
                'location' => 'Main Office - Print Station',
                'status' => 'active',
                'is_leasable' => false,
                'lease_rate' => null,
                'lease_rate_type' => 'daily',
                'notes' => 'Shared printer for all office staff',
            ];
        }

        // Event equipment
        if ($eventCategory) {
            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $eventCategory->id,
                'name' => 'Large Event Tent (20x30ft)',
                'asset_code' => 'EVE-0001',
                'description' => 'Weather-resistant tent for outdoor events',
                'purchase_value' => 800.00,
                'current_value' => 650.00,
                'purchase_date' => '2020-12-01',
                'warranty_expiry' => null,
                'location' => 'Storage Warehouse',
                'status' => 'active',
                'is_leasable' => true,
                'lease_rate' => 75.00,
                'lease_rate_type' => 'daily',
                'notes' => 'Popular for weddings and community events',
            ];

            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $eventCategory->id,
                'name' => 'Folding Chairs Set (50 pieces)',
                'asset_code' => 'EVE-0002',
                'description' => 'Stackable chairs for events and meetings',
                'purchase_value' => 500.00,
                'current_value' => 400.00,
                'purchase_date' => '2021-02-28',
                'warranty_expiry' => null,
                'location' => 'Storage Room B',
                'status' => 'active',
                'is_leasable' => true,
                'lease_rate' => 1.00,
                'lease_rate_type' => 'daily',
                'notes' => 'Durable and easy to transport',
            ];
        }

        // Real estate
        if ($realEstateCategory) {
            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $realEstateCategory->id,
                'name' => 'Cooperative Main Building',
                'asset_code' => 'REA-0001',
                'description' => 'Main office building and meeting hall',
                'purchase_value' => 150000.00,
                'current_value' => 180000.00,
                'purchase_date' => '2018-06-01',
                'warranty_expiry' => null,
                'location' => '123 Main Street, City Center',
                'status' => 'active',
                'is_leasable' => false,
                'lease_rate' => null,
                'lease_rate_type' => 'daily',
                'notes' => 'Headquarters and primary operational facility',
            ];
        }

        // Investment assets
        if ($investmentCategory) {
            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $investmentCategory->id,
                'name' => 'Government Bond Portfolio',
                'asset_code' => 'INV-0001',
                'description' => 'Low-risk government bonds for stable returns',
                'purchase_value' => 50000.00,
                'current_value' => 52500.00,
                'purchase_date' => '2022-01-01',
                'warranty_expiry' => null,
                'location' => 'Investment Account - Bank A',
                'status' => 'active',
                'is_leasable' => false,
                'lease_rate' => null,
                'lease_rate_type' => 'daily',
                'notes' => 'Conservative investment for steady income',
                'metadata' => json_encode([
                    'bond_type' => 'government',
                    'maturity_date' => '2027-01-01',
                    'interest_rate' => '3.5%',
                    'annual_yield' => 1750.00
                ])
            ];

            $sampleAssets[] = [
                'tenant_id' => $tenantId,
                'category_id' => $investmentCategory->id,
                'name' => 'Mutual Fund Investment',
                'asset_code' => 'INV-0002',
                'description' => 'Diversified equity mutual fund portfolio',
                'purchase_value' => 30000.00,
                'current_value' => 33600.00,
                'purchase_date' => '2021-07-15',
                'warranty_expiry' => null,
                'location' => 'Investment Account - Fund Manager XYZ',
                'status' => 'active',
                'is_leasable' => false,
                'lease_rate' => null,
                'lease_rate_type' => 'daily',
                'notes' => 'Growth-oriented investment for long-term gains',
                'metadata' => json_encode([
                    'fund_type' => 'equity',
                    'risk_level' => 'moderate',
                    'annual_return' => '12%',
                    'management_fee' => '1.5%'
                ])
            ];
        }

        // Create sample assets
        foreach ($sampleAssets as $assetData) {
            Asset::firstOrCreate(
                [
                    'tenant_id' => $assetData['tenant_id'],
                    'asset_code' => $assetData['asset_code']
                ],
                $assetData
            );
        }
    }
}
