<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InputSanitizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Sanitize input data
        $this->sanitizeInput($request);

        // Validate critical fields
        $this->validateCriticalFields($request);

        return $next($request);
    }

    /**
     * Sanitize input data to prevent XSS and other attacks
     */
    protected function sanitizeInput(Request $request)
    {
        $input = $request->all();

        // Recursively sanitize array data
        $sanitized = $this->recursiveSanitize($input);

        // Replace request data with sanitized version
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize data
     */
    protected function recursiveSanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'recursiveSanitize'], $data);
        }

        if (is_string($data)) {
            // Remove potentially dangerous characters
            $data = strip_tags($data);
            $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
            
            // Remove null bytes
            $data = str_replace("\0", '', $data);
            
            // Remove control characters except newlines and tabs
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        }

        return $data;
    }

    /**
     * Validate critical fields for security
     */
    protected function validateCriticalFields(Request $request)
    {
        $rules = [];

        // Validate email fields
        foreach ($request->all() as $key => $value) {
            if (strpos($key, 'email') !== false && is_string($value)) {
                $rules[$key] = 'email|max:255';
            }
            
            // Validate numeric IDs
            if (strpos($key, '_id') !== false || strpos($key, 'id') !== false) {
                if (is_numeric($value)) {
                    $rules[$key] = 'integer|min:1';
                }
            }
            
            // Validate phone numbers
            if (strpos($key, 'phone') !== false || strpos($key, 'mobile') !== false) {
                $rules[$key] = 'regex:/^[\+]?[0-9\s\-\(\)]+$/|max:20';
            }
        }

        if (!empty($rules)) {
            $validator = Validator::make($request->all(), $rules);
            
            if ($validator->fails()) {
                abort(422, 'Invalid input data: ' . implode(', ', $validator->errors()->all()));
            }
        }
    }
}
