<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Exception;

class MilitaryFileUploadService
{
    // Military-grade file type validation
    private const ALLOWED_MIME_TYPES = [
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
    ];

    // Maximum file sizes (in bytes)
    private const MAX_FILE_SIZES = [
        'image' => 5 * 1024 * 1024,      // 5MB for images
        'document' => 10 * 1024 * 1024,  // 10MB for documents
        'archive' => 20 * 1024 * 1024,   // 20MB for archives
    ];

    // Dangerous file extensions to block
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'pht',
        'asp', 'aspx', 'jsp', 'jspx',
        'exe', 'bat', 'cmd', 'com', 'scr', 'pif',
        'sh', 'bash', 'csh', 'ksh', 'zsh',
        'py', 'pl', 'rb', 'js', 'vbs', 'wsf',
        'jar', 'war', 'ear', 'class',
    ];

    /**
     * Military-grade secure file upload with comprehensive validation
     */
    public function uploadFile(UploadedFile $file, string $directory = 'uploads', array $options = []): array
    {
        try {
            // Step 1: Basic file validation
            $this->validateBasicFile($file);
            
            // Step 2: Advanced security checks
            $this->performSecurityChecks($file);
            
            // Step 3: File content analysis
            $this->analyzeFileContent($file);
            
            // Step 4: Generate secure filename
            $secureFilename = $this->generateSecureFilename($file);
            
            // Step 5: Process file based on type
            $processedFile = $this->processFile($file, $secureFilename, $options);
            
            // Step 6: Store file securely
            $filePath = $this->storeFileSecurely($processedFile, $directory, $secureFilename);
            
            // Step 7: Log successful upload
            $this->logSuccessfulUpload($file, $filePath);
            
            return [
                'success' => true,
                'filename' => $secureFilename,
                'path' => $filePath,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'hash' => hash_file('sha256', $file->getPathname()),
            ];
            
        } catch (Exception $e) {
            $this->logUploadError($file, $e);
            return [
                'success' => false,
                'error' => 'File upload failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Basic file validation
     */
    private function validateBasicFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new Exception('Invalid file upload');
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file->getErrorMessage());
        }

        // Check file size
        $maxSize = $this->getMaxFileSize($file->getMimeType());
        if ($file->getSize() > $maxSize) {
            throw new Exception('File size exceeds maximum allowed size of ' . $this->formatBytes($maxSize));
        }
    }

    /**
     * Advanced security checks
     */
    private function performSecurityChecks(UploadedFile $file): void
    {
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        
        // Check for dangerous extensions
        if (in_array($extension, self::DANGEROUS_EXTENSIONS)) {
            throw new Exception('File type not allowed for security reasons');
        }
        
        // Check MIME type against allowed types
        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new Exception('File type not allowed');
        }
        
        // Verify extension matches MIME type
        $allowedExtensions = self::ALLOWED_MIME_TYPES[$mimeType];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('File extension does not match file type');
        }
        
        // Check for suspicious filename patterns
        $suspiciousPatterns = [
            '/\.\./',           // Directory traversal
            '/[<>:"|?*]/',      // Invalid characters
            '/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])$/i', // Windows reserved names
            '/\.(php|asp|jsp|exe|bat|cmd|sh)$/i', // Dangerous extensions
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $originalName)) {
                throw new Exception('Suspicious filename detected');
            }
        }
    }

    /**
     * Analyze file content for malicious patterns
     */
    private function analyzeFileContent(UploadedFile $file): void
    {
        $content = file_get_contents($file->getPathname());
        
        // Check for PHP code in non-PHP files
        if (!$this->isPhpFile($file->getMimeType())) {
            $phpPatterns = [
                '/<\?php/i',
                '/<\?=/i',
                '/<\?/i',
                '/eval\s*\(/i',
                '/exec\s*\(/i',
                '/system\s*\(/i',
                '/shell_exec/i',
                '/passthru/i',
                '/proc_open/i',
                '/popen/i',
            ];
            
            foreach ($phpPatterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    throw new Exception('Malicious content detected in file');
                }
            }
        }
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/create\s+table/i',
            '/alter\s+table/i',
        ];
        
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new Exception('SQL injection pattern detected');
            }
        }
        
        // Check file magic bytes
        $this->validateFileMagicBytes($file, $content);
    }

    /**
     * Validate file magic bytes
     */
    private function validateFileMagicBytes(UploadedFile $file, string $content): void
    {
        $mimeType = $file->getMimeType();
        $magicBytes = substr($content, 0, 10);
        
        $expectedMagicBytes = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47"],
            'image/gif' => ["GIF87a", "GIF89a"],
            'application/pdf' => ["%PDF"],
            'application/zip' => ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"],
        ];
        
        if (isset($expectedMagicBytes[$mimeType])) {
            $valid = false;
            foreach ($expectedMagicBytes[$mimeType] as $magic) {
                if (strpos($magicBytes, $magic) === 0) {
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                throw new Exception('File content does not match declared type');
            }
        }
    }

    /**
     * Generate secure filename
     */
    private function generateSecureFilename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $timestamp = now()->format('YmdHis');
        $randomString = Str::random(16);
        $hash = hash('sha256', $file->getPathname());
        
        return $timestamp . '_' . $randomString . '_' . substr($hash, 0, 8) . '.' . $extension;
    }

    /**
     * Process file based on type
     */
    private function processFile(UploadedFile $file, string $filename, array $options): UploadedFile
    {
        $mimeType = $file->getMimeType();
        
        // Process images
        if (strpos($mimeType, 'image/') === 0) {
            return $this->processImageFile($file, $options);
        }
        
        // For other files, return as-is (already validated)
        return $file;
    }

    /**
     * Process image files with security enhancements
     */
    private function processImageFile(UploadedFile $file, array $options): UploadedFile
    {
        try {
            // Create image instance
            $image = Image::make($file->getPathname());
            
            // Remove EXIF data for privacy
            $image->orientate();
            
            // Resize if too large (prevent memory exhaustion attacks)
            $maxWidth = $options['max_width'] ?? 2048;
            $maxHeight = $options['max_height'] ?? 2048;
            
            if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
                $image->resize($maxWidth, $maxHeight, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            }
            
            // Save processed image
            $tempPath = tempnam(sys_get_temp_dir(), 'processed_');
            $image->save($tempPath, 90); // 90% quality
            
            // Create new UploadedFile instance
            return new UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                $file->getError(),
                true // test mode
            );
            
        } catch (Exception $e) {
            Log::error('Image processing failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName()
            ]);
            return $file; // Return original if processing fails
        }
    }

    /**
     * Store file securely
     */
    private function storeFileSecurely(UploadedFile $file, string $directory, string $filename): string
    {
        // Create secure directory structure
        $secureDirectory = 'secure_uploads/' . $directory . '/' . now()->format('Y/m/d');
        
        // Store file
        $filePath = $file->storeAs($secureDirectory, $filename, 'private');
        
        // Set proper permissions
        $fullPath = Storage::disk('private')->path($filePath);
        chmod($fullPath, 0644);
        
        return $filePath;
    }

    /**
     * Get maximum file size for MIME type
     */
    private function getMaxFileSize(string $mimeType): int
    {
        if (strpos($mimeType, 'image/') === 0) {
            return self::MAX_FILE_SIZES['image'];
        } elseif (in_array($mimeType, ['application/zip', 'application/x-zip-compressed'])) {
            return self::MAX_FILE_SIZES['archive'];
        } else {
            return self::MAX_FILE_SIZES['document'];
        }
    }

    /**
     * Check if file is PHP file
     */
    private function isPhpFile(string $mimeType): bool
    {
        return in_array($mimeType, [
            'application/x-php',
            'text/x-php',
            'application/x-httpd-php',
        ]);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Log successful upload
     */
    private function logSuccessfulUpload(UploadedFile $file, string $filePath): void
    {
        Log::info('File uploaded successfully', [
            'original_name' => $file->getClientOriginalName(),
            'secure_filename' => basename($filePath),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log upload error
     */
    private function logUploadError(UploadedFile $file, Exception $e): void
    {
        Log::warning('File upload failed', [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'error' => $e->getMessage(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'timestamp' => now()->toISOString(),
        ]);
    }
}
