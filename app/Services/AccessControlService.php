<?php

namespace App\Services;

use App\Models\User;
use App\Models\Role;
use App\Models\AccessControl;
use Illuminate\Support\Facades\Cache;

class AccessControlService
{
    /**
     * Check if user has permission
     *
     * @param User $user
     * @param string $permission
     * @return bool
     */
    public function hasPermission(User $user, string $permission): bool
    {
        // Super admin has access to everything
        if ($user->user_type === 'superadmin') {
            return true;
        }
        
        // Tenant admin has access to everything within their tenant
        if ($user->user_type === 'admin') {
            return true;
        }
        
        // For other users, check role-based permissions
        if (!$user->role) {
            return false;
        }
        
        return $user->role->permissions()
            ->where('permission', $permission)
            ->exists();
    }
    
    /**
     * Check if user has any of the given permissions
     *
     * @param User $user
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has all of the given permissions
     *
     * @param User $user
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($user, $permission)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if user can access tenant-specific resource
     *
     * @param User $user
     * @param int $tenantId
     * @return bool
     */
    public function canAccessTenant(User $user, int $tenantId): bool
    {
        // Super admin can access all tenants
        if ($user->user_type === 'superadmin') {
            return true;
        }
        
        // User must belong to the tenant
        return $user->tenant_id === $tenantId;
    }
    
    /**
     * Check if user is tenant owner
     *
     * @param User $user
     * @return bool
     */
    public function isTenantOwner(User $user): bool
    {
        return $user->tenant_owner === 1;
    }
    
    /**
     * Check if user is tenant admin
     *
     * @param User $user
     * @return bool
     */
    public function isTenantAdmin(User $user): bool
    {
        return $user->user_type === 'admin' || $this->isTenantOwner($user);
    }
    
    /**
     * Get user's effective permissions (cached)
     *
     * @param User $user
     * @return array
     */
    public function getUserPermissions(User $user): array
    {
        $cacheKey = "user_permissions_{$user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            // Super admin has all permissions
            if ($user->user_type === 'superadmin') {
                return ['*']; // Wildcard for all permissions
            }
            
            // Tenant admin has all permissions within tenant
            if ($user->user_type === 'admin') {
                return ['*']; // Wildcard for all permissions
            }
            
            // Get role-based permissions
            if (!$user->role) {
                return [];
            }
            
            return $user->role->permissions()
                ->pluck('permission')
                ->toArray();
        });
    }
    
    /**
     * Clear user permission cache
     *
     * @param User $user
     * @return void
     */
    public function clearUserPermissionCache(User $user): void
    {
        $cacheKey = "user_permissions_{$user->id}";
        Cache::forget($cacheKey);
    }
    
    /**
     * Check if user can manage other users
     *
     * @param User $user
     * @return bool
     */
    public function canManageUsers(User $user): bool
    {
        return $this->hasPermission($user, 'users.index') && 
               $this->hasPermission($user, 'users.create');
    }
    
    /**
     * Check if user can manage roles
     *
     * @param User $user
     * @return bool
     */
    public function canManageRoles(User $user): bool
    {
        return $this->hasPermission($user, 'roles.index') && 
               $this->hasPermission($user, 'roles.create');
    }
    
    /**
     * Check if user can access financial operations
     *
     * @param User $user
     * @return bool
     */
    public function canAccessFinancialOperations(User $user): bool
    {
        return $this->hasAnyPermission($user, [
            'loans.approve',
            'loans.disburse',
            'deposit_requests.approve',
            'withdraw_requests.approve',
            'transactions.create'
        ]);
    }
    
    /**
     * Check if user can access reports
     *
     * @param User $user
     * @return bool
     */
    public function canAccessReports(User $user): bool
    {
        return $this->hasPermission($user, 'reports.index');
    }
    
    /**
     * Check if user can access audit logs
     *
     * @param User $user
     * @return bool
     */
    public function canAccessAudit(User $user): bool
    {
        return $this->hasPermission($user, 'audit.index');
    }
    
    /**
     * Validate tenant access for API requests
     *
     * @param User $user
     * @param int $tenantId
     * @return bool
     */
    public function validateTenantAccess(User $user, int $tenantId): bool
    {
        if (!$this->canAccessTenant($user, $tenantId)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get accessible modules for user
     *
     * @param User $user
     * @return array
     */
    public function getAccessibleModules(User $user): array
    {
        $modules = [];
        
        // Core modules
        if ($this->hasPermission($user, 'members.index')) {
            $modules[] = 'members';
        }
        
        if ($this->hasPermission($user, 'loans.index')) {
            $modules[] = 'loans';
        }
        
        if ($this->hasPermission($user, 'savings_accounts.index')) {
            $modules[] = 'savings';
        }
        
        if ($this->hasPermission($user, 'transactions.index')) {
            $modules[] = 'transactions';
        }
        
        // VSLA module
        if ($this->hasPermission($user, 'vsla.meetings.index')) {
            $modules[] = 'vsla';
        }
        
        // Asset management
        if ($this->hasPermission($user, 'assets.index')) {
            $modules[] = 'assets';
        }
        
        // E-signature
        if ($this->hasPermission($user, 'esignature.documents.index')) {
            $modules[] = 'esignature';
        }
        
        // Payroll
        if ($this->hasPermission($user, 'payroll.employees.index')) {
            $modules[] = 'payroll';
        }
        
        // Voting system
        if ($this->hasPermission($user, 'voting.elections.index')) {
            $modules[] = 'voting';
        }
        
        return $modules;
    }
}
