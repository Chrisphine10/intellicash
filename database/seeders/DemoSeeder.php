<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Exception;

class DemoSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        // Only show info if running from command line
        if ($this->command) {
            $this->command->info('Seeding demo data...');
        }

        DB::beginTransaction();

        try {
            // Check if dummy data file exists and import it (skip if data already exists)
            $dummyDataFile = 'public/uploads/dummy_data.sql';
            if (file_exists($dummyDataFile) && !$this->hasExistingData()) {
                try {
                    DB::unprepared(file_get_contents($dummyDataFile));
                    if ($this->command) {
                        $this->command->info('Imported dummy data from SQL file.');
                    }
                } catch (Exception $e) {
                    if ($this->command) {
                        $this->command->warn('Skipped dummy data import (data may already exist): ' . $e->getMessage());
                    }
                }
            } elseif ($this->command) {
                $this->command->info('Skipped dummy data import (data already exists or file not found).');
            }

            // Create Super Admin if not exists
            $superAdminExists = DB::table('users')->where('user_type', 'superadmin')->exists();
            if (!$superAdminExists) {
                DB::table('users')->insert([
                    'name'              => 'Super Admin',
                    'email'             => 'admin@demo.com',
                    'email_verified_at' => now(),
                    'password'          => Hash::make('123456'),
                    'status'            => 1,
                    'profile_picture'   => 'default.png',
                    'user_type'         => 'superadmin',
                ]);
                if ($this->command) {
                    $this->command->info('Created Super Admin user.');
                }
            }

            // Create Tenant if not exists
            $existingTenant = DB::table('tenants')->where('slug', 'intelli-demo')->first();
            if (!$existingTenant) {
                $tenantId = DB::table('tenants')->insertGetId([
                    'slug'              => 'intelli-demo',
                    'name'              => 'IntelliDemo',
                    'membership_type'   => 'member',
                    'package_id'        => 7,
                    'subscription_date' => now(),
                    'valid_to'          => date('Y-m-d', strtotime(now() . ' + 25 years')),
                    'status'            => 1,
                ]);

                if ($this->command) {
                    $this->command->info('Created IntelliDemo tenant.');
                }
            } else {
                $tenantId = $existingTenant->id;
                if ($this->command) {
                    $this->command->info('IntelliDemo tenant already exists.');
                }
            }

            // Create tenant owner if not exists
            $ownerExists = DB::table('users')->where('tenant_id', $tenantId)->where('tenant_owner', 1)->exists();
            if (!$ownerExists) {
                // Get the Admin role for this tenant
                $adminRole = DB::table('roles')->where('name', 'Admin')->where('tenant_id', $tenantId)->first();
                
                DB::table('users')->insert([
                    'name'            => 'IntelliDemo Admin',
                    'email'           => 'admin@intellidemo.com',
                    'user_type'       => 'admin',
                    'tenant_id'       => $tenantId,
                    'tenant_owner'    => 1,
                    'role_id'         => $adminRole ? $adminRole->id : null,
                    'status'          => 1,
                    'profile_picture' => 'default.png',
                    'password'        => Hash::make('123456'),
                    'email_verified_at' => now(),
                ]);
                if ($this->command) {
                    $this->command->info('Created IntelliDemo tenant owner with Admin role.');
                }
            } else {
                // Update existing owner to have Admin role if they don't have one
                $existingOwner = DB::table('users')->where('tenant_id', $tenantId)->where('tenant_owner', 1)->first();
                if ($existingOwner && !$existingOwner->role_id) {
                    $adminRole = DB::table('roles')->where('name', 'Admin')->where('tenant_id', $tenantId)->first();
                    if ($adminRole) {
                        DB::table('users')->where('id', $existingOwner->id)->update(['role_id' => $adminRole->id]);
                        if ($this->command) {
                            $this->command->info('Updated existing tenant owner with Admin role.');
                        }
                    }
                }
            }

            // Create currencies for tenant if not exist
            $currenciesExist = DB::table('currency')->where('tenant_id', $tenantId)->exists();
            if (!$currenciesExist) {
                DB::table('currency')->insert([
                    [
                        'full_name'     => 'Kenyan Shilling',
                        'name'          => 'KES',
                        'exchange_rate' => 1.000000,
                        'base_currency' => 1,
                        'status'        => 1,
                        'tenant_id'     => $tenantId,
                    ],
                    [
                        'full_name'     => 'United States Dollar',
                        'name'          => 'USD',
                        'exchange_rate' => 0.007500,
                        'base_currency' => 0,
                        'status'        => 1,
                        'tenant_id'     => $tenantId,
                    ],
                ]);
                if ($this->command) {
                    $this->command->info('Created currencies for tenant.');
                }
            }

            // Run SaasSeeder to create roles and additional tenant data
            $saasSeeder = new \Database\Seeders\SaasSeeder();
            $saasSeeder->run($tenantId);
            if ($this->command) {
                $this->command->info('Created roles and permissions for tenant.');
            }

            DB::commit();

            if ($this->command) {
                $this->command->info('Demo data seeded successfully!');
                $this->command->info("Tenant: IntelliDemo (ID: {$tenantId})");
                $this->command->info('Login URL: ' . url('/intelli-demo/login'));
            }

        } catch (Exception $e) {
            DB::rollBack();
            if ($this->command) {
                $this->command->error('Demo seeding failed: ' . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Check if dummy data already exists
     */
    private function hasExistingData()
    {
        // Check for common dummy data tables
        $tables = ['faqs', 'features', 'packages', 'posts', 'teams', 'testimonials', 'settings'];
        
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                return true;
            }
        }
        
        return false;
    }
}
