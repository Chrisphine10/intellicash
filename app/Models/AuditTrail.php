<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

use App\Models\Member;
use App\Models\User;

class AuditTrail extends Model
{
    use MultiTenant;

    protected $table = 'audit_trails';
    
    // Disable timestamps since we only use created_at
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'user_type',
        'user_id',
        'ip_address',
        'user_agent',
        'url',
        'method',
        'description',
        'metadata',
        'session_id',
        'tenant_id',
        'created_at'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];


    /**
     * Get the auditable model (polymorphic relationship)
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who performed the action
     */
    public function user(): BelongsTo
    {
        if ($this->user_type === 'member') {
            return $this->belongsTo(Member::class, 'user_id');
        } elseif ($this->user_type === 'system_admin') {
            return $this->belongsTo(User::class, 'user_id');
        }
        
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the tenant this audit belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope for filtering by event type
     */
    public function scopeEventType($query, $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope for filtering by user type
     */
    public function scopeUserType($query, $userType)
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope for filtering by auditable model
     */
    public function scopeAuditableType($query, $auditableType)
    {
        return $query->where('auditable_type', $auditableType);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted event description
     */
    public function getFormattedDescriptionAttribute(): string
    {
        $modelName = class_basename($this->auditable_type);
        $event = ucfirst(str_replace('_', ' ', $this->event_type));
        
        return "{$event} {$modelName}";
    }

    /**
     * Get changes summary
     */
    public function getChangesSummaryAttribute(): array
    {
        try {
            // Handle NULL values and ensure arrays
            $oldValues = $this->old_values;
            $newValues = $this->new_values;
            
            // Convert NULL to empty array
            if ($oldValues === null) {
                $oldValues = [];
            }
            if ($newValues === null) {
                $newValues = [];
            }
            
            // Ensure they are arrays
            if (!is_array($oldValues)) {
                $oldValues = [];
            }
            if (!is_array($newValues)) {
                $newValues = [];
            }
            
            // If both are empty, no changes
            if (empty($oldValues) && empty($newValues)) {
                return [];
            }

            $changes = [];
            
            // Compare old and new values
            $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
            
            foreach ($allKeys as $key) {
                $oldValue = $oldValues[$key] ?? null;
                $newValue = $newValues[$key] ?? null;
                
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue
                    ];
                }
            }

            return $changes;
        } catch (\Exception $e) {
            \Log::warning('Error in getChangesSummaryAttribute', [
                'audit_id' => $this->id,
                'error' => $e->getMessage(),
                'old_values_type' => gettype($this->old_values),
                'new_values_type' => gettype($this->new_values)
            ]);
            return [];
        }
    }

    /**
     * Get user name for display
     */
    public function getUserNameAttribute(): ?string
    {
        if (!$this->user_id) {
            return 'System';
        }

        if ($this->user_type === 'member') {
            return $this->user?->first_name . ' ' . $this->user?->last_name;
        }

        return $this->user?->name;
    }

    /**
     * Scope for filtering by tenant
     */
    public function scopeTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope for recent events
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for specific user
     */
    public function scopeByUser($query, $userId, $userType = null)
    {
        $query = $query->where('user_id', $userId);
        
        if ($userType) {
            $query = $query->where('user_type', $userType);
        }
        
        return $query;
    }

    /**
     * Get audit trail for a specific model
     */
    public static function getModelAuditTrail($modelType, $modelId, $limit = 50)
    {
        return static::where('auditable_type', $modelType)
            ->where('auditable_id', $modelId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit statistics for a tenant
     */
    public static function getTenantStatistics($tenantId, $startDate = null, $endDate = null)
    {
        $query = static::where('tenant_id', $tenantId);
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        return [
            'total_events' => $query->count(),
            'events_by_type' => $query->groupBy('event_type')
                ->selectRaw('event_type, count(*) as count')
                ->pluck('count', 'event_type'),
            'events_by_user_type' => $query->groupBy('user_type')
                ->selectRaw('user_type, count(*) as count')
                ->pluck('count', 'user_type'),
            'events_by_model' => $query->groupBy('auditable_type')
                ->selectRaw('auditable_type, count(*) as count')
                ->pluck('count', 'auditable_type'),
            'recent_activity' => static::where('tenant_id', $tenantId)
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];
    }

    /**
     * Clean up old audit records
     */
    public static function cleanupOldRecords($days = 365)
    {
        $cutoffDate = now()->subDays($days);
        
        return static::where('created_at', '<', $cutoffDate)->delete();
    }

    /**
     * Get module-specific audit events
     */
    public static function getModuleAuditEvents($module, $tenantId, $limit = 100)
    {
        $moduleModels = config("audit.modules.{$module}.models", []);
        
        if (empty($moduleModels)) {
            return collect();
        }
        
        return static::where('tenant_id', $tenantId)
            ->whereIn('auditable_type', $moduleModels)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if audit logging is enabled for a module
     */
    public static function isModuleAuditEnabled($module)
    {
        return config("audit.modules.{$module}.enabled", true) && 
               config("audit.settings.log_{$module}_module", true);
    }
}
