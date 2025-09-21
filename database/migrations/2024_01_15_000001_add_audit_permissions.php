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
            // Add audit permissions based on role type
            $permissions = [];
            
            if ($role->name === 'Admin' || $role->name === 'admin') {
                // Admin gets full audit access
                $permissions = [
                    'audit.index',
                    'audit.show',
                    'audit.get_table_data',
                    'audit.statistics',
                    'audit.export'
                ];
            } elseif ($role->name === 'Staff' || $role->name === 'staff' || $role->name === 'User' || $role->name === 'user') {
                // Staff gets limited audit access
                $permissions = [
                    'audit.index',
                    'audit.show',
                    'audit.get_table_data'
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
        // Remove audit permissions
        DB::table('permissions')
            ->whereIn('permission', [
                'audit.index',
                'audit.show',
                'audit.get_table_data',
                'audit.statistics',
                'audit.export'
            ])
            ->delete();
    }
};
