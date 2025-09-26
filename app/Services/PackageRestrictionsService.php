<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class PackageRestrictionsService
{
    protected $tenant;
    protected $package;

    public function __construct(Tenant $tenant = null)
    {
        $this->tenant = $tenant ?? app('tenant');
        $this->package = $this->tenant->package;
    }

    /**
     * Check if tenant can create more users
     */
    public function canCreateUser(): bool
    {
        return $this->checkLimit('users', 'user_limit');
    }

    /**
     * Check if tenant can create more members
     */
    public function canCreateMember(): bool
    {
        return $this->checkLimit('members', 'member_limit');
    }

    /**
     * Check if tenant can create more branches
     */
    public function canCreateBranch(): bool
    {
        return $this->checkLimit('branches', 'branch_limit');
    }

    /**
     * Check if tenant can create more accounts
     */
    public function canCreateAccount(): bool
    {
        return $this->checkLimit('savings_accounts', 'account_limit');
    }

    /**
     * Check if tenant can create more account types (savings/loan products)
     */
    public function canCreateAccountType(): bool
    {
        return $this->checkLimit('savings_products', 'account_type_limit') && 
               $this->checkLimit('loan_products', 'account_type_limit');
    }

    /**
     * Check if tenant can create more assets
     */
    public function canCreateAsset(): bool
    {
        return $this->checkLimit('assets', 'asset_limit');
    }

    /**
     * Check if tenant can create more loans
     */
    public function canCreateLoan(): bool
    {
        return $this->checkLimit('loans', 'loan_limit');
    }

    /**
     * Check if tenant can create more VSLA cycles
     */
    public function canCreateVslaCycle(): bool
    {
        return $this->checkLimit('vsla_cycles', 'account_limit');
    }

    /**
     * Check if tenant can create more elections
     */
    public function canCreateElection(): bool
    {
        return $this->checkLimit('elections', 'election_limit');
    }

    /**
     * Check if tenant can create more employees
     */
    public function canCreateEmployee(): bool
    {
        return $this->checkLimit('employees', 'employee_limit');
    }

    /**
     * Check if tenant can create more payroll periods
     */
    public function canCreatePayrollPeriod(): bool
    {
        return $this->checkLimit('payroll_periods', 'account_limit');
    }

    /**
     * Generic limit checking method
     */
    public function checkLimit(string $table, string $packageColumn): bool
    {
        if (!$this->package) {
            return false;
        }

        $packageLimit = $this->package->{$packageColumn};

        // Unlimited
        if ($packageLimit == '-1') {
            return true;
        }

        // Count current records
        $currentCount = DB::table($table)
            ->where('tenant_id', $this->tenant->id)
            ->count();

        return $currentCount < $packageLimit;
    }

    /**
     * Get remaining limit for a resource
     */
    public function getRemainingLimit(string $table, string $packageColumn): int
    {
        if (!$this->package) {
            return 0;
        }

        $packageLimit = $this->package->{$packageColumn};

        // Unlimited
        if ($packageLimit == '-1') {
            return 999;
        }

        // Count current records
        $currentCount = DB::table($table)
            ->where('tenant_id', $this->tenant->id)
            ->count();

        return max(0, $packageLimit - $currentCount);
    }

    /**
     * Get limit information for a resource
     */
    public function getLimitInfo(string $table, string $packageColumn): array
    {
        if (!$this->package) {
            return [
                'current' => 0,
                'limit' => 0,
                'remaining' => 0,
                'unlimited' => false,
                'can_create' => false
            ];
        }

        $packageLimit = $this->package->{$packageColumn};
        $currentCount = DB::table($table)
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $unlimited = $packageLimit == '-1';
        $remaining = $unlimited ? 999 : max(0, $packageLimit - $currentCount);

        return [
            'current' => $currentCount,
            'limit' => $unlimited ? 'Unlimited' : $packageLimit,
            'remaining' => $remaining,
            'unlimited' => $unlimited,
            'can_create' => $unlimited || $currentCount < $packageLimit
        ];
    }

    /**
     * Check if package supports a specific feature
     */
    public function supportsFeature(string $feature): bool
    {
        if (!$this->package) {
            return false;
        }

        return $this->package->supportsFeature($feature);
    }

    /**
     * Check if package supports member portal
     */
    public function supportsMemberPortal(): bool
    {
        if (!$this->package) {
            return false;
        }

        return $this->package->supportsMemberPortal();
    }

    /**
     * Get all package restrictions summary
     */
    public function getRestrictionsSummary(): array
    {
        return [
            'users' => $this->getLimitInfo('users', 'user_limit'),
            'members' => $this->getLimitInfo('members', 'member_limit'),
            'branches' => $this->getLimitInfo('branches', 'branch_limit'),
            'accounts' => $this->getLimitInfo('savings_accounts', 'account_limit'),
            'account_types' => $this->getLimitInfo('savings_products', 'account_type_limit'),
            'loans' => $this->getLimitInfo('loans', 'loan_limit'),
            'assets' => $this->getLimitInfo('assets', 'asset_limit'),
            'elections' => $this->getLimitInfo('elections', 'election_limit'),
            'employees' => $this->getLimitInfo('employees', 'employee_limit'),
            'member_portal' => $this->supportsMemberPortal(),
            'vsla_enabled' => $this->supportsFeature('vsla_enabled'),
            'asset_management_enabled' => $this->supportsFeature('asset_management_enabled'),
            'payroll_enabled' => $this->supportsFeature('payroll_enabled'),
            'voting_enabled' => $this->supportsFeature('voting_enabled'),
            'api_enabled' => $this->supportsFeature('api_enabled'),
            'qr_code_enabled' => $this->supportsFeature('qr_code_enabled'),
            'esignature_enabled' => $this->supportsFeature('esignature_enabled'),
            'package_name' => $this->package->name ?? 'No Package',
            'package_type' => $this->package->package_type ?? 'N/A',
        ];
    }

    /**
     * Check if tenant has reached any critical limits
     */
    public function hasReachedCriticalLimits(): bool
    {
        $criticalChecks = [
            $this->canCreateUser(),
            $this->canCreateMember(),
            $this->canCreateAccount(),
        ];

        // If any critical limit is reached, return true
        return !collect($criticalChecks)->every(fn($check) => $check);
    }

    /**
     * Get upgrade recommendations based on current usage
     */
    public function getUpgradeRecommendations(): array
    {
        $recommendations = [];
        $summary = $this->getRestrictionsSummary();

        foreach ($summary as $resource => $info) {
            if ($resource === 'member_portal' || $resource === 'package_name' || $resource === 'package_type') {
                continue;
            }

            if (!$info['unlimited'] && $info['remaining'] <= 2) {
                $recommendations[] = [
                    'resource' => ucfirst(str_replace('_', ' ', $resource)),
                    'current' => $info['current'],
                    'limit' => $info['limit'],
                    'remaining' => $info['remaining'],
                    'message' => "You're running low on {$resource}. Consider upgrading your package."
                ];
            }
        }

        return $recommendations;
    }
}
