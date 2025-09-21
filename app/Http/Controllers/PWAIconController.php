<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PWAIconController extends Controller
{
    /**
     * Generate PWA icons from a source image
     */
    public function generateIcons(Request $request)
    {
        $request->validate([
            'source_image' => 'required|image|mimes:png,jpg,jpeg|max:2048',
            'app_name' => 'required|string|max:50'
        ]);

        $sourceImage = $request->file('source_image');
        $appName = $request->input('app_name', 'IntelliCash');
        
        // Icon sizes to generate
        $iconSizes = [16, 32, 72, 96, 128, 144, 152, 180, 192, 384, 512];
        
        $generatedIcons = [];
        
        foreach ($iconSizes as $size) {
            $filename = "pwa-icon-{$size}x{$size}.png";
            $path = public_path("uploads/media/{$filename}");
            
            if ($this->resizeImage($sourceImage->getPathname(), $path, $size, $size)) {
                $generatedIcons[] = $filename;
            }
        }
        
        // Update settings with icon filenames
        if (!empty($generatedIcons)) {
            \App\Models\Setting::updateOrInsert(
                ['name' => 'pwa_icon_192'],
                ['value' => 'pwa-icon-192x192.png', 'updated_at' => now()]
            );
            
            \App\Models\Setting::updateOrInsert(
                ['name' => 'pwa_icon_512'],
                ['value' => 'pwa-icon-512x512.png', 'updated_at' => now()]
            );
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Icons generated successfully',
            'generated_icons' => $generatedIcons
        ]);
    }
    
    /**
     * Resize image to specified dimensions
     */
    private function resizeImage($sourcePath, $destinationPath, $width, $height)
    {
        try {
            // Get image info
            $imageInfo = getimagesize($sourcePath);
            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Create source image resource
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) {
                return false;
            }
            
            // Create destination image
            $destinationImage = imagecreatetruecolor($width, $height);
            
            // Preserve transparency for PNG
            if ($mimeType === 'image/png') {
                imagealphablending($destinationImage, false);
                imagesavealpha($destinationImage, true);
                $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
                imagefilledrectangle($destinationImage, 0, 0, $width, $height, $transparent);
            }
            
            // Resize image
            imagecopyresampled(
                $destinationImage, $sourceImage,
                0, 0, 0, 0,
                $width, $height,
                $sourceWidth, $sourceHeight
            );
            
            // Save image
            $result = imagepng($destinationImage, $destinationPath, 9);
            
            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($destinationImage);
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('PWA Icon generation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create default PWA icons using text
     */
    public function createDefaultIcons()
    {
        $appName = get_option('company_name', 'IntelliCash');
        $initials = strtoupper(substr($appName, 0, 2));
        $themeColor = get_option('pwa_theme_color', get_option('primary_color', '#007bff'));
        
        // Convert hex color to RGB
        $bgColor = $this->hexToRgb($themeColor);
        $textColor = [255, 255, 255]; // White text
        
        $iconSizes = [16, 32, 72, 96, 128, 144, 152, 180, 192, 384, 512];
        $generatedIcons = [];
        
        foreach ($iconSizes as $size) {
            $filename = "pwa-icon-{$size}x{$size}.png";
            $path = public_path("uploads/media/{$filename}");
            
            if ($this->createTextIcon($path, $size, $initials, $bgColor, $textColor)) {
                $generatedIcons[] = $filename;
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Default icons created successfully',
            'generated_icons' => $generatedIcons
        ]);
    }
    
    /**
     * Create a text-based icon
     */
    private function createTextIcon($path, $size, $text, $bgColor, $textColor)
    {
        try {
            // Create image
            $image = imagecreatetruecolor($size, $size);
            
            // Allocate colors
            $bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
            $textCol = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);
            
            // Fill background
            imagefill($image, 0, 0, $bg);
            
            // Calculate font size (60% of icon size)
            $fontSize = $size * 0.6;
            
            // Get text dimensions
            $bbox = imagettfbbox($fontSize, 0, public_path('backend/assets/fonts/arial.ttf'), $text);
            $textWidth = $bbox[4] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[5];
            
            // Calculate position to center text
            $x = ($size - $textWidth) / 2;
            $y = ($size - $textHeight) / 2 + $textHeight;
            
            // Add text
            imagettftext($image, $fontSize, 0, $x, $y, $textCol, public_path('backend/assets/fonts/arial.ttf'), $text);
            
            // Save image
            $result = imagepng($image, $path, 9);
            
            // Clean up
            imagedestroy($image);
            
            return $result;
            
        } catch (\Exception $e) {
            // Fallback: create simple colored square
            return $this->createSimpleIcon($path, $size, $bgColor);
        }
    }
    
    /**
     * Create a simple colored square icon (fallback)
     */
    private function createSimpleIcon($path, $size, $bgColor)
    {
        try {
            $image = imagecreatetruecolor($size, $size);
            $color = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
            imagefill($image, 0, 0, $color);
            
            $result = imagepng($image, $path, 9);
            imagedestroy($image);
            
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb($hex)
    {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
        }
        
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }
}
