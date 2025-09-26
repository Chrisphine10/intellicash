<?php

namespace App\Traits;

use App\Models\AuditTrail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait AuditTrailTrait
{
    /**
     * Boot the audit trail trait
     */
    public static function bootAuditTrailTrait()
    {
        // Log when a model is created
        static::created(function ($model) {
            $model->logAuditEvent('created', null, $model->getAttributes());
        });

        // Log when a model is updated
        static::updated(function ($model) {
            $model->logAuditEvent('updated', $model->getOriginal(), $model->getChanges());
        });

        // Log when a model is deleted
        static::deleted(function ($model) {
            $model->logAuditEvent('deleted', $model->getAttributes(), null);
        });
    }

    /**
     * Log an audit event
     */
    public function logAuditEvent($eventType, $oldValues = null, $newValues = null, $description = null, $metadata = [])
    {
        // Check if audit logging is enabled for this module
        if (!$this->shouldLogAudit($eventType)) {
            return;
        }

        // Get user information
        $user = Auth::user();
        $userType = 'system';
        $userId = null;

        if ($user) {
            $userType = $user->user_type ?? 'user';
            $userId = $user->id;
        }

        // Get tenant information
        $tenant = app('tenant');
        $tenantId = $tenant ? $tenant->id : null;

        // Prepare audit data
        $auditData = [
            'event_type' => $eventType,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_type' => $userType,
            'user_id' => $userId,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
            'method' => Request::method(),
            'description' => $description ?: $this->getAuditDescription($eventType),
            'metadata' => array_merge($metadata, $this->getAuditMetadata()),
            'session_id' => session()->getId(),
            'tenant_id' => $tenantId,
            'created_at' => now(),
        ];

        // Create audit trail record
        try {
            AuditTrail::create($auditData);
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            \Log::error('Failed to create audit trail', [
                'error' => $e->getMessage(),
                'audit_data' => $auditData
            ]);
        }
    }

    /**
     * Check if audit logging should be performed for this event
     */
    protected function shouldLogAudit($eventType)
    {
        // Check if audit logging is globally enabled
        if (!config('audit.enabled', true)) {
            return false;
        }

        // Check if this model should be audited
        if (isset($this->auditExclude) && in_array($eventType, $this->auditExclude)) {
            return false;
        }

        // Check if this model should only audit specific events
        if (isset($this->auditOnly) && !in_array($eventType, $this->auditOnly)) {
            return false;
        }

        // Check module-specific audit rules
        return $this->checkModuleAuditRules($eventType);
    }

    /**
     * Check module-specific audit rules
     */
    protected function checkModuleAuditRules($eventType)
    {
        $tenant = app('tenant');
        if (!$tenant) {
            return true; // Allow audit if no tenant context
        }

        $modelClass = get_class($this);
        
        // Check module-specific rules
        switch ($modelClass) {
            case 'App\Models\VslaTransaction':
            case 'App\Models\VslaCycle':
            case 'App\Models\VslaMeeting':
                return $tenant->isVslaEnabled();
                
            case 'App\Models\Election':
            case 'App\Models\Vote':
            case 'App\Models\VotingPosition':
                // Voting module status - check if voting is enabled
                return true; // Default to true, can be configured based on tenant settings
                
            case 'App\Models\ESignatureDocument':
            case 'App\Models\ESignatureSignature':
                return $tenant->esignature_enabled ?? false;
                
            case 'App\Models\Asset':
            case 'App\Models\AssetCategory':
                return $tenant->isAssetManagementEnabled();
                
            case 'App\Models\Employee':
            case 'App\Models\PayrollPeriod':
            case 'App\Models\PayrollDeduction':
                return $tenant->isPayrollEnabled();
                
            default:
                return true; // Allow audit for core models
        }
    }

    /**
     * Get audit description for the event
     */
    protected function getAuditDescription($eventType)
    {
        $modelName = class_basename($this);
        
        switch ($eventType) {
            case 'created':
                return "Created {$modelName}";
            case 'updated':
                return "Updated {$modelName}";
            case 'deleted':
                return "Deleted {$modelName}";
            case 'viewed':
                return "Viewed {$modelName}";
            case 'exported':
                return "Exported {$modelName}";
            default:
                return ucfirst($eventType) . " {$modelName}";
        }
    }

    /**
     * Get additional metadata for audit trail
     */
    protected function getAuditMetadata()
    {
        $metadata = [];
        
        // Add model-specific metadata
        if (method_exists($this, 'getAuditMetadata')) {
            $metadata = array_merge($metadata, $this->getAuditMetadata());
        }
        
        // Add common metadata
        $metadata['model_table'] = $this->getTable();
        $metadata['model_key'] = $this->getKeyName();
        
        return $metadata;
    }

    /**
     * Manually log a custom audit event
     */
    public function logCustomAuditEvent($eventType, $description, $metadata = [])
    {
        $this->logAuditEvent($eventType, null, null, $description, $metadata);
    }

    /**
     * Get audit trail for this model
     */
    public function auditTrail()
    {
        return $this->morphMany(AuditTrail::class, 'auditable')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent audit events for this model
     */
    public function getRecentAuditEvents($limit = 10)
    {
        return $this->auditTrail()->limit($limit)->get();
    }

    /**
     * Check if model has been modified since last audit
     */
    public function hasUnauditedChanges()
    {
        $lastAudit = $this->auditTrail()->first();
        
        if (!$lastAudit) {
            return true;
        }
        
        return $this->updated_at > $lastAudit->created_at;
    }
}
