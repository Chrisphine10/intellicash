<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

class CryptographicProtectionService
{
    private const ENCRYPTION_ALGORITHM = 'AES-256-GCM';
    private const KEY_LENGTH = 32; // 256 bits
    private const IV_LENGTH = 12;  // 96 bits for GCM
    private const TAG_LENGTH = 16; // 128 bits for GCM

    /**
     * Encrypt sensitive data with military-grade encryption
     */
    public function encrypt(string $data, string $key = null): array
    {
        try {
            $key = $key ?: $this->getOrCreateEncryptionKey();
            $iv = random_bytes(self::IV_LENGTH);
            
            $encrypted = openssl_encrypt(
                $data,
                self::ENCRYPTION_ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            return [
                'data' => base64_encode($encrypted),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'algorithm' => self::ENCRYPTION_ALGORITHM,
            ];
            
        } catch (Exception $e) {
            Log::error('Encryption failed', [
                'error' => $e->getMessage(),
                'data_length' => strlen($data)
            ]);
            throw $e;
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(array $encryptedData, string $key = null): string
    {
        try {
            $key = $key ?: $this->getOrCreateEncryptionKey();
            
            $data = base64_decode($encryptedData['data']);
            $iv = base64_decode($encryptedData['iv']);
            $tag = base64_decode($encryptedData['tag']);
            
            $decrypted = openssl_decrypt(
                $data,
                self::ENCRYPTION_ALGORITHM,
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            Log::error('Decryption failed', [
                'error' => $e->getMessage(),
                'encrypted_data' => $encryptedData
            ]);
            throw $e;
        }
    }

    /**
     * Hash sensitive data with salt
     */
    public function hash(string $data, string $salt = null): array
    {
        $salt = $salt ?: $this->generateSalt();
        $hash = hash('sha256', $data . $salt);
        
        return [
            'hash' => $hash,
            'salt' => $salt,
            'algorithm' => 'sha256',
        ];
    }

    /**
     * Verify hashed data
     */
    public function verifyHash(string $data, string $hash, string $salt): bool
    {
        $computedHash = hash('sha256', $data . $salt);
        return hash_equals($hash, $computedHash);
    }

    /**
     * Generate secure random key
     */
    public function generateKey(): string
    {
        return base64_encode(random_bytes(self::KEY_LENGTH));
    }

    /**
     * Generate secure salt
     */
    public function generateSalt(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Encrypt database field
     */
    public function encryptField(string $value): string
    {
        if (empty($value)) {
            return $value;
        }
        
        $encrypted = $this->encrypt($value);
        return json_encode($encrypted);
    }

    /**
     * Decrypt database field
     */
    public function decryptField(string $encryptedValue): string
    {
        if (empty($encryptedValue)) {
            return $encryptedValue;
        }
        
        try {
            $encryptedData = json_decode($encryptedValue, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $encryptedValue; // Return as-is if not encrypted
            }
            
            return $this->decrypt($encryptedData);
        } catch (Exception $e) {
            Log::warning('Failed to decrypt field', [
                'error' => $e->getMessage(),
                'encrypted_value' => substr($encryptedValue, 0, 50) . '...'
            ]);
            return $encryptedValue; // Return as-is if decryption fails
        }
    }

    /**
     * Encrypt sensitive fields in array
     */
    public function encryptSensitiveFields(array $data, array $sensitiveFields = null): array
    {
        $sensitiveFields = $sensitiveFields ?: $this->getDefaultSensitiveFields();
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->encryptField($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Decrypt sensitive fields in array
     */
    public function decryptSensitiveFields(array $data, array $sensitiveFields = null): array
    {
        $sensitiveFields = $sensitiveFields ?: $this->getDefaultSensitiveFields();
        
        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = $this->decryptField($data[$field]);
            }
        }
        
        return $data;
    }

    /**
     * Get default sensitive fields
     */
    private function getDefaultSensitiveFields(): array
    {
        return [
            'password',
            'api_key',
            'secret',
            'token',
            'ssn',
            'credit_card',
            'bank_account',
            'phone',
            'email',
            'address',
        ];
    }

    /**
     * Get or create encryption key
     */
    private function getOrCreateEncryptionKey(): string
    {
        $key = env('ENCRYPTION_KEY');
        
        if (empty($key)) {
            $key = $this->generateKey();
            Log::warning('No encryption key found, generated new one. Please set ENCRYPTION_KEY in .env');
        }
        
        return base64_decode($key);
    }

    /**
     * Rotate encryption key
     */
    public function rotateKey(): array
    {
        $oldKey = $this->getOrCreateEncryptionKey();
        $newKey = $this->generateKey();
        
        // Store new key
        $this->storeKey($newKey);
        
        return [
            'old_key' => base64_encode($oldKey),
            'new_key' => $newKey,
            'rotated_at' => now()->toISOString(),
        ];
    }

    /**
     * Store encryption key securely
     */
    private function storeKey(string $key): void
    {
        // In production, store in secure key management system
        // For now, we'll use environment variable
        Log::info('New encryption key generated', [
            'key_id' => substr(hash('sha256', $key), 0, 16),
            'rotated_at' => now()->toISOString()
        ]);
    }

    /**
     * Generate secure password
     */
    public function generateSecurePassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        
        $all = $uppercase . $lowercase . $numbers . $symbols;
        
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];
        
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        
        return str_shuffle($password);
    }

    /**
     * Validate password strength
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters long';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        // Check for common patterns
        $commonPatterns = [
            '/123/',
            '/abc/i',
            '/qwerty/i',
            '/password/i',
            '/admin/i',
        ];
        
        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, $password)) {
                $errors[] = 'Password contains common patterns';
                break;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password),
        ];
    }

    /**
     * Calculate password strength score
     */
    private function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // Length bonus
        $score += min(strlen($password) * 2, 20);
        
        // Character variety bonus
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Uniqueness bonus
        $uniqueChars = count(array_unique(str_split($password)));
        $score += min($uniqueChars, 10);
        
        return min($score, 100);
    }

    /**
     * Generate secure API token
     */
    public function generateApiToken(): string
    {
        return Str::random(64);
    }

    /**
     * Generate secure session token
     */
    public function generateSessionToken(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Encrypt file content
     */
    public function encryptFile(string $filePath, string $outputPath = null): string
    {
        $content = file_get_contents($filePath);
        $encrypted = $this->encrypt($content);
        
        $outputPath = $outputPath ?: $filePath . '.encrypted';
        file_put_contents($outputPath, json_encode($encrypted));
        
        return $outputPath;
    }

    /**
     * Decrypt file content
     */
    public function decryptFile(string $encryptedFilePath, string $outputPath = null): string
    {
        $encryptedData = json_decode(file_get_contents($encryptedFilePath), true);
        $decrypted = $this->decrypt($encryptedData);
        
        $outputPath = $outputPath ?: str_replace('.encrypted', '', $encryptedFilePath);
        file_put_contents($outputPath, $decrypted);
        
        return $outputPath;
    }
}
