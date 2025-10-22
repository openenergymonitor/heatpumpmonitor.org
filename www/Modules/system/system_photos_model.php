<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

class SystemPhotos
{
    private $mysqli;
    private $system;

    public function __construct($mysqli, $system = null)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;
    }

    public function upload_photo($userid) {
        $userid = (int) $userid;
        
        // Check if photo was uploaded
        if (!isset($_FILES['photo'])) {
            return array("success" => false, "message" => "No photo uploaded");
        }
        
        // Get system ID from POST data
        if (!isset($_POST['system_id'])) {
            return array("success" => false, "message" => "System ID required");
        }
        
        $system_id = (int) $_POST['system_id'];
        
        // Check if user has write access to this system
        if ($this->system && !$this->system->has_write_access($userid, $system_id)) {
            return array("success" => false, "message" => "Access denied");
        }
        
        $photo = $_FILES['photo'];
        
        // Check for upload errors
        if ($photo['error'] !== UPLOAD_ERR_OK) {
            return array("success" => false, "message" => "Upload failed with error: " . $photo['error']);
        }
        
        // Validate file size (5MB max)
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ($photo['size'] > $max_size) {
            return array("success" => false, "message" => "File size exceeds 5MB limit");
        }
        
        // Validate file type
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $photo['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return array("success" => false, "message" => "Invalid file type. Only JPG, PNG, and WebP are allowed");
        }
        
        // Check if system already has 4 photos
        $existing_count = $this->get_photo_count($system_id);
        if ($existing_count >= 4) {
            return array("success" => false, "message" => "Maximum of 4 photos allowed per system");
        }
        
        // Create directory structure
        $upload_dir = "theme/img/system/" . $system_id . "/";
        if (!file_exists($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                return array("success" => false, "message" => "Failed to create upload directory");
            }
        }
        
        // Generate random filename while preserving extension
        $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
        $filename = uniqid('img_', true) . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($photo['tmp_name'], $filepath)) {
            return array("success" => false, "message" => "Failed to save uploaded file");
        }
        
        // Get image dimensions
        $image_info = getimagesize($filepath);
        $width = $image_info ? $image_info[0] : null;
        $height = $image_info ? $image_info[1] : null;
        
        // Save to database
        $stmt = $this->mysqli->prepare("INSERT INTO system_images (system_id, image_path, original_filename, width, height, file_size, date_uploaded) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $date_uploaded = time();
        $stmt->bind_param("issiiii", $system_id, $filepath, $photo['name'], $width, $height, $photo['size'], $date_uploaded);
        
        if ($stmt->execute()) {
            $image_id = $this->mysqli->insert_id;
            return array(
                "success" => true, 
                "message" => "Photo uploaded successfully",
                "image_id" => $image_id,
                "url" => $filepath,
                "width" => $width,
                "height" => $height
            );
        } else {
            // Clean up file if database insert fails
            unlink($filepath);
            return array("success" => false, "message" => "Failed to save photo information to database");
        }
    }
    
    public function get_photos($userid, $system_id) {
        $system_id = (int) $system_id;
        
        // Check if user has read access to this system
        if ($this->system && !$this->system->has_read_access($userid, $system_id)) {
            return array("success" => false, "message" => "Access denied");
        }
        
        $stmt = $this->mysqli->prepare("SELECT id, image_path, original_filename, width, height, file_size, date_uploaded FROM system_images WHERE system_id = ? ORDER BY date_uploaded ASC");
        $stmt->bind_param("i", $system_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $photos = array();
        while ($row = $result->fetch_assoc()) {
            $photos[] = array(
                'id' => (int)$row['id'],
                'url' => $row['image_path'],
                'original_filename' => $row['original_filename'],
                'width' => $row['width'] ? (int)$row['width'] : null,
                'height' => $row['height'] ? (int)$row['height'] : null,
                'file_size' => $row['file_size'] ? (int)$row['file_size'] : null,
                'date_uploaded' => (int)$row['date_uploaded']
            );
        }
        
        return array("success" => true, "photos" => $photos);
    }
    
    private function get_photo_count($system_id) {
        $system_id = (int) $system_id;
        $stmt = $this->mysqli->prepare("SELECT COUNT(*) as count FROM system_images WHERE system_id = ?");
        $stmt->bind_param("i", $system_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        return (int)$row['count'];
    }

    public function delete_photo($userid, $photo_id) {
        $photo_id = (int) $photo_id;
        
        // Get photo details and check if user has access
        $stmt = $this->mysqli->prepare("SELECT si.*, sm.userid as system_userid FROM system_images si JOIN system_meta sm ON si.system_id = sm.id WHERE si.id = ?");
        $stmt->bind_param("i", $photo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return array("success" => false, "message" => "Photo not found");
        }
        
        // Check if user has write access to this system
        if ($this->system && !$this->system->has_write_access($userid, $row['system_id'])) {
            return array("success" => false, "message" => "Access denied");
        }
        
        // Delete the file from filesystem
        if (file_exists($row['image_path'])) {
            if (!unlink($row['image_path'])) {
                return array("success" => false, "message" => "Failed to delete image file");
            }
        }
        
        // Delete from database
        $stmt = $this->mysqli->prepare("DELETE FROM system_images WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        
        if ($stmt->execute()) {
            return array("success" => true, "message" => "Photo deleted successfully");
        } else {
            return array("success" => false, "message" => "Failed to delete photo from database");
        }
    }
}