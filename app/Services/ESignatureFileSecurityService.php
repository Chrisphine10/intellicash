<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Facades\Image;

class ESignatureFileSecurityService
{
    /**
     * Allowed file types with MIME type validation
     */
    private const ALLOWED_TYPES = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ];

    /**
     * Maximum file size (10MB)
     */
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * Validate and secure file upload
     */
    public function validateAndSecureFile(UploadedFile $file): array
    {
        $errors = [];
        
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            $errors[] = 'File size must be less than ' . $this->formatBytes(self::MAX_FILE_SIZE);
        }
        
        // Check file type
        if (!$this->isValidFileType($file)) {
            $errors[] = 'File must be a PDF or Word document';
        }
        
        // Check file extension matches MIME type
        if (!$this->validateFileExtension($file)) {
            $errors[] = 'File extension does not match file type';
        }
        
        // Scan for malicious content
        if (!$this->scanFileContent($file)) {
            $errors[] = 'File contains potentially malicious content';
        }
        
        // Check for embedded objects
        if ($this->hasEmbeddedObjects($file)) {
            $errors[] = 'File contains embedded objects which are not allowed';
        }
        
        return $errors;
    }

    /**
     * Check if file type is valid
     */
    private function isValidFileType(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            return false;
        }
        
        return in_array($mimeType, self::ALLOWED_TYPES[$extension]);
    }

    /**
     * Validate file extension matches MIME type
     */
    private function validateFileExtension(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        
        // Additional validation using file content
        $fileContent = file_get_contents($file->getPathname());
        
        switch ($extension) {
            case 'pdf':
                return strpos($fileContent, '%PDF-') === 0;
            case 'doc':
                return strpos($fileContent, "\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1") === 0;
            case 'docx':
                // DOCX files are ZIP archives
                return $this->isValidZipFile($fileContent);
            default:
                return false;
        }
    }

    /**
     * Check if content is a valid ZIP file (for DOCX)
     */
    private function isValidZipFile(string $content): bool
    {
        // Check ZIP file signature
        return strpos($content, "PK\x03\x04") === 0 || strpos($content, "PK\x05\x06") === 0;
    }

    /**
     * Scan file content for malicious patterns
     */
    private function scanFileContent(UploadedFile $file): bool
    {
        $fileContent = file_get_contents($file->getPathname());
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '/<script[^>]*>.*?<\/script>/is',  // JavaScript
            '/javascript:/i',                  // JavaScript URLs
            '/vbscript:/i',                    // VBScript URLs
            '/onload\s*=/i',                   // Event handlers
            '/onerror\s*=/i',
            '/onclick\s*=/i',
            '/<iframe[^>]*>/i',               // Iframes
            '/<object[^>]*>/i',               // Objects
            '/<embed[^>]*>/i',                // Embeds
            '/<applet[^>]*>/i',               // Applets
            '/eval\s*\(/i',                   // Eval functions
            '/exec\s*\(/i',                   // Exec functions
            '/system\s*\(/i',                 // System functions
            '/shell_exec\s*\(/i',             // Shell exec
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $fileContent)) {
                Log::warning('Suspicious content detected in uploaded file', [
                    'file_name' => $file->getClientOriginalName(),
                    'pattern' => $pattern,
                    'ip' => request()->ip()
                ]);
                return false;
            }
        }
        
        return true;
    }

    /**
     * Check for embedded objects in file
     */
    private function hasEmbeddedObjects(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if ($extension === 'pdf') {
            return $this->checkPdfEmbeddedObjects($file);
        }
        
        return false;
    }

    /**
     * Check PDF for embedded objects
     */
    private function checkPdfEmbeddedObjects(UploadedFile $file): bool
    {
        $fileContent = file_get_contents($file->getPathname());
        
        // Check for embedded objects in PDF
        $embeddedPatterns = [
            '/\/EmbeddedFile/',
            '/\/JavaScript/',
            '/\/Launch/',
            '/\/GoToR/',
            '/\/URI/',
            '/\/SubmitForm/',
            '/\/ImportData/',
        ];
        
        foreach ($embeddedPatterns as $pattern) {
            if (preg_match($pattern, $fileContent)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate secure file path
     */
    public function generateSecureFilePath(string $originalName, string $tenantId): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', pathinfo($originalName, PATHINFO_FILENAME));
        $timestamp = now()->timestamp;
        $randomString = bin2hex(random_bytes(8));
        
        return "esignature/documents/{$tenantId}/{$timestamp}_{$randomString}_{$safeName}.{$extension}";
    }

    /**
     * Store file securely
     */
    public function storeFileSecurely(UploadedFile $file, string $tenantId): string
    {
        $securePath = $this->generateSecureFilePath($file->getClientOriginalName(), $tenantId);
        
        // Store file
        Storage::disk('public')->put($securePath, file_get_contents($file->getPathname()));
        
        // Set secure permissions
        $fullPath = storage_path('app/public/' . $securePath);
        chmod($fullPath, 0644);
        
        return $securePath;
    }

    /**
     * Generate document integrity hash
     */
    public function generateDocumentHash(string $filePath): string
    {
        $fullPath = storage_path('app/public/' . $filePath);
        
        if (!file_exists($fullPath)) {
            throw new \Exception('Document file not found');
        }
        
        return hash_file('sha256', $fullPath);
    }

    /**
     * Verify document integrity
     */
    public function verifyDocumentIntegrity(string $filePath, string $expectedHash): bool
    {
        try {
            $actualHash = $this->generateDocumentHash($filePath);
            return hash_equals($expectedHash, $actualHash);
        } catch (\Exception $e) {
            Log::error('Failed to verify document integrity', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTempFiles(): void
    {
        $tempDir = storage_path('app/temp/esignature');
        
        if (File::exists($tempDir)) {
            $files = File::files($tempDir);
            
            foreach ($files as $file) {
                // Delete files older than 1 hour
                if (time() - $file->getMTime() > 3600) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    /**
     * Validate signature image
     */
    public function validateSignatureImage(string $imageData): bool
    {
        // Check if it's a valid base64 image
        if (!preg_match('/^data:image\/(png|jpeg|jpg|gif);base64,/', $imageData)) {
            return false;
        }
        
        $imageString = substr($imageData, strpos($imageData, ',') + 1);
        $imageBinary = base64_decode($imageString, true);
        
        if ($imageBinary === false) {
            return false;
        }
        
        // Check image size (max 2MB)
        if (strlen($imageBinary) > 2 * 1024 * 1024) {
            return false;
        }
        
        // Validate image using GD
        $image = imagecreatefromstring($imageBinary);
        if ($image === false) {
            return false;
        }
        
        imagedestroy($image);
        return true;
    }
}
