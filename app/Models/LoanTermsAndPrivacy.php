<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanTermsAndPrivacy extends Model
{
    use MultiTenant;

    protected $table = 'loan_terms_and_privacy';

    protected $fillable = [
        'tenant_id',
        'loan_product_id',
        'title',
        'terms_and_conditions',
        'privacy_policy',
        'is_active',
        'is_default',
        'version',
        'effective_date',
        'expiry_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'effective_date' => 'datetime',
        'expiry_date' => 'datetime',
    ];

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the loan product
     */
    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class);
    }

    /**
     * Get the creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the updater
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope for active terms
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default terms
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope for general terms (not product-specific)
     */
    public function scopeGeneral($query)
    {
        return $query->whereNull('loan_product_id');
    }

    /**
     * Scope for product-specific terms
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('loan_product_id', $productId);
    }

    /**
     * Get the latest version for a tenant and product
     */
    public static function getLatestForTenantAndProduct($tenantId, $productId = null)
    {
        return static::where('tenant_id', $tenantId)
            ->where('loan_product_id', $productId)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Get default terms for a tenant
     */
    public static function getDefaultForTenant($tenantId)
    {
        return static::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->whereNull('loan_product_id')
            ->first();
    }

    /**
     * Check if terms are currently effective
     */
    public function isCurrentlyEffective()
    {
        $now = now();
        
        if ($this->effective_date && $now->lt($this->effective_date)) {
            return false;
        }
        
        if ($this->expiry_date && $now->gt($this->expiry_date)) {
            return false;
        }
        
        return true;
    }

    /**
     * Get formatted version
     */
    public function getFormattedVersionAttribute()
    {
        return "v{$this->version}";
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        if (!$this->isCurrentlyEffective()) {
            return 'Expired';
        }
        
        return 'Active';
    }
}
