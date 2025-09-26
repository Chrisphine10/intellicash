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
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        };

        // Add indexes to users table for better performance
        if (!$indexExists('users', 'users_tenant_type_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'user_type'], 'users_tenant_type_index');
            });
        }

        if (!$indexExists('users', 'users_tenant_role_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'role_id'], 'users_tenant_role_index');
            });
        }

        if (!$indexExists('users', 'users_tenant_branch_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'branch_id'], 'users_tenant_branch_index');
            });
        }

        if (!$indexExists('users', 'users_tenant_status_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'users_tenant_status_index');
            });
        }

        if (!$indexExists('users', 'users_email_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('email', 'users_email_index');
            });
        }

        if (!$indexExists('users', 'users_mobile_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('mobile', 'users_mobile_index');
            });
        }

        if (!$indexExists('users', 'users_created_at_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('created_at', 'users_created_at_index');
            });
        }

        // Add indexes to roles table
        if (!$indexExists('roles', 'roles_tenant_name_index')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index(['tenant_id', 'name'], 'roles_tenant_name_index');
            });
        }

        if (!$indexExists('roles', 'roles_tenant_index')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index('tenant_id', 'roles_tenant_index');
            });
        }

        // Add indexes to permissions table
        if (!$indexExists('permissions', 'permissions_role_permission_index')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index(['role_id', 'permission'], 'permissions_role_permission_index');
            });
        }

        if (!$indexExists('permissions', 'permissions_permission_index')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index('permission', 'permissions_permission_index');
            });
        }

        if (!$indexExists('permissions', 'permissions_role_index')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->index('role_id', 'permissions_role_index');
            });
        }

        // Add indexes to branches table if it exists
        if (Schema::hasTable('branches')) {
            if (!$indexExists('branches', 'branches_tenant_index')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->index('tenant_id', 'branches_tenant_index');
                });
            }

            if (!$indexExists('branches', 'branches_tenant_name_index')) {
                Schema::table('branches', function (Blueprint $table) {
                    $table->index(['tenant_id', 'name'], 'branches_tenant_name_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Helper function to check if index exists
        $indexExists = function($table, $indexName) {
            $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        };

        // Remove indexes from users table
        $userIndexes = [
            'users_tenant_type_index',
            'users_tenant_role_index', 
            'users_tenant_branch_index',
            'users_tenant_status_index',
            'users_email_index',
            'users_mobile_index',
            'users_created_at_index'
        ];

        foreach ($userIndexes as $index) {
            if ($indexExists('users', $index)) {
                Schema::table('users', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        // Remove indexes from roles table
        $roleIndexes = ['roles_tenant_name_index', 'roles_tenant_index'];
        foreach ($roleIndexes as $index) {
            if ($indexExists('roles', $index)) {
                Schema::table('roles', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        // Remove indexes from permissions table
        $permissionIndexes = [
            'permissions_role_permission_index',
            'permissions_permission_index',
            'permissions_role_index'
        ];
        foreach ($permissionIndexes as $index) {
            if ($indexExists('permissions', $index)) {
                Schema::table('permissions', function (Blueprint $table) use ($index) {
                    $table->dropIndex($index);
                });
            }
        }

        // Remove indexes from branches table if it exists
        if (Schema::hasTable('branches')) {
            $branchIndexes = ['branches_tenant_index', 'branches_tenant_name_index'];
            foreach ($branchIndexes as $index) {
                if ($indexExists('branches', $index)) {
                    Schema::table('branches', function (Blueprint $table) use ($index) {
                        $table->dropIndex($index);
                    });
                }
            }
        }
    }
};