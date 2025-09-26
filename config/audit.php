<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Trail Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for the audit trail system.
    | You can enable/disable audit logging globally and configure specific
    | settings for different modules.
    |
    */

    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Trail Settings
    |--------------------------------------------------------------------------
    |
    | Configure various aspects of the audit trail system.
    |
    */

    'settings' => [
        // Maximum number of audit records to keep per model
        'max_records_per_model' => env('AUDIT_MAX_RECORDS', 10000),
        
        // Number of days to keep audit records
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
        
        // Enable/disable specific event types
        'log_created' => env('AUDIT_LOG_CREATED', true),
        'log_updated' => env('AUDIT_LOG_UPDATED', true),
        'log_deleted' => env('AUDIT_LOG_DELETED', true),
        'log_viewed' => env('AUDIT_LOG_VIEWED', false),
        'log_exported' => env('AUDIT_LOG_EXPORTED', true),
        
        // Enable/disable specific modules
        'log_core_modules' => env('AUDIT_LOG_CORE', true),
        'log_vsla_module' => env('AUDIT_LOG_VSLA', true),
        'log_voting_module' => env('AUDIT_LOG_VOTING', true),
        'log_esignature_module' => env('AUDIT_LOG_ESIGNATURE', true),
        'log_asset_module' => env('AUDIT_LOG_ASSET', true),
        'log_payroll_module' => env('AUDIT_LOG_PAYROLL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Module-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Configure audit settings for specific modules.
    |
    */

    'modules' => [
        'vsla' => [
            'enabled' => env('AUDIT_VSLA_ENABLED', true),
            'events' => ['created', 'updated', 'deleted', 'viewed'],
            'models' => [
                'App\Models\VslaTransaction',
                'App\Models\VslaCycle',
                'App\Models\VslaMeeting',
                'App\Models\VslaMember',
            ],
        ],
        
        'voting' => [
            'enabled' => env('AUDIT_VOTING_ENABLED', true),
            'events' => ['created', 'updated', 'deleted', 'viewed', 'voted'],
            'models' => [
                'App\Models\Election',
                'App\Models\Vote',
                'App\Models\VotingPosition',
                'App\Models\VotingCandidate',
            ],
        ],
        
        'esignature' => [
            'enabled' => env('AUDIT_ESIGNATURE_ENABLED', true),
            'events' => ['created', 'updated', 'deleted', 'viewed', 'signed', 'sent'],
            'models' => [
                'App\Models\ESignatureDocument',
                'App\Models\ESignatureSignature',
            ],
        ],
        
        'asset_management' => [
            'enabled' => env('AUDIT_ASSET_ENABLED', true),
            'events' => ['created', 'updated', 'deleted', 'viewed', 'transferred'],
            'models' => [
                'App\Models\Asset',
                'App\Models\AssetCategory',
                'App\Models\AssetTransfer',
            ],
        ],
        
        'payroll' => [
            'enabled' => env('AUDIT_PAYROLL_ENABLED', true),
            'events' => ['created', 'updated', 'deleted', 'viewed', 'processed'],
            'models' => [
                'App\Models\Employee',
                'App\Models\PayrollPeriod',
                'App\Models\PayrollDeduction',
                'App\Models\PayrollBenefit',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Excluded Fields
    |--------------------------------------------------------------------------
    |
    | Fields that should not be included in audit trail logs.
    |
    */

    'excluded_fields' => [
        'password',
        'password_confirmation',
        'remember_token',
        'api_token',
        'created_at',
        'updated_at',
        'deleted_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Fields
    |--------------------------------------------------------------------------
    |
    | Fields that contain sensitive information and should be masked in logs.
    |
    */

    'sensitive_fields' => [
        'email',
        'phone',
        'address',
        'bank_account_number',
        'social_security_number',
        'credit_card_number',
    ],
];
