<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantSetupService
{
    /**
     * Complete setup for a new tenant
     *
     * @param Tenant $tenant
     * @param User $owner
     * @return bool
     */
    public function setupNewTenant(Tenant $tenant, User $owner): bool
    {
        try {
            DB::beginTransaction();
            
            // 1. Create default roles
            $this->createDefaultRoles($tenant);
            
            // 2. Assign Admin role to tenant owner
            $this->assignAdminRoleToOwner($tenant, $owner);
            
            // 3. Create default settings
            $this->createDefaultSettings($tenant);
            
            // 4. Create default branches if needed
            $this->createDefaultBranch($tenant);
            
            // 5. Set up default currencies
            $this->setupDefaultCurrencies($tenant);
            
            DB::commit();
            
            Log::info("Tenant setup completed successfully for tenant: {$tenant->name} (ID: {$tenant->id})");
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Tenant setup failed for tenant: {$tenant->name} (ID: {$tenant->id}). Error: " . $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Create default roles for tenant
     *
     * @param Tenant $tenant
     * @return void
     */
    private function createDefaultRoles(Tenant $tenant): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Tenant Administrator with full access to all features',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
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
                'name' => 'Agent',
                'description' => 'Loan Agent role with access to loan management features',
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
            [
                'name' => 'VSLA User',
                'description' => 'VSLA User role with access to VSLA module features when VSLA is enabled',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('roles')->insert($roles);
        
        // Assign permissions to each role
        $createdRoles = DB::table('roles')->where('tenant_id', $tenant->id)->get();
        
        foreach ($createdRoles as $role) {
            $this->assignRolePermissions($role);
        }
    }
    
    /**
     * Assign permissions to a role
     *
     * @param object $role
     * @return void
     */
    private function assignRolePermissions($role): void
    {
        $permissions = $this->getRolePermissions($role->name);
        
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
     * Get permissions for a specific role
     *
     * @param string $roleName
     * @return array
     */
    private function getRolePermissions(string $roleName): array
    {
        switch ($roleName) {
            case 'Admin':
                return $this->getAdminPermissions();
            case 'Manager':
                return $this->getManagerPermissions();
            case 'Staff':
                return $this->getStaffPermissions();
            case 'Agent':
                return $this->getAgentPermissions();
            case 'Viewer':
                return $this->getViewerPermissions();
            case 'VSLA User':
                return $this->getVslaUserPermissions();
            default:
                return [];
        }
    }
    
    /**
     * Assign Admin role to tenant owner
     *
     * @param Tenant $tenant
     * @param User $owner
     * @return void
     */
    private function assignAdminRoleToOwner(Tenant $tenant, User $owner): void
    {
        $adminRole = DB::table('roles')
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Admin')
            ->first();
        
        if ($adminRole) {
            $owner->update(['role_id' => $adminRole->id]);
        }
    }
    
    /**
     * Create default settings for tenant
     *
     * @param Tenant $tenant
     * @return void
     */
    private function createDefaultSettings(Tenant $tenant): void
    {
        $defaultSettings = [
            [
                'name' => 'site_name',
                'value' => $tenant->name,
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'site_email',
                'value' => '',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'currency',
                'value' => 'KES',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'date_format',
                'value' => 'd-m-Y',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'time_format',
                'value' => 'H:i',
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('settings')->insert($defaultSettings);
    }
    
    /**
     * Create default branch for tenant
     *
     * @param Tenant $tenant
     * @return void
     */
    private function createDefaultBranch(Tenant $tenant): void
    {
        DB::table('branches')->insert([
            'name' => 'Main Branch',
            'address' => '',
            'phone' => '',
            'email' => '',
            'status' => 1,
            'tenant_id' => $tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    
    /**
     * Setup default currencies for tenant
     *
     * @param Tenant $tenant
     * @return void
     */
    private function setupDefaultCurrencies(Tenant $tenant): void
    {
        $currencies = [
            [
                'full_name' => 'Kenyan Shilling',
                'name' => 'KES',
                'exchange_rate' => 1.000000,
                'base_currency' => 1,
                'status' => 1,
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'full_name' => 'United States Dollar',
                'name' => 'USD',
                'exchange_rate' => 0.007500,
                'base_currency' => 0,
                'status' => 1,
                'tenant_id' => $tenant->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
        
        DB::table('currency')->insert($currencies);
    }
    
    /**
     * Get comprehensive Admin permissions
     *
     * @return array
     */
    private function getAdminPermissions(): array
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
        ];
    }
    
    /**
     * Get Manager permissions
     *
     * @return array
     */
    private function getManagerPermissions(): array
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
     * Get Staff permissions
     *
     * @return array
     */
    private function getStaffPermissions(): array
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
     * Get Agent permissions
     *
     * @return array
     */
    private function getAgentPermissions(): array
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
     * Get Viewer permissions
     *
     * @return array
     */
    private function getViewerPermissions(): array
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
     * Get VSLA User permissions
     *
     * @return array
     */
    private function getVslaUserPermissions(): array
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
