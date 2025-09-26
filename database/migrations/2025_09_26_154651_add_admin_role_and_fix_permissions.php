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
        // Get all tenants
        $tenants = DB::table('tenants')->get();
        
        foreach ($tenants as $tenant) {
            // Check if Admin role already exists for this tenant
            $adminRoleExists = DB::table('roles')
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Admin')
                ->exists();
            
            if (!$adminRoleExists) {
                // Create Admin role for this tenant
                $adminRoleId = DB::table('roles')->insertGetId([
                    'name' => 'Admin',
                    'description' => 'Administrator role with full system access',
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Assign comprehensive permissions to Admin role
                $adminPermissions = [
                    // Dashboard
                    'dashboard.index',
                    'dashboard.total_customer_widget',
                    'dashboard.deposit_requests_widget',
                    'dashboard.withdraw_requests_widget',
                    'dashboard.loan_requests_widget',
                    'dashboard.pending_loans_widget',
                    'dashboard.overdue_loans_widget',
                    'dashboard.total_loans_widget',
                    'dashboard.total_savings_widget',
                    'dashboard.total_deposits_widget',
                    'dashboard.total_withdrawals_widget',
                    'dashboard.recent_transactions_widget',
                    'dashboard.due_repayments_widget',
                    'dashboard.loan_balances_widget',
                    
                    // User Management
                    'users.index',
                    'users.create',
                    'users.store',
                    'users.show',
                    'users.edit',
                    'users.update',
                    'users.destroy',
                    'users.get_table_data',
                    
                    // Role Management
                    'roles.index',
                    'roles.create',
                    'roles.store',
                    'roles.show',
                    'roles.edit',
                    'roles.update',
                    'roles.destroy',
                    
                    // Permission Management
                    'permission.show',
                    'permission.store',
                    
                    // Members
                    'members.index',
                    'members.create',
                    'members.store',
                    'members.show',
                    'members.edit',
                    'members.update',
                    'members.destroy',
                    'members.get_table_data',
                    
                    // Loans
                    'loans.index',
                    'loans.create',
                    'loans.store',
                    'loans.show',
                    'loans.edit',
                    'loans.update',
                    'loans.destroy',
                    'loans.get_table_data',
                    'loans.filter',
                    'loans.approve',
                    'loans.reject',
                    'loans.disburse',
                    
                    // Loan Products
                    'loan_products.index',
                    'loan_products.create',
                    'loan_products.store',
                    'loan_products.edit',
                    'loan_products.update',
                    'loan_products.destroy',
                    
                    // Loan Repayments
                    'loan_repayments.index',
                    'loan_repayments.create',
                    'loan_repayments.store',
                    'loan_repayments.show',
                    'loan_repayments.edit',
                    'loan_repayments.update',
                    'loan_repayments.destroy',
                    'loan_repayments.get_table_data',
                    
                    // Savings Accounts
                    'savings_accounts.index',
                    'savings_accounts.create',
                    'savings_accounts.store',
                    'savings_accounts.show',
                    'savings_accounts.edit',
                    'savings_accounts.update',
                    'savings_accounts.destroy',
                    'savings_accounts.get_table_data',
                    
                    // Savings Products
                    'savings_products.index',
                    'savings_products.create',
                    'savings_products.store',
                    'savings_products.edit',
                    'savings_products.update',
                    'savings_products.destroy',
                    
                    // Transactions
                    'transactions.index',
                    'transactions.create',
                    'transactions.store',
                    'transactions.show',
                    'transactions.edit',
                    'transactions.update',
                    'transactions.destroy',
                    'transactions.get_table_data',
                    
                    // Deposit Requests
                    'deposit_requests.index',
                    'deposit_requests.create',
                    'deposit_requests.store',
                    'deposit_requests.show',
                    'deposit_requests.edit',
                    'deposit_requests.update',
                    'deposit_requests.destroy',
                    'deposit_requests.get_table_data',
                    'deposit_requests.approve',
                    'deposit_requests.reject',
                    
                    // Withdraw Requests
                    'withdraw_requests.index',
                    'withdraw_requests.create',
                    'withdraw_requests.store',
                    'withdraw_requests.show',
                    'withdraw_requests.edit',
                    'withdraw_requests.update',
                    'withdraw_requests.destroy',
                    'withdraw_requests.get_table_data',
                    'withdraw_requests.approve',
                    'withdraw_requests.reject',
                    
                    // Branches
                    'branches.index',
                    'branches.create',
                    'branches.store',
                    'branches.show',
                    'branches.edit',
                    'branches.update',
                    'branches.destroy',
                    
                    // Currency
                    'currency.index',
                    'currency.create',
                    'currency.store',
                    'currency.show',
                    'currency.edit',
                    'currency.update',
                    'currency.destroy',
                    
                    // Settings
                    'settings.index',
                    'settings.update',
                    
                    // Reports
                    'reports.index',
                    'reports.members',
                    'reports.loans',
                    'reports.savings',
                    'reports.transactions',
                    'reports.deposits',
                    'reports.withdrawals',
                    
                    // Audit
                    'audit.index',
                    'audit.show',
                    'audit.get_table_data',
                    'audit.statistics',
                    'audit.export',
                    
                    // VSLA (if enabled)
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
                    
                    // Modules
                    'modules.index',
                    'modules.toggle_vsla',
                ];
                
                // Insert permissions for Admin role
                foreach ($adminPermissions as $permission) {
                    DB::table('permissions')->insert([
                        'role_id' => $adminRoleId,
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
        // Remove Admin roles and their permissions
        $adminRoles = DB::table('roles')->where('name', 'Admin')->get();
        
        foreach ($adminRoles as $role) {
            // Delete permissions first
            DB::table('permissions')->where('role_id', $role->id)->delete();
            
            // Delete role
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};