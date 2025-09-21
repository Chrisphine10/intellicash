<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Tenant;

class SeedRolesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default roles for all tenants';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Seeding roles for all tenants...');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Check if roles already exist for this tenant
            $existingRoles = DB::table('roles')->where('tenant_id', $tenant->id)->count();
            
            if ($existingRoles === 0) {
                // Create default roles for the tenant
                DB::table('roles')->insert([
                    [
                        'name' => 'Manager',
                        'description' => 'Manager role with full access to most features',
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'name' => 'Staff',
                        'description' => 'Staff role with limited access',
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'name' => 'Viewer',
                        'description' => 'Viewer role with read-only access',
                        'tenant_id' => $tenant->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);

                $this->info("Created roles for tenant: {$tenant->name} (ID: {$tenant->id})");
            } else {
                $this->info("Roles already exist for tenant: {$tenant->name} (ID: {$tenant->id})");
            }
        }

        $this->info('Role seeding completed!');
        return 0;
    }
}
