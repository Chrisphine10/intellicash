<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AuditService
{
    /**
     * Log report access
     */
    public static function logReportAccess(string $reportType, array $parameters = [])
    {
        Log::info('Report accessed', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email ?? 'unknown',
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'report_type' => $reportType,
            'parameters' => $parameters,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log report export
     */
    public static function logReportExport(string $reportType, string $format, array $parameters = [])
    {
        Log::info('Report exported', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email ?? 'unknown',
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'report_type' => $reportType,
            'export_format' => $format,
            'parameters' => $parameters,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent(string $event, array $context = [])
    {
        Log::warning('Security event detected', array_merge([
            'user_id' => Auth::id(),
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ], $context));
    }
    
    /**
     * Log failed authentication attempt
     */
    public static function logFailedAuth(string $email, string $reason = 'Invalid credentials')
    {
        Log::warning('Failed authentication attempt', [
            'email' => $email,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log permission denied
     */
    public static function logPermissionDenied(string $action, string $resource = '')
    {
        Log::warning('Permission denied', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email ?? 'unknown',
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'action' => $action,
            'resource' => $resource,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log data modification
     */
    public static function logDataModification(string $table, string $action, array $oldData = [], array $newData = [])
    {
        Log::info('Data modification', [
            'user_id' => Auth::id(),
            'user_email' => Auth::user()->email ?? 'unknown',
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'table' => $table,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Log system error
     */
    public static function logSystemError(string $error, string $context = '', array $additionalData = [])
    {
        Log::error('System error', array_merge([
            'user_id' => Auth::id(),
            'tenant_id' => app('tenant')->id ?? 'unknown',
            'error' => $error,
            'context' => $context,
            'ip_address' => request()->ip(),
            'timestamp' => now()->toISOString()
        ], $additionalData));
    }
}