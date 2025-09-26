<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SecurityService
{
    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, array $data = [])
    {
        Log::channel('security')->warning($event, array_merge([
            'timestamp' => now()->toISOString(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'user_id' => auth()->id(),
        ], $data));
    }

    /**
     * Check for SQL injection patterns
     */
    public function detectSQLInjection(string $input): bool
    {
        $patterns = [
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
            '/\';\s*drop/i',
            '/\'\s*or\s*\'/i',
            '/\'\s*and\s*\'/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for XSS patterns
     */
    public function detectXSS(string $input): bool
    {
        $patterns = [
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/onclick=/i',
            '/onmouseover=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit(string $key, int $maxAttempts, int $windowMinutes = 1): bool
    {
        $cacheKey = "rate_limit_{$key}_" . request()->ip();
        $attempts = Cache::get($cacheKey, 0);

        if ($attempts >= $maxAttempts) {
            $this->logSecurityEvent('Rate limit exceeded', [
                'key' => $key,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
            ]);
            return false;
        }

        Cache::put($cacheKey, $attempts + 1, $windowMinutes * 60);
        return true;
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload($file): array
    {
        $errors = [];
        
        if (!$file) {
            return ['File not provided'];
        }

        // Check file size
        $maxSize = config('security.input_validation.max_file_size', 8388608);
        if ($file->getSize() > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size';
        }

        // Check file type
        $allowedTypes = config('security.input_validation.allowed_file_types', ['jpg', 'jpeg', 'png', 'pdf']);
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }

        // Check for suspicious content
        $content = file_get_contents($file->getPathname());
        if ($this->detectSQLInjection($content) || $this->detectXSS($content)) {
            $errors[] = 'File contains suspicious content';
            $this->logSecurityEvent('Suspicious file upload detected', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
            ]);
        }

        return $errors;
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput($data)
    {
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace(chr(0), '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // Check for suspicious patterns
            if ($this->detectSQLInjection($data) || $this->detectXSS($data)) {
                $this->logSecurityEvent('Suspicious input detected', [
                    'input' => substr($data, 0, 100), // Log first 100 chars only
                ]);
                throw new \InvalidArgumentException('Invalid input detected');
            }
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeInput($value);
            }
        }

        return $data;
    }

    /**
     * Check tenant isolation
     */
    public function validateTenantAccess($tenantId, $userId): bool
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user || $user->tenant_id !== $tenantId) {
            $this->logSecurityEvent('Cross-tenant access attempt', [
                'user_id' => $userId,
                'user_tenant_id' => $user->tenant_id ?? 'null',
                'requested_tenant_id' => $tenantId,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Generate secure random string
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Check if user can bypass global scopes
     */
    public function canBypassGlobalScopes($userId): bool
    {
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            return false;
        }

        $allowedRoles = config('security.global_scope_protection.allowed_roles', ['superadmin']);
        
        if (in_array($user->user_type, $allowedRoles)) {
            $this->logSecurityEvent('Global scope bypass authorized', [
                'user_id' => $userId,
                'user_type' => $user->user_type,
            ]);
            return true;
        }

        $this->logSecurityEvent('Unauthorized global scope bypass attempt', [
            'user_id' => $userId,
            'user_type' => $user->user_type,
        ]);
        
        return false;
    }
}
