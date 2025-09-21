<?php

namespace App\Utilities;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BuniActivityLogger
{
    /**
     * Log Buni daily activities
     */
    public static function logActivity($activity, $data = [], $level = 'info')
    {
        $logData = [
            'timestamp' => Carbon::now()->toISOString(),
            'activity' => $activity,
            'data' => $data,
            'user_id' => auth()->id() ?? 'system',
            'tenant_id' => request()->tenant->id ?? 'unknown',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ];

        // Log to daily file
        Log::channel('daily')->{$level}('BUNI_ACTIVITY', $logData);
        
        // Also log to main log for immediate visibility
        Log::{$level}('BUNI_ACTIVITY: ' . $activity, $logData);
    }

    /**
     * Log payment initiation
     */
    public static function logPaymentInitiation($transactionId, $amount, $memberId, $status = 'started')
    {
        self::logActivity('payment_initiation', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'member_id' => $memberId,
            'status' => $status
        ]);
    }

    /**
     * Log payment completion
     */
    public static function logPaymentCompletion($transactionId, $amount, $memberId, $buniReference = null)
    {
        self::logActivity('payment_completion', [
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'member_id' => $memberId,
            'buni_reference' => $buniReference,
            'status' => 'completed'
        ]);
    }

    /**
     * Log withdrawal initiation
     */
    public static function logWithdrawalInitiation($withdrawRequestId, $amount, $mobileNumber, $memberId)
    {
        self::logActivity('withdrawal_initiation', [
            'withdraw_request_id' => $withdrawRequestId,
            'amount' => $amount,
            'mobile_number' => $mobileNumber,
            'member_id' => $memberId,
            'status' => 'started'
        ]);
    }

    /**
     * Log withdrawal completion
     */
    public static function logWithdrawalCompletion($withdrawRequestId, $amount, $mobileNumber, $memberId, $buniReference = null)
    {
        self::logActivity('withdrawal_completion', [
            'withdraw_request_id' => $withdrawRequestId,
            'amount' => $amount,
            'mobile_number' => $mobileNumber,
            'member_id' => $memberId,
            'buni_reference' => $buniReference,
            'status' => 'completed'
        ]);
    }

    /**
     * Log API errors
     */
    public static function logApiError($operation, $error, $requestData = [], $responseData = [])
    {
        self::logActivity('api_error', [
            'operation' => $operation,
            'error' => $error,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'status' => 'error'
        ], 'error');
    }

    /**
     * Log configuration issues
     */
    public static function logConfigurationError($issue, $details = [])
    {
        self::logActivity('configuration_error', [
            'issue' => $issue,
            'details' => $details,
            'status' => 'error'
        ], 'error');
    }

    /**
     * Log IPN notifications
     */
    public static function logIpnNotification($type, $data, $status = 'received')
    {
        self::logActivity('ipn_notification', [
            'type' => $type,
            'data' => $data,
            'status' => $status
        ]);
    }

    /**
     * Log daily summary
     */
    public static function logDailySummary($summary)
    {
        self::logActivity('daily_summary', $summary, 'info');
    }
}
