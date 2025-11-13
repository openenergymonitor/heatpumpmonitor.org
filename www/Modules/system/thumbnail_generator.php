<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class ThumbnailGenerator
{
    // Thumbnail sizes configuration: [width, height, suffix, name, crop_mode]
    // crop_mode: 'fit' (maintain aspect ratio) or 'crop' (exact dimensions)
    private $thumbnail_sizes = [
        [80, 60, '_thumb_80x60', '80x60', 'crop'],    // Admin list thumbnails (cropped to exact size)
        [150, 150, '_thumb_150', '150', 'crop'],       // Gallery thumbnails (cropped to exact size)
        [300, 300, '_thumb_300', '300', 'crop'],       // Medium previews (cropped to exact size)
    ];

    private $quality = 85; // JPEG quality

    /**
     * Get thumbnail sizes configuration
     * @return array Array of [width, height, suffix, name, crop_mode] for each size
     */
    public function getThumbnailSizes() {
        return $this->thumbnail_sizes;
    }

    /**
     * Add a new thumbnail size
     * @param int $width
     * @param int $height  
     * @param string $name
     * @param string $crop_mode 'fit' (maintain aspect ratio) or 'crop' (exact dimensions)
     */
    public function addThumbnailSize($width, $height, $name, $crop_mode = 'fit') {
        $suffix = '_thumb_' . $name;
        $this->thumbnail_sizes[] = [$width, $height, $suffix, $name, $crop_mode];
    }

    /**
     * Generate all thumbnail sizes for an image
     * @param string $original_path Path to the original image
     * @return array Result array with success status and thumbnail paths
     */
    public function generateThumbnails($original_path) {
        $result = [
            'success' => false,
            'thumbnails' => [],
            'errors' => []
        ];

        if (!file_exists($original_path)) {
            $result['errors'][] = "Original image file not found: $original_path";
            return $result;
        }

        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            $result['errors'][] = "GD extension is not available";
            return $result;
        }

        // Get image info
        $image_info = getimagesize($original_path);
        if (!$image_info) {
            $result['errors'][] = "Could not get image information";
            return $result;
        }

        $mime_type = $image_info['mime'];

        // Create image resource from original (with EXIF rotation applied)
        $original_image = $this->createImageFromFile($original_path, $mime_type);
        if (!$original_image) {
            $result['errors'][] = "Could not create image resource from original file";
            return $result;
        }

        // Get actual dimensions after EXIF rotation has been applied
        $original_width = imagesx($original_image);
        $original_height = imagesy($original_image);

        $success_count = 0;
        $base_path = pathinfo($original_path, PATHINFO_DIRNAME);
        $base_name = pathinfo($original_path, PATHINFO_FILENAME);
        $extension = pathinfo($original_path, PATHINFO_EXTENSION);

        foreach ($this->thumbnail_sizes as $size_config) {
            $thumb_width = $size_config[0];
            $thumb_height = $size_config[1];
            $suffix = $size_config[2];
            $size_name = $size_config[3];
            $crop_mode = $size_config[4] ?? 'fit';

            // Generate thumbnail path
            $thumbnail_path = $base_path . '/' . $base_name . $suffix . '.' . $extension;

            // Calculate dimensions based on crop mode
            $dimensions = $this->calculateThumbnailDimensions(
                $original_width, 
                $original_height, 
                $thumb_width, 
                $thumb_height,
                $crop_mode
            );

            // Create thumbnail
            if ($this->createThumbnail(
                $original_image, 
                $thumbnail_path, 
                $dimensions, 
                $mime_type
            )) {
                // Store in new optimized format with explicit dimensions
                $result['thumbnails'][] = [
                    'width' => $thumb_width,
                    'height' => $thumb_height,
                    'url' => $thumbnail_path
                ];
                $success_count++;
            } else {
                $result['errors'][] = "Failed to create {$thumb_width}x{$thumb_height} thumbnail";
            }
        }

        // Clean up original image resource
        imagedestroy($original_image);

        $result['success'] = $success_count > 0;
        return $result;
    }

    /**
     * Create image resource from file based on MIME type
     * Automatically handles EXIF rotation for JPEG images
     */
    private function createImageFromFile($file_path, $mime_type) {
        $image = null;
        
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($file_path);
                // Handle EXIF rotation for JPEG images
                if ($image && function_exists('exif_read_data')) {
                    $image = $this->handleExifRotation($image, $file_path);
                }
                return $image;
            case 'image/png':
                return imagecreatefrompng($file_path);
            case 'image/webp':
                return imagecreatefromwebp($file_path);
            default:
                return false;
        }
    }

    /**
     * Handle EXIF rotation data for JPEG images
     * @param resource $image GD image resource
     * @param string $file_path Path to original image file
     * @return resource Rotated image resource
     */
    private function handleExifRotation($image, $file_path) {
        // Check if EXIF functions are available
        if (!function_exists('exif_read_data')) {
            // EXIF extension not available - thumbnails will use original orientation
            // This is acceptable fallback behavior, though not ideal for rotated mobile photos
            return $image;
        }

        try {
            // Suppress warnings/errors for corrupted EXIF data
            $exif = @exif_read_data($file_path);
            if (!$exif || !isset($exif['Orientation'])) {
                return $image; // No EXIF orientation data or unreadable
            }

            $rotated_image = null;
            switch ($exif['Orientation']) {
                case 3: // 180 degrees
                    $rotated_image = imagerotate($image, 180, 0);
                    break;
                case 6: // 90 degrees CW (270 degrees CCW)
                    $rotated_image = imagerotate($image, -90, 0);
                    break;
                case 8: // 90 degrees CCW (270 degrees CW)
                    $rotated_image = imagerotate($image, 90, 0);
                    break;
                case 2: // Horizontal flip
                    $rotated_image = $this->flipImageHorizontal($image);
                    break;
                case 4: // Vertical flip
                    $rotated_image = $this->flipImageVertical($image);
                    break;
                case 5: // Horizontal flip + 90 degrees CCW
                    $temp = $this->flipImageHorizontal($image);
                    if ($temp) {
                        $rotated_image = imagerotate($temp, 90, 0);
                        if ($temp !== $image) imagedestroy($temp);
                    }
                    break;
                case 7: // Horizontal flip + 90 degrees CW
                    $temp = $this->flipImageHorizontal($image);
                    if ($temp) {
                        $rotated_image = imagerotate($temp, -90, 0);
                        if ($temp !== $image) imagedestroy($temp);
                    }
                    break;
                case 1: // No rotation needed
                default:
                    return $image;
            }

            // If rotation was successful, destroy original and return rotated
            if ($rotated_image && $rotated_image !== $image) {
                imagedestroy($image);
                return $rotated_image;
            }
        } catch (Exception $e) {
            // If EXIF reading fails, just return the original image
            // This ensures the thumbnail generation continues even with problematic EXIF data
        }

        return $image;
    }

    /**
     * Flip image horizontally
     * @param resource $image GD image resource
     * @return resource Flipped image resource
     */
    private function flipImageHorizontal($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $flipped = imagecreatetruecolor($width, $height);
        
        if (!$flipped) {
            return $image;
        }

        // Preserve transparency
        $this->preserveTransparency($image, $flipped);

        // Copy pixels horizontally flipped
        for ($x = 0; $x < $width; $x++) {
            imagecopy($flipped, $image, $width - $x - 1, 0, $x, 0, 1, $height);
        }

        return $flipped;
    }

    /**
     * Flip image vertically
     * @param resource $image GD image resource
     * @return resource Flipped image resource
     */
    private function flipImageVertical($image) {
        $width = imagesx($image);
        $height = imagesy($image);
        $flipped = imagecreatetruecolor($width, $height);
        
        if (!$flipped) {
            return $image;
        }

        // Preserve transparency
        $this->preserveTransparency($image, $flipped);

        // Copy pixels vertically flipped
        for ($y = 0; $y < $height; $y++) {
            imagecopy($flipped, $image, 0, $height - $y - 1, 0, $y, $width, 1);
        }

        return $flipped;
    }

    /**
     * Preserve transparency for PNG/GIF images when creating new image resource
     * @param resource $source Source image
     * @param resource $dest Destination image
     */
    private function preserveTransparency($source, $dest) {
        $transparent_index = imagecolortransparent($source);
        if ($transparent_index >= 0) {
            $transparent_color = imagecolorsforindex($source, $transparent_index);
            $transparent_index = imagecolorallocate(
                $dest,
                $transparent_color['red'],
                $transparent_color['green'],
                $transparent_color['blue']
            );
            imagefill($dest, 0, 0, $transparent_index);
            imagecolortransparent($dest, $transparent_index);
        } else {
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 255, 255, 255, 127);
            imagefill($dest, 0, 0, $transparent);
        }
    }

    /**
     * Calculate thumbnail dimensions based on crop mode
     * @param int $orig_width Original image width
     * @param int $orig_height Original image height  
     * @param int $max_width Target width
     * @param int $max_height Target height
     * @param string $crop_mode 'fit' (maintain aspect ratio) or 'crop' (exact dimensions)
     */
    private function calculateThumbnailDimensions($orig_width, $orig_height, $max_width, $max_height, $crop_mode = 'fit') {
        if ($crop_mode === 'crop') {
            // For crop mode, we need to calculate source dimensions that will fill the target
            $scale_x = $max_width / $orig_width;
            $scale_y = $max_height / $orig_height;
            $scale = max($scale_x, $scale_y); // Use larger scale to fill the target
            
            // Calculate source crop area (centered)
            $crop_width = $max_width / $scale;
            $crop_height = $max_height / $scale;
            $crop_x = ($orig_width - $crop_width) / 2;
            $crop_y = ($orig_height - $crop_height) / 2;
            
            return [
                'width' => $max_width,
                'height' => $max_height,
                'crop_x' => (int) max(0, $crop_x),
                'crop_y' => (int) max(0, $crop_y),
                'crop_width' => (int) min($crop_width, $orig_width),
                'crop_height' => (int) min($crop_height, $orig_height),
                'mode' => 'crop'
            ];
        } else {
            // For fit mode, maintain aspect ratio
            $ratio = min($max_width / $orig_width, $max_height / $orig_height);
            
            return [
                'width' => round($orig_width * $ratio),
                'height' => round($orig_height * $ratio),
                'ratio' => $ratio,
                'mode' => 'fit'
            ];
        }
    }

    /**
     * Create and save thumbnail image
     */
    private function createThumbnail($original_image, $thumbnail_path, $dimensions, $mime_type) {
        $thumb_width = $dimensions['width'];
        $thumb_height = $dimensions['height'];

        // Create thumbnail canvas
        $thumbnail = imagecreatetruecolor($thumb_width, $thumb_height);
        if (!$thumbnail) {
            return false;
        }

        // Preserve transparency for PNG and WebP
        if ($mime_type === 'image/png' || $mime_type === 'image/webp') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }

        // Resize image based on mode
        if ($dimensions['mode'] === 'crop') {
            // Copy and resize with cropping
            $success = imagecopyresampled(
                $thumbnail,
                $original_image,
                0, 0, // Destination x, y
                $dimensions['crop_x'], $dimensions['crop_y'], // Source x, y
                $thumb_width, $thumb_height, // Destination width, height
                $dimensions['crop_width'], $dimensions['crop_height'] // Source width, height
            );
        } else {
            // Copy and resize maintaining aspect ratio
            $success = imagecopyresampled(
                $thumbnail,
                $original_image,
                0, 0, 0, 0,
                $thumb_width, $thumb_height,
                imagesx($original_image), imagesy($original_image)
            );
        }

        if (!$success) {
            imagedestroy($thumbnail);
            return false;
        }

        // Save thumbnail based on original format
        $save_success = false;
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                $save_success = imagejpeg($thumbnail, $thumbnail_path, $this->quality);
                break;
            case 'image/png':
                $save_success = imagepng($thumbnail, $thumbnail_path);
                break;
            case 'image/webp':
                $save_success = imagewebp($thumbnail, $thumbnail_path, $this->quality);
                break;
        }

        imagedestroy($thumbnail);
        return $save_success;
    }

    /**
     * Delete all thumbnail files for an image
     */
    public function deleteThumbnails($original_path) {
        $base_path = pathinfo($original_path, PATHINFO_DIRNAME);
        $base_name = pathinfo($original_path, PATHINFO_FILENAME);
        $extension = pathinfo($original_path, PATHINFO_EXTENSION);

        $deleted_count = 0;
        foreach ($this->thumbnail_sizes as $size_config) {
            $suffix = $size_config[2];
            $thumbnail_path = $base_path . '/' . $base_name . $suffix . '.' . $extension;
            
            if (file_exists($thumbnail_path)) {
                if (unlink($thumbnail_path)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Check if thumbnails exist for an image
     */
    public function thumbnailsExist($original_path) {
        $base_path = pathinfo($original_path, PATHINFO_DIRNAME);
        $base_name = pathinfo($original_path, PATHINFO_FILENAME);
        $extension = pathinfo($original_path, PATHINFO_EXTENSION);

        $existing = [];
        foreach ($this->thumbnail_sizes as $size_config) {
            $thumb_width = $size_config[0];
            $thumb_height = $size_config[1];
            $suffix = $size_config[2];
            $thumbnail_path = $base_path . '/' . $base_name . $suffix . '.' . $extension;
            
            if (file_exists($thumbnail_path)) {
                $existing[] = [
                    'width' => $thumb_width,
                    'height' => $thumb_height,
                    'url' => $thumbnail_path
                ];
            }
        }

        return $existing;
    }

    /**
     * Check if an image needs new thumbnail sizes based on current configuration
     * @param string $original_path Path to original image
     * @param array|null $existing_thumbnails Current thumbnails from database
     * @return bool True if new sizes need to be generated
     */
    public function needsNewSizes($original_path, $existing_thumbnails = null) {
        if (!$existing_thumbnails) {
            return true; // No thumbnails exist
        }
        
        if (!is_array($existing_thumbnails)) {
            return true; // Invalid format
        }
        
        // Get current configuration
        $expected_count = count($this->thumbnail_sizes);
        if (count($existing_thumbnails) < $expected_count) {
            return true; // Missing some sizes
        }
        
        // Check if all expected sizes exist
        $existing_sizes = [];
        foreach ($existing_thumbnails as $thumb) {
            if (isset($thumb['width'], $thumb['height'])) {
                $existing_sizes[] = $thumb['width'] . 'x' . $thumb['height'];
            }
        }
        
        foreach ($this->thumbnail_sizes as $config) {
            $expected_size = $config[0] . 'x' . $config[1];
            if (!in_array($expected_size, $existing_sizes)) {
                return true; // Missing expected size
            }
        }
        
        // Check if thumbnail files actually exist
        foreach ($existing_thumbnails as $thumb) {
            if (!isset($thumb['url']) || !file_exists($thumb['url'])) {
                return true; // Thumbnail file missing
            }
        }
        
        return false; // All good
    }

    /**
     * Generate thumbnail paths for a given original path
     */
    public function getThumbnailPaths($original_path) {
        $base_path = pathinfo($original_path, PATHINFO_DIRNAME);
        $base_name = pathinfo($original_path, PATHINFO_FILENAME);
        $extension = pathinfo($original_path, PATHINFO_EXTENSION);

        $paths = [];
        foreach ($this->thumbnail_sizes as $size_config) {
            $suffix = $size_config[2];
            $size_name = $size_config[3];
            $paths[$size_name] = $base_path . '/' . $base_name . $suffix . '.' . $extension;
        }

        return $paths;
    }
}