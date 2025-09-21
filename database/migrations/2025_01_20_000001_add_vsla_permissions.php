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
            // Add VSLA permissions based on role type
            $permissions = [];
            
            if ($role->name === 'Admin' || $role->name === 'admin') {
                // Admin gets full VSLA access
                $permissions = [
                    // VSLA Settings
                    'vsla.settings.index',
                    'vsla.settings.update',
                    'vsla.settings.sync-accounts',
                    'vsla.settings.assign-role',
                    'vsla.settings.remove-role',
                    
                    // VSLA Meetings
                    'vsla.meetings.index',
                    'vsla.meetings.create',
                    'vsla.meetings.store',
                    'vsla.meetings.show',
                    'vsla.meetings.edit',
                    'vsla.meetings.update',
                    'vsla.meetings.destroy',
                    'vsla.meetings.attendance',
                    
                    // VSLA Transactions
                    'vsla.transactions.index',
                    'vsla.transactions.create',
                    'vsla.transactions.store',
                    'vsla.transactions.bulk_create',
                    'vsla.transactions.bulk_store',
                    'vsla.transactions.get_members',
                    'vsla.transactions.approve',
                    'vsla.transactions.reject',
                    
                    // Module Management (restricted to admin only)
                    'modules.index',
                    'modules.toggle_vsla',
                ];
            } elseif ($role->name === 'Staff' || $role->name === 'staff') {
                // Staff gets limited VSLA access (no settings or module management)
                $permissions = [
                    // VSLA Meetings (read and create only)
                    'vsla.meetings.index',
                    'vsla.meetings.create',
                    'vsla.meetings.store',
                    'vsla.meetings.show',
                    'vsla.meetings.attendance',
                    
                    // VSLA Transactions (limited access)
                    'vsla.transactions.index',
                    'vsla.transactions.create',
                    'vsla.transactions.store',
                    'vsla.transactions.get_members',
                ];
            } elseif ($role->name === 'User' || $role->name === 'user') {
                // Regular users get very limited VSLA access (view only)
                $permissions = [
                    'vsla.meetings.index',
                    'vsla.meetings.show',
                    'vsla.transactions.index',
                ];
            } elseif ($role->name === 'VSLA User') {
                // VSLA User gets full VSLA access (this should already be handled by the VSLA role migration)
                $permissions = [
                    // VSLA Settings (limited access - view only)
                    'vsla.settings.index',
                    
                    // VSLA Meetings (full access)
                    'vsla.meetings.index',
                    'vsla.meetings.create',
                    'vsla.meetings.store',
                    'vsla.meetings.show',
                    'vsla.meetings.edit',
                    'vsla.meetings.update',
                    'vsla.meetings.destroy',
                    'vsla.meetings.attendance',
                    
                    // VSLA Transactions (full access)
                    'vsla.transactions.index',
                    'vsla.transactions.create',
                    'vsla.transactions.store',
                    'vsla.transactions.bulk_create',
                    'vsla.transactions.bulk_store',
                    'vsla.transactions.get_members',
                    'vsla.transactions.approve',
                    'vsla.transactions.reject',
                    'vsla.transactions.history',
                    'vsla.transactions.edit',
                    'vsla.transactions.update',
                    'vsla.transactions.destroy',
                    
                    // Basic dashboard access
                    'dashboard.index',
                    
                    // Members (view only - needed for VSLA operations)
                    'members.index',
                    'members.show',
                    'members.get_table_data',
                    
                    // Savings Accounts (view only - needed for VSLA operations)
                    'savings_accounts.index',
                    'savings_accounts.show',
                    'savings_accounts.get_table_data',
                    
                    // Transactions (view only - needed for VSLA operations)
                    'transactions.index',
                    'transactions.show',
                    'transactions.get_table_data',
                ];
            }
            
            // Insert permissions for this role
            foreach ($permissions as $permission) {
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
        // Remove VSLA permissions
        DB::table('permissions')
            ->whereIn('permission', [
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
                'modules.index',
                'modules.toggle_vsla',
            ])
            ->delete();
    }
};
