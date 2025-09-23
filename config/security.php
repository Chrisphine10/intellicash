<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security-related configuration settings for the
    | IntelliCash application to ensure maximum security compliance.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    |
    | Configure session security settings for maximum protection
    |
    */
    'session' => [
        'encrypt' => env('SESSION_ENCRYPT', true),
        'secure' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => env('SESSION_HTTP_ONLY', true),
        'same_site' => env('SESSION_SAME_SITE', 'strict'),
        'lifetime' => env('SESSION_LIFETIME', 120),
        'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    |
    | Configure CSRF protection settings
    |
    */
    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'exclude_uris' => [
            '*/callback/instamojo',
            'subscription_callback/instamojo',
            'api/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Security
    |--------------------------------------------------------------------------
    |
    | Configure password security requirements
    |
    */
    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_symbols' => env('PASSWORD_REQUIRE_SYMBOLS', true),
        'max_age_days' => env('PASSWORD_MAX_AGE_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    |
    | Configure 2FA security settings
    |
    */
    '2fa' => [
        'enabled' => env('2FA_ENABLED', true),
        'issuer' => env('2FA_ISSUER', 'IntelliCash'),
        'window' => env('2FA_WINDOW', 1),
        'recovery_codes_count' => env('2FA_RECOVERY_CODES_COUNT', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for security
    |
    */
    'rate_limiting' => [
        'login_attempts' => env('RATE_LIMIT_LOGIN_ATTEMPTS', 5),
        'login_decay_minutes' => env('RATE_LIMIT_LOGIN_DECAY', 15),
        'api_requests' => env('RATE_LIMIT_API_REQUESTS', 60),
        'api_decay_minutes' => env('RATE_LIMIT_API_DECAY', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    |
    | Configure file upload security settings
    |
    */
    'file_upload' => [
        'max_size' => env('FILE_UPLOAD_MAX_SIZE', 10240), // KB
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
        ],
        'scan_uploads' => env('FILE_UPLOAD_SCAN', true),
        'quarantine_suspicious' => env('FILE_UPLOAD_QUARANTINE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    |
    | Configure encryption settings
    |
    */
    'encryption' => [
        'key_length' => env('ENCRYPTION_KEY_LENGTH', 32),
        'cipher' => env('ENCRYPTION_CIPHER', 'AES-256-CBC'),
        'hash_algorithm' => env('HASH_ALGORITHM', 'sha256'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    |
    | Configure security headers
    |
    */
    'headers' => [
        'x_frame_options' => env('X_FRAME_OPTIONS', 'DENY'),
        'x_content_type_options' => env('X_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'x_xss_protection' => env('X_XSS_PROTECTION', '1; mode=block'),
        'referrer_policy' => env('REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'content_security_policy' => env('CONTENT_SECURITY_POLICY', "default-src 'self'"),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Configure audit logging settings
    |
    */
    'audit' => [
        'enabled' => env('AUDIT_ENABLED', true),
        'log_level' => env('AUDIT_LOG_LEVEL', 'info'),
        'retention_days' => env('AUDIT_RETENTION_DAYS', 365),
        'log_failed_attempts' => env('AUDIT_LOG_FAILED_ATTEMPTS', true),
        'log_successful_logins' => env('AUDIT_LOG_SUCCESSFUL_LOGINS', true),
        'log_admin_actions' => env('AUDIT_LOG_ADMIN_ACTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Security
    |--------------------------------------------------------------------------
    |
    | Configure IP-based security settings
    |
    */
    'ip_security' => [
        'whitelist' => explode(',', env('IP_WHITELIST', '')),
        'blacklist' => explode(',', env('IP_BLACKLIST', '')),
        'geo_blocking' => env('IP_GEO_BLOCKING', false),
        'allowed_countries' => explode(',', env('IP_ALLOWED_COUNTRIES', '')),
        'blocked_countries' => explode(',', env('IP_BLOCKED_COUNTRIES', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Security
    |--------------------------------------------------------------------------
    |
    | Configure database security settings
    |
    */
    'database' => [
        'encrypt_connections' => env('DB_ENCRYPT_CONNECTIONS', true),
        'ssl_verify' => env('DB_SSL_VERIFY', true),
        'query_logging' => env('DB_QUERY_LOGGING', false),
        'slow_query_threshold' => env('DB_SLOW_QUERY_THRESHOLD', 2000), // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    |
    | Configure API security settings
    |
    */
    'api' => [
        'rate_limit' => env('API_RATE_LIMIT', 60),
        'throttle_requests' => env('API_THROTTLE_REQUESTS', 100),
        'require_https' => env('API_REQUIRE_HTTPS', true),
        'cors_enabled' => env('API_CORS_ENABLED', true),
        'cors_origins' => explode(',', env('API_CORS_ORIGINS', '*')),
    ],
];