<?php

namespace App\Models;

use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;

class VslaAuditLog extends Model
{
    use MultiTenant;

    protected $table = 'vsla_audit_logs';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log VSLA transaction changes
     */
    public static function logTransactionChange($action, $transaction, $oldValues = null, $newValues = null)
    {
        return static::create([
            'tenant_id' => $transaction->tenant_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'VslaTransaction',
            'model_id' => $transaction->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => "VSLA Transaction {$action}: {$transaction->transaction_type} - {$transaction->amount}",
        ]);
    }

    /**
     * Log VSLA cycle changes
     */
    public static function logCycleChange($action, $cycle, $oldValues = null, $newValues = null)
    {
        return static::create([
            'tenant_id' => $cycle->tenant_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'VslaCycle',
            'model_id' => $cycle->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => "VSLA Cycle {$action}: {$cycle->cycle_name}",
        ]);
    }

    /**
     * Log VSLA meeting changes
     */
    public static function logMeetingChange($action, $meeting, $oldValues = null, $newValues = null)
    {
        return static::create([
            'tenant_id' => $meeting->tenant_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => 'VslaMeeting',
            'model_id' => $meeting->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => "VSLA Meeting {$action}: {$meeting->meeting_number}",
        ]);
    }

    /**
     * Get audit trail for a specific model
     */
    public static function getAuditTrail($modelType, $modelId)
    {
        return static::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
