<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SeedRolesForTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:roles-for-tenants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default roles for all existing tenants';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting to seed roles for all tenants...');

        $tenants = Tenant::all();
        
        if ($tenants->isEmpty()) {
            $this->warn('No tenants found in the database.');
            return 0;
        }

        $this->info("Found {$tenants->count()} tenants to process.");

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} (ID: {$tenant->id})");
            
            // Check if roles already exist for this tenant
            $existingRoles = DB::table('roles')->where('tenant_id', $tenant->id)->count();
            
            if ($existingRoles > 0) {
                $this->warn("  - Roles already exist for tenant {$tenant->name}. Deleting existing roles and creating new ones...");
                DB::table('roles')->where('tenant_id', $tenant->id)->delete();
            }

            // Create default roles for the tenant
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
            ];

            DB::table('roles')->insert($roles);
            $this->info("  - Created 5 default roles for tenant {$tenant->name}");
            
            // Get the created roles to assign permissions
            $createdRoles = DB::table('roles')->where('tenant_id', $tenant->id)->get();
            
            // Assign default permissions to each role
            foreach ($createdRoles as $role) {
                $this->assignDefaultPermissions($role, $tenant->id);
            }
        }

        $this->info('Role seeding completed successfully!');
        return 0;
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
        
        $this->info("    - Assigned " . count($permissions) . " permissions to {$role->name} role");
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
}