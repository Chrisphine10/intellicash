<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an audit event
     */
    public static function log(
        string $eventType,
        Model $auditable,
        array $oldValues = null,
        array $newValues = null,
        string $description = null,
        array $metadata = null
    ): void {
        $request = request();
        $user = Auth::user();
        
        $auditData = [
            'event_type' => $eventType,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
            'url' => $request ? $request->fullUrl() : null,
            'method' => $request ? $request->method() : null,
            'session_id' => $request ? $request->session()->getId() : null,
            'tenant_id' => $auditable->tenant_id ?? null,
            'created_at' => now(),
        ];

        // Determine user type and ID
        if ($user) {
            if (method_exists($user, 'member') && $user->member) {
                $auditData['user_type'] = 'member';
                $auditData['user_id'] = $user->member->id;
            } elseif ($user->hasRole('system_admin')) {
                $auditData['user_type'] = 'system_admin';
                $auditData['user_id'] = $user->id;
            } else {
                $auditData['user_type'] = 'user';
                $auditData['user_id'] = $user->id;
            }
        }

        AuditTrail::create($auditData);
    }

    /**
     * Log a model creation
     */
    public static function logCreated(Model $model, string $description = null): void
    {
        self::log('created', $model, null, $model->getAttributes(), $description);
    }

    /**
     * Log a model update
     */
    public static function logUpdated(Model $model, array $oldValues, string $description = null): void
    {
        self::log('updated', $model, $oldValues, $model->getChanges(), $description);
    }

    /**
     * Log a model deletion
     */
    public static function logDeleted(Model $model, string $description = null): void
    {
        self::log('deleted', $model, $model->getAttributes(), null, $description);
    }

    /**
     * Log a model view
     */
    public static function logViewed(Model $model, string $description = null): void
    {
        self::log('viewed', $model, null, null, $description);
    }

    /**
     * Log a user action
     */
    public static function logAction(string $action, string $description = null, array $metadata = null): void
    {
        $request = request();
        $user = Auth::user();
        
        $auditData = [
            'event_type' => $action,
            'auditable_type' => 'System',
            'auditable_id' => 0,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => $request ? $request->ip() : null,
            'user_agent' => $request ? $request->userAgent() : null,
            'url' => $request ? $request->fullUrl() : null,
            'method' => $request ? $request->method() : null,
            'session_id' => $request ? $request->session()->getId() : null,
            'tenant_id' => $user->tenant_id ?? null,
            'created_at' => now(),
        ];

        if ($user) {
            if (method_exists($user, 'member') && $user->member) {
                $auditData['user_type'] = 'member';
                $auditData['user_id'] = $user->member->id;
            } elseif ($user->hasRole('system_admin')) {
                $auditData['user_type'] = 'system_admin';
                $auditData['user_id'] = $user->id;
            } else {
                $auditData['user_type'] = 'user';
                $auditData['user_id'] = $user->id;
            }
        }

        AuditTrail::create($auditData);
    }

    /**
     * Log balance changes
     */
    public static function logBalanceChange(
        Model $account,
        float $oldBalance,
        float $newBalance,
        string $reason = null
    ): void {
        self::log(
            'balance_changed',
            $account,
            ['balance' => $oldBalance],
            ['balance' => $newBalance],
            $reason ?: 'Account balance changed',
            [
                'balance_change' => $newBalance - $oldBalance,
                'change_percentage' => $oldBalance > 0 ? (($newBalance - $oldBalance) / $oldBalance) * 100 : 0
            ]
        );
    }

    /**
     * Log transaction modifications
     */
    public static function logTransactionModification(
        Model $transaction,
        array $oldValues,
        string $reason = null
    ): void {
        self::log(
            'transaction_modified',
            $transaction,
            $oldValues,
            $transaction->getChanges(),
            $reason ?: 'Transaction modified',
            [
                'modification_reason' => $reason,
                'amount_changed' => isset($oldValues['amount']) && isset($transaction->amount) 
                    ? $transaction->amount - $oldValues['amount'] : null
            ]
        );
    }
}
