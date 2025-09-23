<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use MultiTenant;

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'category',
        'is_active',
        'is_public',
        'version',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'tags' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get the tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
     * Scope for active documents
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for public documents
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for specific category
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope for terms and conditions
     */
    public function scopeTermsAndConditions($query)
    {
        return $query->where('category', 'terms_and_conditions');
    }

    /**
     * Scope for privacy policies
     */
    public function scopePrivacyPolicies($query)
    {
        return $query->where('category', 'privacy_policy');
    }

    /**
     * Get the full file URL
     */
    public function getFileUrlAttribute()
    {
        return Storage::url($this->file_path);
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file exists
     */
    public function fileExists()
    {
        return Storage::exists($this->file_path);
    }

    /**
     * Get file content
     */
    public function getFileContent()
    {
        if ($this->fileExists()) {
            return Storage::get($this->file_path);
        }
        
        return null;
    }

    /**
     * Delete the file from storage
     */
    public function deleteFile()
    {
        if ($this->fileExists()) {
            Storage::delete($this->file_path);
        }
    }

    /**
     * Get category label
     */
    public function getCategoryLabelAttribute()
    {
        $labels = [
            'terms_and_conditions' => 'Terms and Conditions',
            'privacy_policy' => 'Privacy Policy',
            'loan_agreement' => 'Loan Agreement',
            'legal_document' => 'Legal Document',
            'policy' => 'Policy',
            'other' => 'Other'
        ];

        return $labels[$this->category] ?? 'Unknown';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        if (!$this->is_active) {
            return 'Inactive';
        }
        
        return 'Active';
    }

    /**
     * Get latest version for a category
     */
    public static function getLatestForCategory($tenantId, $category)
    {
        return static::where('tenant_id', $tenantId)
            ->where('category', $category)
            ->where('is_active', true)
            ->orderBy('version', 'desc')
            ->first();
    }

    /**
     * Get all versions for a category
     */
    public static function getAllVersionsForCategory($tenantId, $category)
    {
        return static::where('tenant_id', $tenantId)
            ->where('category', $category)
            ->orderBy('version', 'desc')
            ->get();
    }
}
