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
            // Check if Admin role already exists for this tenant
            $existingAdminRole = DB::table('roles')
                ->where('tenant_id', $tenant->id)
                ->where('name', 'Admin')
                ->first();
            
            if (!$existingAdminRole) {
                // Create Admin role
                $adminRoleId = DB::table('roles')->insertGetId([
                    'name' => 'Admin',
                    'description' => 'Tenant Administrator with full access to all features',
                    'tenant_id' => $tenant->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // Assign comprehensive admin permissions
                $adminPermissions = [
                    // Dashboard - All widgets
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
                    
                    // Members - Full access
                    'members.index',
                    'members.create',
                    'members.store',
                    'members.show',
                    'members.edit',
                    'members.update',
                    'members.destroy',
                    'members.get_table_data',
                    
                    // Users - Full access (Admin only)
                    'users.index',
                    'users.create',
                    'users.store',
                    'users.show',
                    'users.edit',
                    'users.update',
                    'users.destroy',
                    'users.get_table_data',
                    
                    // Roles - Full access (Admin only)
                    'roles.index',
                    'roles.create',
                    'roles.store',
                    'roles.show',
                    'roles.edit',
                    'roles.update',
                    'roles.destroy',
                    
                    // Permissions - Full access (Admin only)
                    'permission.show',
                    'permission.store',
                    
                    // Loans - Full access
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
                    
                    // Loan Products - Full access
                    'loan_products.index',
                    'loan_products.create',
                    'loan_products.store',
                    'loan_products.edit',
                    'loan_products.update',
                    'loan_products.destroy',
                    
                    // Loan Repayments - Full access
                    'loan_repayments.index',
                    'loan_repayments.create',
                    'loan_repayments.store',
                    'loan_repayments.show',
                    'loan_repayments.edit',
                    'loan_repayments.update',
                    'loan_repayments.destroy',
                    'loan_repayments.get_table_data',
                    
                    // Savings Accounts - Full access
                    'savings_accounts.index',
                    'savings_accounts.create',
                    'savings_accounts.store',
                    'savings_accounts.show',
                    'savings_accounts.edit',
                    'savings_accounts.update',
                    'savings_accounts.destroy',
                    'savings_accounts.get_table_data',
                    
                    // Savings Products - Full access
                    'savings_products.index',
                    'savings_products.create',
                    'savings_products.store',
                    'savings_products.edit',
                    'savings_products.update',
                    'savings_products.destroy',
                    
                    // Transactions - Full access
                    'transactions.index',
                    'transactions.create',
                    'transactions.store',
                    'transactions.show',
                    'transactions.edit',
                    'transactions.update',
                    'transactions.destroy',
                    'transactions.get_table_data',
                    
                    // Deposit Requests - Full access
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
                    
                    // Withdraw Requests - Full access
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
                    
                    // Branches - Full access
                    'branches.index',
                    'branches.create',
                    'branches.store',
                    'branches.show',
                    'branches.edit',
                    'branches.update',
                    'branches.destroy',
                    
                    // Currency - Full access
                    'currency.index',
                    'currency.create',
                    'currency.store',
                    'currency.show',
                    'currency.edit',
                    'currency.update',
                    'currency.destroy',
                    
                    // Settings - Full access
                    'settings.index',
                    'settings.update',
                    
                    // Reports - Full access
                    'reports.index',
                    'reports.members',
                    'reports.loans',
                    'reports.savings',
                    'reports.transactions',
                    'reports.deposits',
                    'reports.withdrawals',
                    
                    // Audit - Full access
                    'audit.index',
                    'audit.show',
                    'audit.get_table_data',
                    'audit.statistics',
                    'audit.export',
                    
                    // VSLA Module - Full access (when enabled)
                    'vsla.settings.index',
                    'vsla.settings.update',
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
                    'vsla.transactions.history',
                    'vsla.transactions.edit',
                    'vsla.transactions.update',
                    'vsla.transactions.destroy',
                    'vsla.cycles.index',
                    'vsla.cycles.create',
                    'vsla.cycles.store',
                    'vsla.cycles.show',
                    'vsla.cycles.edit',
                    'vsla.cycles.update',
                    'vsla.cycles.destroy',
                    'vsla.shareouts.index',
                    'vsla.shareouts.create',
                    'vsla.shareouts.store',
                    'vsla.shareouts.show',
                    'vsla.shareouts.edit',
                    'vsla.shareouts.update',
                    'vsla.shareouts.destroy',
                    
                    // Asset Management - Full access (when enabled)
                    'assets.index',
                    'assets.create',
                    'assets.store',
                    'assets.show',
                    'assets.edit',
                    'assets.update',
                    'assets.destroy',
                    'assets.get_table_data',
                    'asset_categories.index',
                    'asset_categories.create',
                    'asset_categories.store',
                    'asset_categories.show',
                    'asset_categories.edit',
                    'asset_categories.update',
                    'asset_categories.destroy',
                    
                    // E-Signature - Full access (when enabled)
                    'esignature.documents.index',
                    'esignature.documents.create',
                    'esignature.documents.store',
                    'esignature.documents.show',
                    'esignature.documents.edit',
                    'esignature.documents.update',
                    'esignature.documents.destroy',
                    'esignature.documents.sign',
                    'esignature.documents.approve',
                    'esignature.documents.reject',
                    
                    // Payroll - Full access (when enabled)
                    'payroll.employees.index',
                    'payroll.employees.create',
                    'payroll.employees.store',
                    'payroll.employees.show',
                    'payroll.employees.edit',
                    'payroll.employees.update',
                    'payroll.employees.destroy',
                    'payroll.salaries.index',
                    'payroll.salaries.create',
                    'payroll.salaries.store',
                    'payroll.salaries.show',
                    'payroll.salaries.edit',
                    'payroll.salaries.update',
                    'payroll.salaries.destroy',
                    'payroll.salaries.process',
                    'payroll.reports.index',
                    
                    // API Management - Full access (when enabled)
                    'api.keys.index',
                    'api.keys.create',
                    'api.keys.store',
                    'api.keys.show',
                    'api.keys.edit',
                    'api.keys.update',
                    'api.keys.destroy',
                    'api.keys.regenerate',
                    
                    // Voting System - Full access (when enabled)
                    'voting.elections.index',
                    'voting.elections.create',
                    'voting.elections.store',
                    'voting.elections.show',
                    'voting.elections.edit',
                    'voting.elections.update',
                    'voting.elections.destroy',
                    'voting.candidates.index',
                    'voting.candidates.create',
                    'voting.candidates.store',
                    'voting.candidates.show',
                    'voting.candidates.edit',
                    'voting.candidates.update',
                    'voting.candidates.destroy',
                    'voting.votes.index',
                    'voting.votes.create',
                    'voting.votes.store',
                    'voting.results.index',
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
            
            // Assign Admin role to existing tenant owners who don't have a role assigned
            $tenantOwners = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('tenant_owner', 1)
                ->whereNull('role_id')
                ->get();
            
            foreach ($tenantOwners as $owner) {
                DB::table('users')
                    ->where('id', $owner->id)
                    ->update(['role_id' => $existingAdminRole ? $existingAdminRole->id : $adminRoleId]);
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
            // Remove permissions
            DB::table('permissions')->where('role_id', $role->id)->delete();
            
            // Remove role
            DB::table('roles')->where('id', $role->id)->delete();
        }
        
        // Clear role_id for tenant owners
        DB::table('users')
            ->where('tenant_owner', 1)
            ->update(['role_id' => null]);
    }
};
