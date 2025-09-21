<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Military-Grade Security Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration implements banking-level security standards
    | and military-grade protection protocols for the IntelliCash system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'content_security_policy' => [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            'font-src' => "'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            'img-src' => "'self' data: https:",
            'connect-src' => "'self' https:",
            'frame-ancestors' => "'none'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'object-src' => "'none'",
            'media-src' => "'self'",
            'manifest-src' => "'self'",
        ],
        
        'hsts' => [
            'max_age' => 31536000, // 1 year
            'include_subdomains' => true,
            'preload' => true,
        ],
        
        'x_frame_options' => 'DENY',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(), microphone=(), camera=()',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'global' => [
            'max_attempts' => 1000,
            'decay_minutes' => 60,
        ],
        
        'per_ip' => [
            'max_attempts' => 100,
            'decay_minutes' => 60,
        ],
        
        'per_user' => [
            'max_attempts' => 200,
            'decay_minutes' => 60,
        ],
        
        'endpoints' => [
            'login' => ['max_attempts' => 5, 'decay_minutes' => 60],
            'password_reset' => ['max_attempts' => 3, 'decay_minutes' => 60],
            'api' => ['max_attempts' => 50, 'decay_minutes' => 60],
            'admin' => ['max_attempts' => 30, 'decay_minutes' => 60],
            'upload' => ['max_attempts' => 10, 'decay_minutes' => 60],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    */
    'file_upload' => [
        'allowed_mime_types' => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'application/zip' => ['zip'],
        ],
        
        'max_file_sizes' => [
            'image' => 5 * 1024 * 1024,      // 5MB
            'document' => 10 * 1024 * 1024,  // 10MB
            'archive' => 20 * 1024 * 1024,   // 20MB
        ],
        
        'dangerous_extensions' => [
            'php', 'php3', 'php4', 'php5', 'phtml', 'pht',
            'asp', 'aspx', 'jsp', 'jspx',
            'exe', 'bat', 'cmd', 'com', 'scr', 'pif',
            'sh', 'bash', 'csh', 'ksh', 'zsh',
            'py', 'pl', 'rb', 'js', 'vbs', 'wsf',
            'jar', 'war', 'ear', 'class',
        ],
        
        'scan_for_malware' => true,
        'remove_exif_data' => true,
        'max_image_dimensions' => ['width' => 2048, 'height' => 2048],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */
    'session' => [
        'encrypt' => true,
        'lifetime' => 120, // 2 hours
        'expire_on_close' => true,
        'secure' => env('SESSION_SECURE_COOKIE', true),
        'http_only' => true,
        'same_site' => 'strict',
        'regenerate_on_login' => true,
        'regenerate_frequency' => 30, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Security
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'max_age_days' => 90,
        'history_count' => 5,
        'lockout_attempts' => 5,
        'lockout_duration' => 15, // minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Security
    |--------------------------------------------------------------------------
    */
    'database' => [
        'encrypt_at_rest' => true,
        'audit_all_queries' => true,
        'parameterized_queries_only' => true,
        'connection_timeout' => 30,
        'query_timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | API Security
    |--------------------------------------------------------------------------
    */
    'api' => [
        'require_https' => true,
        'rate_limit_per_minute' => 60,
        'require_authentication' => true,
        'log_all_requests' => true,
        'validate_origin' => true,
        'allowed_origins' => [
            env('APP_URL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Threat Detection
    |--------------------------------------------------------------------------
    */
    'threat_detection' => [
        'enabled' => true,
        'suspicious_patterns' => [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
            '/eval\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/proc_open/i',
            '/popen/i',
        ],
        
        'sql_injection_patterns' => [
            '/\'\s*or\s*1\s*=\s*1/i',
            '/\'\s*or\s*\'1\'\s*=\s*\'1/i',
            '/\'\s*union\s+select/i',
            '/\'\s*drop\s+table/i',
            '/\'\s*insert\s+into/i',
            '/\'\s*delete\s+from/i',
            '/\'\s*update\s+set/i',
        ],
        
        'response_time_threshold' => 5000, // milliseconds
        'concurrent_requests_threshold' => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    */
    'audit' => [
        'enabled' => true,
        'log_level' => 'info',
        'retention_days' => 365,
        'encrypt_logs' => true,
        'log_sensitive_data' => false,
        'real_time_alerts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption Configuration
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'key_rotation_days' => 90,
        'encrypt_sensitive_fields' => [
            'password',
            'api_key',
            'secret',
            'token',
            'ssn',
            'credit_card',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Security
    |--------------------------------------------------------------------------
    */
    'debug' => [
        'allowed_ips' => explode(',', env('DEBUG_ALLOWED_IPS', '127.0.0.1,::1')),
        'require_superadmin' => true,
        'rate_limit' => ['max_attempts' => 5, 'decay_minutes' => 1],
        'log_access' => true,
    ],
];
