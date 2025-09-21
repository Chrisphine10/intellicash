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
        // Get all existing tenants
        $tenants = DB::table('tenants')->get();
        
        foreach ($tenants as $tenant) {
            // Check if VSLA role already exists for this tenant
            $existingVslaRole = DB::table('roles')
                ->where('tenant_id', $tenant->id)
                ->where('name', 'VSLA User')
                ->first();
            
            if (!$existingVslaRole) {
                // Create VSLA User role
                $vslaRoleId = DB::table('roles')->insertGetId([
                    'name' => 'VSLA User',
                    'description' => 'VSLA User role with access to VSLA module features when VSLA is enabled',
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Assign VSLA permissions to the VSLA User role
                $vslaPermissions = [
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
                
                // Insert VSLA permissions for this role
                foreach ($vslaPermissions as $permission) {
                    DB::table('permissions')->insert([
                        'role_id' => $vslaRoleId,
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
        // Remove VSLA User roles and their permissions
        $vslaRoles = DB::table('roles')->where('name', 'VSLA User')->get();
        
        foreach ($vslaRoles as $role) {
            // Remove permissions for this role
            DB::table('permissions')->where('role_id', $role->id)->delete();
            
            // Remove the role
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};
