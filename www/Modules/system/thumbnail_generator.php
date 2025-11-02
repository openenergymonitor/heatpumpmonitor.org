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
     * @return array Array of [width, height, suffix, name] for each size
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

        $original_width = $image_info[0];
        $original_height = $image_info[1];
        $mime_type = $image_info['mime'];

        // Create image resource from original
        $original_image = $this->createImageFromFile($original_path, $mime_type);
        if (!$original_image) {
            $result['errors'][] = "Could not create image resource from original file";
            return $result;
        }

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
     */
    private function createImageFromFile($file_path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($file_path);
            case 'image/png':
                return imagecreatefrompng($file_path);
            case 'image/webp':
                return imagecreatefromwebp($file_path);
            default:
                return false;
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