<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get all existing roles
        $roles = DB::table('roles')->get();
        
        foreach ($roles as $role) {
            // Add comprehensive admin permissions
            $adminPermissions = [];
            
            if ($role->name === 'Admin' || $role->name === 'admin') {
                // Admin gets comprehensive access to all system functionality
                $adminPermissions = [
                    // User Management
                    'users.index',
                    'users.create',
                    'users.store',
                    'users.show',
                    'users.edit',
                    'users.update',
                    'users.destroy',
                    
                    // Role Management
                    'roles.index',
                    'roles.create',
                    'roles.store',
                    'roles.show',
                    'roles.edit',
                    'roles.update',
                    'roles.destroy',
                    
                    // Permission Management
                    'permissions.index',
                    'permissions.create',
                    'permissions.store',
                    'permissions.show',
                    'permissions.edit',
                    'permissions.update',
                    'permissions.destroy',
                    
                    // Branch Management
                    'branches.index',
                    'branches.create',
                    'branches.store',
                    'branches.show',
                    'branches.edit',
                    'branches.update',
                    'branches.destroy',
                    
                    // Member Management
                    'members.index',
                    'members.create',
                    'members.store',
                    'members.show',
                    'members.edit',
                    'members.update',
                    'members.destroy',
                    
                    // Loan Management
                    'loans.index',
                    'loans.create',
                    'loans.store',
                    'loans.show',
                    'loans.edit',
                    'loans.update',
                    'loans.destroy',
                    'loans.approve',
                    'loans.reject',
                    
                    // Savings Management
                    'savings_accounts.index',
                    'savings_accounts.create',
                    'savings_accounts.store',
                    'savings_accounts.show',
                    'savings_accounts.edit',
                    'savings_accounts.update',
                    'savings_accounts.destroy',
                    
                    // Transaction Management
                    'transactions.index',
                    'transactions.create',
                    'transactions.store',
                    'transactions.show',
                    'transactions.edit',
                    'transactions.update',
                    'transactions.destroy',
                    
                    // Bank Account Management
                    'bank_accounts.index',
                    'bank_accounts.create',
                    'bank_accounts.store',
                    'bank_accounts.show',
                    'bank_accounts.edit',
                    'bank_accounts.update',
                    'bank_accounts.destroy',
                    
                    // Reports
                    'reports.index',
                    'reports.create',
                    'reports.store',
                    'reports.show',
                    'reports.edit',
                    'reports.update',
                    'reports.destroy',
                    'reports.export',
                    
                    // Settings
                    'settings.index',
                    'settings.create',
                    'settings.store',
                    'settings.show',
                    'settings.edit',
                    'settings.update',
                    'settings.destroy',
                    
                    // Dashboard
                    'dashboard.index',
                    'dashboard.show',
                    
                    // Audit
                    'audit.index',
                    'audit.show',
                    'audit.get_table_data',
                    'audit.statistics',
                    'audit.export',
                    
                    // VSLA Management (already added in previous migration)
                    'vsla.settings.index',
                    'vsla.settings.update',
                    'vsla.settings.sync-accounts',
                    'vsla.settings.assign-role',
                    'vsla.settings.remove-role',
                    'vsla.meetings.index',
                    'vsla.meetings.create',
                    'vsla.meetings.store',
                    'vsla.meetings.show',
                    'vsla.meetings.edit',
                    'vsla.meetings.update',
                    'vsla.meetings.destroy',
                    'vsla.meetings.attendance',
                    'vsla.transactions.index',
                    'vsla.transactions.create',
                    'vsla.transactions.store',
                    'vsla.transactions.bulk_create',
                    'vsla.transactions.bulk_store',
                    'vsla.transactions.get_members',
                    'vsla.transactions.approve',
                    'vsla.transactions.reject',
                    
                    // Module Management
                    'modules.index',
                    'modules.toggle_vsla',
                    
                    // System Administration
                    'system.index',
                    'system.settings',
                    'system.maintenance',
                    'system.backup',
                    'system.restore',
                ];
            }
            
            // Insert permissions for this role
            foreach ($adminPermissions as $permission) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove comprehensive admin permissions
        DB::table('permissions')
            ->whereIn('permission', [
                'users.index', 'users.create', 'users.store', 'users.show', 'users.edit', 'users.update', 'users.destroy',
                'roles.index', 'roles.create', 'roles.store', 'roles.show', 'roles.edit', 'roles.update', 'roles.destroy',
                'permissions.index', 'permissions.create', 'permissions.store', 'permissions.show', 'permissions.edit', 'permissions.update', 'permissions.destroy',
                'branches.index', 'branches.create', 'branches.store', 'branches.show', 'branches.edit', 'branches.update', 'branches.destroy',
                'members.index', 'members.create', 'members.store', 'members.show', 'members.edit', 'members.update', 'members.destroy',
                'loans.index', 'loans.create', 'loans.store', 'loans.show', 'loans.edit', 'loans.update', 'loans.destroy', 'loans.approve', 'loans.reject',
                'savings_accounts.index', 'savings_accounts.create', 'savings_accounts.store', 'savings_accounts.show', 'savings_accounts.edit', 'savings_accounts.update', 'savings_accounts.destroy',
                'transactions.index', 'transactions.create', 'transactions.store', 'transactions.show', 'transactions.edit', 'transactions.update', 'transactions.destroy',
                'bank_accounts.index', 'bank_accounts.create', 'bank_accounts.store', 'bank_accounts.show', 'bank_accounts.edit', 'bank_accounts.update', 'bank_accounts.destroy',
                'reports.index', 'reports.create', 'reports.store', 'reports.show', 'reports.edit', 'reports.update', 'reports.destroy', 'reports.export',
                'settings.index', 'settings.create', 'settings.store', 'settings.show', 'settings.edit', 'settings.update', 'settings.destroy',
                'dashboard.index', 'dashboard.show',
                'system.index', 'system.settings', 'system.maintenance', 'system.backup', 'system.restore',
            ])
            ->delete();
    }
};
