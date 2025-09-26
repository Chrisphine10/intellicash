<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration settings for the
    | IntelliCash application to prevent common vulnerabilities.
    |
    */

    'sql_injection_protection' => [
        'enabled' => true,
        'log_attempts' => true,
        'block_suspicious_queries' => true,
    ],

    'global_scope_protection' => [
        'enabled' => true,
        'allowed_roles' => ['superadmin', 'system_admin'],
        'log_bypass_attempts' => true,
        'require_explicit_permission' => true,
    ],

    'tenant_isolation' => [
        'enabled' => true,
        'strict_mode' => true,
        'log_cross_tenant_attempts' => true,
        'block_unauthorized_access' => true,
    ],

    'input_validation' => [
        'strict_mode' => true,
        'sanitize_all_inputs' => true,
        'validate_file_uploads' => true,
        'max_file_size' => 8388608, // 8MB
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
    ],

    'rate_limiting' => [
        'enabled' => true,
        'login_attempts' => 5,
        'api_requests' => 100,
        'file_uploads' => 10,
        'window_minutes' => 1,
    ],

    'logging' => [
        'security_events' => true,
        'failed_logins' => true,
        'privilege_escalation' => true,
        'data_access' => true,
        'retention_days' => 90,
    ],

    'headers' => [
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';",
    ],

    'encryption' => [
        'sensitive_data' => true,
        'password_hashing' => 'bcrypt',
        'session_encryption' => true,
        'cookie_encryption' => true,
    ],
];