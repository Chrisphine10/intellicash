<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaasSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($tenantId): void {
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

        // Create default roles for the tenant
        DB::table('roles')->insert([
            [
                'name' => 'Admin',
                'description' => 'Tenant Administrator with full access to all features',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Manager',
                'description' => 'Manager role with full access to most features',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Staff',
                'description' => 'Staff role with limited access',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Agent',
                'description' => 'Loan Agent role with access to loan management features',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Viewer',
                'description' => 'Viewer role with read-only access',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'VSLA User',
                'description' => 'VSLA User role with access to VSLA module features when VSLA is enabled',
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        
        // Get the created roles to assign permissions
        $createdRoles = DB::table('roles')->where('tenant_id', $tenantId)->get();
        
        // Assign default permissions to each role
        foreach ($createdRoles as $role) {
            $this->assignDefaultPermissions($role, $tenantId);
        }
    }
    
    /**
     * Assign default permissions to a role
     */
    private function assignDefaultPermissions($role, $tenantId)
    {
        $permissions = [];
        
        switch ($role->name) {
            case 'Admin':
                $permissions = $this->getAdminPermissions();
                break;
            case 'Manager':
                $permissions = $this->getManagerPermissions();
                break;
            case 'Staff':
                $permissions = $this->getStaffPermissions();
                break;
            case 'Agent':
                $permissions = $this->getAgentPermissions();
                break;
            case 'Viewer':
                $permissions = $this->getViewerPermissions();
                break;
            case 'VSLA User':
                $permissions = $this->getVslaUserPermissions();
                break;
        }
        
        // Insert permissions for this role
        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'role_id' => $role->id,
                'permission' => $permission,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }
    
    /**
     * Get Admin permissions (Full access to all features including system administration)
     */
    private function getAdminPermissions()
    {
        return [
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
    }
    
    /**
     * Get Manager permissions (Full access to most features)
     */
    private function getManagerPermissions()
    {
        return [
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
            
            // Members
            'members.index',
            'members.create',
            'members.store',
            'members.show',
            'members.edit',
            'members.update',
            'members.destroy',
            'members.get_table_data',
            
            // Users
            'users.index',
            'users.create',
            'users.store',
            'users.show',
            'users.edit',
            'users.update',
            'users.destroy',
            'users.get_table_data',
            
            // Roles
            'roles.index',
            'roles.create',
            'roles.store',
            'roles.show',
            'roles.edit',
            'roles.update',
            'roles.destroy',
            
            // Permissions
            'permission.show',
            'permission.store',
            
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
        ];
    }
    
    /**
     * Get Staff permissions (Limited access)
     */
    private function getStaffPermissions()
    {
        return [
            // Dashboard
            'dashboard.index',
            'dashboard.total_customer_widget',
            'dashboard.deposit_requests_widget',
            'dashboard.withdraw_requests_widget',
            'dashboard.loan_requests_widget',
            
            // Members (View and basic operations)
            'members.index',
            'members.create',
            'members.store',
            'members.show',
            'members.edit',
            'members.update',
            'members.get_table_data',
            
            // Loans (View and basic operations)
            'loans.index',
            'loans.create',
            'loans.store',
            'loans.show',
            'loans.edit',
            'loans.update',
            'loans.get_table_data',
            'loans.filter',
            
            // Loan Repayments
            'loan_repayments.index',
            'loan_repayments.create',
            'loan_repayments.store',
            'loan_repayments.show',
            'loan_repayments.edit',
            'loan_repayments.update',
            'loan_repayments.get_table_data',
            
            // Savings Accounts
            'savings_accounts.index',
            'savings_accounts.create',
            'savings_accounts.store',
            'savings_accounts.show',
            'savings_accounts.edit',
            'savings_accounts.update',
            'savings_accounts.get_table_data',
            
            // Transactions
            'transactions.index',
            'transactions.create',
            'transactions.store',
            'transactions.show',
            'transactions.edit',
            'transactions.update',
            'transactions.get_table_data',
            
            // Deposit Requests (View only)
            'deposit_requests.index',
            'deposit_requests.show',
            'deposit_requests.get_table_data',
            
            // Withdraw Requests (View only)
            'withdraw_requests.index',
            'withdraw_requests.show',
            'withdraw_requests.get_table_data',
            
            // Reports (Basic)
            'reports.index',
            'reports.members',
            'reports.loans',
            'reports.savings',
            'reports.transactions',
        ];
    }
    
    /**
     * Get Agent permissions (Loan management focus)
     */
    private function getAgentPermissions()
    {
        return [
            // Dashboard
            'dashboard.index',
            'dashboard.total_customer_widget',
            'dashboard.loan_requests_widget',
            'dashboard.pending_loans_widget',
            'dashboard.overdue_loans_widget',
            'dashboard.total_loans_widget',
            'dashboard.due_repayments_widget',
            
            // Members (View and basic operations)
            'members.index',
            'members.create',
            'members.store',
            'members.show',
            'members.edit',
            'members.update',
            'members.get_table_data',
            
            // Loans (Full access)
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
            
            // Loan Products (View only)
            'loan_products.index',
            'loan_products.show',
            
            // Loan Repayments (Full access)
            'loan_repayments.index',
            'loan_repayments.create',
            'loan_repayments.store',
            'loan_repayments.show',
            'loan_repayments.edit',
            'loan_repayments.update',
            'loan_repayments.destroy',
            'loan_repayments.get_table_data',
            
            // Savings Accounts (View and basic operations)
            'savings_accounts.index',
            'savings_accounts.create',
            'savings_accounts.store',
            'savings_accounts.show',
            'savings_accounts.edit',
            'savings_accounts.update',
            'savings_accounts.get_table_data',
            
            // Transactions (View and basic operations)
            'transactions.index',
            'transactions.create',
            'transactions.store',
            'transactions.show',
            'transactions.edit',
            'transactions.update',
            'transactions.get_table_data',
            
            // Reports (Loan focused)
            'reports.index',
            'reports.loans',
            'reports.members',
            'reports.transactions',
        ];
    }
    
    /**
     * Get Viewer permissions (Read-only access)
     */
    private function getViewerPermissions()
    {
        return [
            // Dashboard (View only)
            'dashboard.index',
            'dashboard.total_customer_widget',
            'dashboard.total_loans_widget',
            'dashboard.total_savings_widget',
            
            // Members (View only)
            'members.index',
            'members.show',
            'members.get_table_data',
            
            // Loans (View only)
            'loans.index',
            'loans.show',
            'loans.get_table_data',
            'loans.filter',
            
            // Loan Repayments (View only)
            'loan_repayments.index',
            'loan_repayments.show',
            'loan_repayments.get_table_data',
            
            // Savings Accounts (View only)
            'savings_accounts.index',
            'savings_accounts.show',
            'savings_accounts.get_table_data',
            
            // Transactions (View only)
            'transactions.index',
            'transactions.show',
            'transactions.get_table_data',
            
            // Deposit Requests (View only)
            'deposit_requests.index',
            'deposit_requests.show',
            'deposit_requests.get_table_data',
            
            // Withdraw Requests (View only)
            'withdraw_requests.index',
            'withdraw_requests.show',
            'withdraw_requests.get_table_data',
            
            // Reports (View only)
            'reports.index',
            'reports.members',
            'reports.loans',
            'reports.savings',
            'reports.transactions',
        ];
    }
    
    /**
     * Get VSLA User permissions (VSLA module access when enabled)
     */
    private function getVslaUserPermissions()
    {
        return [
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
}
