<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

require_once "Modules/system/thumbnail_generator.php";

/**
 * Shared image upload helper class
 * Consolidates common upload logic used by SystemPhotos and Heatpump models
 */
class ImageUploadHelper
{
    private $thumbnail_generator;
    private $max_file_size = 5242880; // 5MB in bytes
    private $allowed_mime_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/heic', 'image/heif');

    public function __construct()
    {
        $this->thumbnail_generator = new ThumbnailGenerator();
    }

    /**
     * Validate uploaded file
     * 
     * @param array $file The $_FILES array entry
     * @return array Success status and error message if validation fails
     */
    public function validateFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return array("success" => false, "message" => "Upload failed with error: " . $file['error']);
        }
        
        // Validate file size
        if ($file['size'] > $this->max_file_size) {
            return array("success" => false, "message" => "File size exceeds 5MB limit");
        }
        
        // Validate file type using MIME detection
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_mime_types)) {
            return array("success" => false, "message" => "Invalid file type. Only JPG, PNG, WebP, and HEIC are allowed");
        }
        
        return array("success" => true);
    }

    /**
     * Process and save uploaded image
     * 
     * @param array $file The $_FILES array entry
     * @param string $target_dir Directory to save the file
     * @param string $filename Target filename
     * @return array Result with success status, filepath, dimensions, and thumbnails
     */
    public function processUpload($file, $target_dir, $filename)
    {
        // Create directory structure if needed
        if (!file_exists($target_dir)) {
            if (!mkdir($target_dir, 0755, true)) {
                return array("success" => false, "message" => "Failed to create upload directory");
            }
        }
        
        $filepath = $target_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return array("success" => false, "message" => "Failed to save uploaded file");
        }
        
        // Convert HEIC/HEIF to JPEG for browser compatibility
        $converted = $this->convertHeicToJpeg($filepath);
        if ($converted) {
            $filepath = $converted['filepath'];
        }
        
        // Get image dimensions
        $image_info = getimagesize($filepath);
        $width = $image_info ? $image_info[0] : null;
        $height = $image_info ? $image_info[1] : null;
        
        // Generate thumbnails (don't fail upload if this fails)
        $thumbnail_result = $this->thumbnail_generator->generateThumbnails($filepath);
        $thumbnail_paths = $thumbnail_result['thumbnails'] ?? [];
        
        // Encode thumbnails as JSON
        $thumbnails_json = !empty($thumbnail_paths) ? json_encode($thumbnail_paths) : null;
        
        return array(
            "success" => true,
            "filepath" => $filepath,
            "width" => $width,
            "height" => $height,
            "thumbnails" => $thumbnail_paths,
            "thumbnails_json" => $thumbnails_json,
            "thumbnail_generation" => array(
                "success" => $thumbnail_result['success'],
                "count" => count($thumbnail_paths),
                "errors" => $thumbnail_result['errors'] ?? []
            )
        );
    }

    /**
     * Delete image and its thumbnails
     * 
     * @param string $filepath Path to the main image file
     * @param string|null $thumbnails_json JSON string of thumbnail paths
     * @return array Result with success status
     */
    public function deleteImage($filepath, $thumbnails_json = null)
    {
        $success = true;
        $messages = array();
        
        // Delete main image file if it exists
        if (!empty($filepath) && file_exists($filepath)) {
            if (!unlink($filepath)) {
                $success = false;
                $messages[] = "Failed to delete main image file";
            }
        }
        
        // Delete thumbnail files if they exist
        if (!empty($thumbnails_json)) {
            $thumbnails = json_decode($thumbnails_json, true);
            if (is_array($thumbnails)) {
                foreach ($thumbnails as $thumbnail) {
                    $thumbnail_path = isset($thumbnail['url']) ? $thumbnail['url'] : $thumbnail;
                    if (!empty($thumbnail_path) && file_exists($thumbnail_path)) {
                        if (!unlink($thumbnail_path)) {
                            $success = false;
                            $messages[] = "Failed to delete thumbnail: " . basename($thumbnail_path);
                        }
                    }
                }
            }
        }
        
        return array(
            "success" => $success,
            "message" => $success ? "Image deleted successfully" : implode(", ", $messages)
        );
    }

    /**
     * Get allowed MIME types
     * 
     * @return array List of allowed MIME types
     */
    public function getAllowedMimeTypes()
    {
        return $this->allowed_mime_types;
    }

    /**
     * Get max file size in bytes
     * 
     * @return int Max file size
     */
    public function getMaxFileSize()
    {
        return $this->max_file_size;
    }

    /**
     * Convert HEIC/HEIF image to JPEG for browser compatibility
     * 
     * @param string $filepath Path to the uploaded HEIC file
     * @return array|null Array with new filepath if converted, null if not HEIC or conversion failed
     */
    private function convertHeicToJpeg($filepath)
    {
        // Check if file is HEIC/HEIF
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        if (!in_array($mime_type, array('image/heic', 'image/heif'))) {
            return null; // Not a HEIC file, no conversion needed
        }
        
        // Check if ImageMagick extension is available
        if (!extension_loaded('imagick')) {
            // Log warning but don't fail - the HEIC file will be stored as-is
            error_log('ImageMagick extension not available for HEIC conversion');
            return null;
        }
        
        try {
            // Create Imagick object and read the HEIC file
            $imagick = new \Imagick($filepath);
            
            // Set JPEG quality (85 is a good balance between quality and file size)
            $imagick->setImageCompressionQuality(85);
            
            // Set output format to JPEG
            $imagick->setImageFormat('jpeg');
            
            // Generate new filename with .jpg extension
            $pathinfo = pathinfo($filepath);
            $new_filepath = $pathinfo['dirname'] . '/' . $pathinfo['filename'] . '.jpg';
            
            // Write the converted image
            $imagick->writeImage($new_filepath);
            
            // Clean up
            $imagick->clear();
            $imagick->destroy();
            
            // Delete the original HEIC file
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            return array('filepath' => $new_filepath);
            
        } catch (\Exception $e) {
            // Log error but don't fail the upload - the HEIC file will be stored as-is
            error_log('HEIC conversion failed: ' . $e->getMessage());
            return null;
        }
    }
}
