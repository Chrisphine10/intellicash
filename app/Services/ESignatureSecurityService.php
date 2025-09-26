<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ESignatureSecurityService
{
    /**
     * Generate a cryptographically secure signature token
     */
    public function generateSecureToken(): string
    {
        // Use cryptographically secure random bytes
        $randomBytes = random_bytes(32);
        return bin2hex($randomBytes);
    }

    /**
     * Create a cryptographic signature hash
     */
    public function createSignatureHash(string $signatureData, string $signerEmail, string $documentId): string
    {
        $payload = [
            'signature_data' => $signatureData,
            'signer_email' => $signerEmail,
            'document_id' => $documentId,
            'timestamp' => now()->timestamp,
            'nonce' => Str::random(16)
        ];

        $payloadString = json_encode($payload);
        return hash_hmac('sha256', $payloadString, config('app.key'));
    }

    /**
     * Verify signature integrity
     */
    public function verifySignatureHash(string $signatureData, string $signerEmail, string $documentId, string $providedHash): bool
    {
        // Recreate the hash with the same parameters
        $expectedHash = $this->createSignatureHash($signatureData, $signerEmail, $documentId);
        
        // Use hash_equals for timing attack protection
        return hash_equals($expectedHash, $providedHash);
    }

    /**
     * Encrypt signature data
     */
    public function encryptSignatureData(string $signatureData): string
    {
        return Crypt::encryptString($signatureData);
    }

    /**
     * Decrypt signature data
     */
    public function decryptSignatureData(string $encryptedData): string
    {
        return Crypt::decryptString($encryptedData);
    }

    /**
     * Generate document integrity hash
     */
    public function generateDocumentHash(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Document file not found');
        }

        $fileContent = file_get_contents($filePath);
        return hash('sha256', $fileContent);
    }

    /**
     * Verify document integrity
     */
    public function verifyDocumentIntegrity(string $filePath, string $expectedHash): bool
    {
        $actualHash = $this->generateDocumentHash($filePath);
        return hash_equals($expectedHash, $actualHash);
    }

    /**
     * Validate signature timestamp (prevent replay attacks)
     */
    public function validateSignatureTimestamp(int $timestamp, int $maxAgeMinutes = 30): bool
    {
        $currentTime = now()->timestamp;
        $ageMinutes = ($currentTime - $timestamp) / 60;
        
        return $ageMinutes <= $maxAgeMinutes && $ageMinutes >= 0;
    }

    /**
     * Generate secure field validation hash
     */
    public function generateFieldValidationHash(array $fields, string $signerEmail): string
    {
        $fieldData = [];
        foreach ($fields as $field) {
            $fieldData[] = [
                'id' => $field['field_id'],
                'value' => $field['value'],
                'signer' => $signerEmail
            ];
        }
        
        $payload = json_encode($fieldData);
        return hash_hmac('sha256', $payload, config('app.key'));
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        // Remove control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        return $input;
    }

    /**
     * Validate signature data format
     */
    public function validateSignatureData(string $signatureData, string $signatureType): bool
    {
        switch ($signatureType) {
            case 'drawn':
                // Validate base64 image data
                if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $signatureData)) {
                    return false;
                }
                $imageData = substr($signatureData, strpos($signatureData, ',') + 1);
                return base64_decode($imageData, true) !== false;
                
            case 'typed':
                // Validate typed signature (alphanumeric and common punctuation)
                return preg_match('/^[a-zA-Z0-9\s\.,\-\']+$/', $signatureData) && strlen($signatureData) <= 100;
                
            case 'uploaded':
                // Validate uploaded image data
                return preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,/', $signatureData);
                
            default:
                return false;
        }
    }

    /**
     * Check for suspicious activity patterns
     */
    public function detectSuspiciousActivity(string $ipAddress, string $userAgent): array
    {
        $suspiciousPatterns = [
            'bot' => preg_match('/bot|crawler|spider|scraper/i', $userAgent),
            'empty_ua' => empty($userAgent),
            'suspicious_ua' => preg_match('/curl|wget|python|java|php/i', $userAgent),
            'rapid_requests' => $this->checkRapidRequests($ipAddress)
        ];

        return $suspiciousPatterns;
    }

    /**
     * Check for rapid requests from same IP
     */
    private function checkRapidRequests(string $ipAddress): bool
    {
        $cacheKey = "esignature_requests_{$ipAddress}";
        $requests = cache()->get($cacheKey, []);
        
        $now = now()->timestamp;
        $requests[] = $now;
        
        // Keep only requests from last 5 minutes
        $requests = array_filter($requests, function($timestamp) use ($now) {
            return ($now - $timestamp) <= 300;
        });
        
        cache()->put($cacheKey, $requests, 300);
        
        // More than 10 requests in 5 minutes is suspicious
        return count($requests) > 10;
    }

    /**
     * Parse browser information from user agent
     */
    public function parseBrowserInfo(string $userAgent): string
    {
        $browsers = [
            'Chrome' => 'Chrome',
            'Firefox' => 'Firefox',
            'Safari' => 'Safari',
            'Edge' => 'Edge',
            'Opera' => 'Opera',
        ];

        foreach ($browsers as $browser => $name) {
            if (strpos($userAgent, $browser) !== false) {
                return $name;
            }
        }

        return 'Unknown Browser';
    }

    /**
     * Parse device information from user agent
     */
    public function parseDeviceInfo(string $userAgent): string
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'Mobile Device';
        } elseif (preg_match('/Windows/', $userAgent)) {
            return 'Windows';
        } elseif (preg_match('/Mac/', $userAgent)) {
            return 'Mac';
        } elseif (preg_match('/Linux/', $userAgent)) {
            return 'Linux';
        }

        return 'Unknown Device';
    }
}
