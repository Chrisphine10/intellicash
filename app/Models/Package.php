<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model {
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'packages';

    protected $fillable = [
        'name',
        'package_type',
        'cost',
        'status',
        'is_popular',
        'discount',
        'trial_days',
        'user_limit',
        'member_limit',
        'branch_limit',
        'account_type_limit',
        'account_limit',
        'loan_limit',
        'asset_limit',
        'election_limit',
        'employee_limit',
        'member_portal',
        'vsla_enabled',
        'asset_management_enabled',
        'payroll_enabled',
        'voting_enabled',
        'api_enabled',
        'qr_code_enabled',
        'esignature_enabled',
        'storage_limit_mb',
        'file_upload_limit_mb',
        'priority_support',
        'custom_branding',
        'others'
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'status' => 'boolean',
        'is_popular' => 'boolean',
        'trial_days' => 'integer',
        'member_portal' => 'boolean',
        'vsla_enabled' => 'boolean',
        'asset_management_enabled' => 'boolean',
        'payroll_enabled' => 'boolean',
        'voting_enabled' => 'boolean',
        'api_enabled' => 'boolean',
        'qr_code_enabled' => 'boolean',
        'esignature_enabled' => 'boolean',
        'storage_limit_mb' => 'integer',
        'file_upload_limit_mb' => 'integer',
        'priority_support' => 'boolean',
        'custom_branding' => 'boolean',
    ];

    public function scopeActive($query) {
        return $query->where('status', 1);
    }

    public function scopeByType($query, $type) {
        return $query->where('package_type', $type);
    }

    public function scopePopular($query) {
        return $query->where('is_popular', 1);
    }

    /**
     * Get tenants using this package
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Check if package has unlimited users
     */
    public function hasUnlimitedUsers(): bool
    {
        return $this->user_limit == '-1';
    }

    /**
     * Check if package has unlimited members
     */
    public function hasUnlimitedMembers(): bool
    {
        return $this->member_limit == '-1';
    }

    /**
     * Check if package has unlimited branches
     */
    public function hasUnlimitedBranches(): bool
    {
        return $this->branch_limit == '-1';
    }

    /**
     * Check if package has unlimited accounts
     */
    public function hasUnlimitedAccounts(): bool
    {
        return $this->account_limit == '-1';
    }

    /**
     * Check if package has unlimited account types
     */
    public function hasUnlimitedAccountTypes(): bool
    {
        return $this->account_type_limit == '-1';
    }

    /**
     * Get the effective cost after discount
     */
    public function getEffectiveCost(): float
    {
        if ($this->discount > 0) {
            return $this->cost - ($this->discount / 100) * $this->cost;
        }
        return $this->cost;
    }

    /**
     * Check if package supports member portal
     */
    public function supportsMemberPortal(): bool
    {
        return $this->member_portal == 1;
    }

    /**
     * Check if package has trial period
     */
    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    /**
     * Get package features as array
     */
    public function getFeatures(): array
    {
        return [
            'users' => $this->user_limit,
            'members' => $this->member_limit,
            'branches' => $this->branch_limit,
            'account_types' => $this->account_type_limit,
            'accounts' => $this->account_limit,
            'member_portal' => $this->member_portal,
            'trial_days' => $this->trial_days,
        ];
    }

    /**
     * Check if package supports a specific feature
     */
    public function supportsFeature(string $feature): bool
    {
        $features = $this->getFeatures();
        return isset($features[$feature]) && $features[$feature] > 0;
    }

    /**
     * Check if package supports VSLA module
     */
    public function supportsVsla(): bool
    {
        return $this->vsla_enabled == 1;
    }

    /**
     * Check if package supports Asset Management module
     */
    public function supportsAssetManagement(): bool
    {
        return $this->asset_management_enabled == 1;
    }

    /**
     * Check if package supports Payroll module
     */
    public function supportsPayroll(): bool
    {
        return $this->payroll_enabled == 1;
    }

    /**
     * Check if package supports Voting module
     */
    public function supportsVoting(): bool
    {
        return $this->voting_enabled == 1;
    }

    /**
     * Check if package supports API access
     */
    public function supportsApi(): bool
    {
        return $this->api_enabled == 1;
    }

    /**
     * Check if package supports QR Code generation
     */
    public function supportsQrCode(): bool
    {
        return $this->qr_code_enabled == 1;
    }

    /**
     * Check if package supports E-Signature
     */
    public function supportsEsignature(): bool
    {
        return $this->esignature_enabled == 1;
    }

    /**
     * Check if package has priority support
     */
    public function hasPrioritySupport(): bool
    {
        return $this->priority_support == 1;
    }

    /**
     * Check if package allows custom branding
     */
    public function allowsCustomBranding(): bool
    {
        return $this->custom_branding == 1;
    }

    /**
     * Get storage limit in MB
     */
    public function getStorageLimit(): int
    {
        return $this->storage_limit_mb ?? 100;
    }

    /**
     * Get file upload limit in MB
     */
    public function getFileUploadLimit(): int
    {
        return $this->file_upload_limit_mb ?? 10;
    }

    /**
     * Get all advanced features as array
     */
    public function getAdvancedFeatures(): array
    {
        return [
            'vsla_enabled' => $this->vsla_enabled,
            'asset_management_enabled' => $this->asset_management_enabled,
            'payroll_enabled' => $this->payroll_enabled,
            'voting_enabled' => $this->voting_enabled,
            'api_enabled' => $this->api_enabled,
            'qr_code_enabled' => $this->qr_code_enabled,
            'esignature_enabled' => $this->esignature_enabled,
            'priority_support' => $this->priority_support,
            'custom_branding' => $this->custom_branding,
            'storage_limit_mb' => $this->storage_limit_mb,
            'file_upload_limit_mb' => $this->file_upload_limit_mb,
        ];
    }

    /**
     * Get comprehensive package features including advanced features
     */
    public function getAllFeatures(): array
    {
        return array_merge($this->getFeatures(), $this->getAdvancedFeatures());
    }
}