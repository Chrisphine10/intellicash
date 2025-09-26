<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;

class DataSanitizationService
{
    /**
     * Sanitize report inputs
     */
    public static function sanitizeReportInputs(array $inputs): array
    {
        $sanitized = [];
        
        foreach ($inputs as $key => $value) {
            switch ($key) {
                case 'date1':
                case 'date2':
                    $sanitized[$key] = self::sanitizeDate($value);
                    break;
                    
                case 'member_no':
                    $sanitized[$key] = self::sanitizeMemberNumber($value);
                    break;
                    
                case 'status':
                case 'loan_type':
                case 'per_page':
                case 'year':
                case 'month':
                case 'currency_id':
                    $sanitized[$key] = self::sanitizeInteger($value);
                    break;
                    
                case 'search':
                    $sanitized[$key] = self::sanitizeSearchTerm($value);
                    break;
                    
                default:
                    $sanitized[$key] = self::sanitizeString($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize date input
     */
    private static function sanitizeDate($value): ?string
    {
        if (empty($value)) return null;
        
        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Sanitize member number
     */
    private static function sanitizeMemberNumber($value): ?string
    {
        if (empty($value)) return null;
        
        // Remove any non-alphanumeric characters and convert to uppercase
        $sanitized = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value)));
        
        return !empty($sanitized) ? $sanitized : null;
    }
    
    /**
     * Sanitize integer input
     */
    private static function sanitizeInteger($value): ?int
    {
        if (empty($value)) return null;
        
        $int = filter_var($value, FILTER_VALIDATE_INT);
        return $int !== false ? $int : null;
    }
    
    /**
     * Sanitize search term
     */
    private static function sanitizeSearchTerm($value): ?string
    {
        if (empty($value)) return null;
        
        // Remove potentially dangerous characters
        $sanitized = preg_replace('/[<>"\']/', '', trim($value));
        
        return !empty($sanitized) ? $sanitized : null;
    }
    
    /**
     * Sanitize string input
     */
    private static function sanitizeString($value): ?string
    {
        if (empty($value)) return null;
        
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Sanitize email input
     */
    public static function sanitizeEmail($value): ?string
    {
        if (empty($value)) return null;
        
        $email = filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    
    /**
     * Sanitize phone number
     */
    public static function sanitizePhone($value): ?string
    {
        if (empty($value)) return null;
        
        // Remove all non-numeric characters except + at the beginning
        $sanitized = preg_replace('/[^0-9+]/', '', trim($value));
        
        return !empty($sanitized) ? $sanitized : null;
    }
    
    /**
     * Sanitize amount/money input
     */
    public static function sanitizeAmount($value): ?float
    {
        if (empty($value)) return null;
        
        // Remove currency symbols and commas
        $cleaned = preg_replace('/[^0-9.-]/', '', $value);
        $float = filter_var($cleaned, FILTER_VALIDATE_FLOAT);
        
        return $float !== false ? round($float, 2) : null;
    }
    
    /**
     * Sanitize file name
     */
    public static function sanitizeFileName($value): ?string
    {
        if (empty($value)) return null;
        
        // Remove path traversal attempts and dangerous characters
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($value));
        
        return !empty($sanitized) ? $sanitized : null;
    }
    
    /**
     * Sanitize SQL identifier (table/column names)
     */
    public static function sanitizeSqlIdentifier($value): ?string
    {
        if (empty($value)) return null;
        
        // Only allow alphanumeric characters and underscores
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $value);
        
        return !empty($sanitized) ? $sanitized : null;
    }
    
    /**
     * Sanitize URL
     */
    public static function sanitizeUrl($value): ?string
    {
        if (empty($value)) return null;
        
        $url = filter_var(trim($value), FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    
    /**
     * Sanitize JSON input
     */
    public static function sanitizeJson($value): ?array
    {
        if (empty($value)) return null;
        
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
    
    /**
     * Sanitize array of values
     */
    public static function sanitizeArray(array $values, string $type = 'string'): array
    {
        $sanitized = [];
        
        foreach ($values as $key => $value) {
            switch ($type) {
                case 'integer':
                    $sanitized[$key] = self::sanitizeInteger($value);
                    break;
                case 'email':
                    $sanitized[$key] = self::sanitizeEmail($value);
                    break;
                case 'amount':
                    $sanitized[$key] = self::sanitizeAmount($value);
                    break;
                default:
                    $sanitized[$key] = self::sanitizeString($value);
            }
        }
        
        return array_filter($sanitized, function($value) {
            return $value !== null;
        });
    }
}
