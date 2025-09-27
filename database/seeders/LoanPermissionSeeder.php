<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\AccessControl;
use Illuminate\Support\Facades\DB;

class LoanPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define loan-related permissions
        $loanPermissions = [
            'loan.view',
            'loan.create',
            'loan.edit',
            'loan.delete',
            'loan.approve',
            'loan.reject',
            'loan_payment.view',
            'loan_payment.create',
            'loan_payment.edit',
            'loan_payment.delete',
            'loan_repayment.view',
            'loan_repayment.create',
            'loan_repayment.edit',
            'loan_repayment.delete',
            'loan_report.view',
            'loan_collateral.view',
            'loan_collateral.create',
            'loan_collateral.edit',
            'loan_collateral.delete',
            'guarantor.view',
            'guarantor.create',
            'guarantor.edit',
            'guarantor.delete',
        ];

        // Get all existing tenants and their roles
        $tenants = DB::table('tenants')->get();
        
        if ($tenants->isEmpty()) {
            if ($this->command) {
                $this->command->info('No tenants found. Skipping loan permission seeding.');
            }
            return;
        }
        
        foreach ($tenants as $tenant) {
            $roles = DB::table('roles')->where('tenant_id', $tenant->id)->get();
            
            foreach ($roles as $role) {
                // Add loan permissions based on role type
                $permissionsToAdd = [];
                
                if ($role->name === 'Admin' || $role->name === 'admin') {
                    // Admin gets full loan access
                    $permissionsToAdd = $loanPermissions;
                } elseif ($role->name === 'Staff' || $role->name === 'staff' || $role->name === 'User' || $role->name === 'user') {
                    // Staff gets limited loan access
                    $permissionsToAdd = [
                        'loan.view',
                        'loan.create',
                        'loan_payment.view',
                        'loan_payment.create',
                        'loan_repayment.view',
                        'loan_repayment.create',
                        'loan_collateral.view',
                        'loan_collateral.create',
                        'guarantor.view',
                        'guarantor.create',
                    ];
                }
                
                // Insert permissions for this role
                foreach ($permissionsToAdd as $permission) {
                    // Check if permission already exists
                    $exists = DB::table('permissions')
                        ->where('role_id', $role->id)
                        ->where('permission', $permission)
                        ->exists();
                    
                    if (!$exists) {
                        DB::table('permissions')->insert([
                            'role_id' => $role->id,
                            'permission' => $permission,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
            
            // Create specific loan roles for this tenant if they don't exist
            $loanManagerRole = Role::firstOrCreate([
                'name' => 'Loan Manager',
                'tenant_id' => $tenant->id,
            ], [
                'description' => 'Loan Manager with full access to loan management functions',
            ]);

            $loanOfficerRole = Role::firstOrCreate([
                'name' => 'Loan Officer',
                'tenant_id' => $tenant->id,
            ], [
                'description' => 'Loan Officer with limited access to loan functions',
            ]);

            // Assign permissions to Loan Manager role (full access)
            foreach ($loanPermissions as $permission) {
                $exists = DB::table('permissions')
                    ->where('role_id', $loanManagerRole->id)
                    ->where('permission', $permission)
                    ->exists();
                
                if (!$exists) {
                    DB::table('permissions')->insert([
                        'role_id' => $loanManagerRole->id,
                        'permission' => $permission,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Assign limited permissions to Loan Officer role (view and create only)
            $loanOfficerPermissions = [
                'loan.view',
                'loan.create',
                'loan_payment.view',
                'loan_payment.create',
                'loan_repayment.view',
                'loan_repayment.create',
                'loan_collateral.view',
                'loan_collateral.create',
                'guarantor.view',
                'guarantor.create',
            ];

            foreach ($loanOfficerPermissions as $permission) {
                $exists = DB::table('permissions')
                    ->where('role_id', $loanOfficerRole->id)
                    ->where('permission', $permission)
                    ->exists();
                
                if (!$exists) {
                    DB::table('permissions')->insert([
                        'role_id' => $loanOfficerRole->id,
                        'permission' => $permission,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }

        if ($this->command) {
            $this->command->info('Loan permissions seeded successfully!');
            $this->command->info('Updated existing roles and created: Loan Manager (full access), Loan Officer (limited access)');
        }
    }
}