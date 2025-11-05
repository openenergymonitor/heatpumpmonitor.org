<?php

// no direct access
defined('EMONCMS_EXEC') or die('Restricted access');

require_once "Modules/system/thumbnail_generator.php";

class SystemPhotos
{
    private $mysqli;
    private $system;
    private $thumbnail_generator;

    public function __construct($mysqli, $system = null)
    {
        $this->mysqli = $mysqli;
        $this->system = $system;
        $this->thumbnail_generator = new ThumbnailGenerator();
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
        
        // Get photo type from POST data (default to 'other')
        $photo_type = isset($_POST['photo_type']) ? $_POST['photo_type'] : 'other';
        $allowed_types = array('outdoor_unit', 'plant_room', 'other');
        if (!in_array($photo_type, $allowed_types)) {
            $photo_type = 'other';
        }
        
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
        
        // Generate thumbnails (don't fail upload if this fails)
        $thumbnail_result = $this->thumbnail_generator->generateThumbnails($filepath);
        $thumbnail_paths = $thumbnail_result['thumbnails'] ?? [];
        
        // Encode thumbnails as JSON
        $thumbnails_json = !empty($thumbnail_paths) ? json_encode($thumbnail_paths) : null;
                
        // Save to database
        $stmt = $this->mysqli->prepare("INSERT INTO system_images (system_id, photo_type, image_path, original_filename, width, height, file_size, date_uploaded, thumbnails) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $date_uploaded = time();
        $stmt->bind_param("isssiiiis", $system_id, $photo_type, $filepath, $photo['name'], $width, $height, $photo['size'], $date_uploaded, $thumbnails_json);
        
        if ($stmt->execute()) {
            $image_id = $this->mysqli->insert_id;
            
            // Prepare response with thumbnail information
            $response = array(
                "success" => true, 
                "message" => "Photo uploaded successfully",
                "image_id" => $image_id,
                "url" => $filepath,
                "width" => $width,
                "height" => $height,
                "thumbnail_generation" => array(
                    "success" => $thumbnail_result['success'],
                    "count" => count($thumbnail_paths)
                )
            );
            
            // Add thumbnail URLs if they were generated
            if (!empty($thumbnail_paths)) {
                $response["thumbnails"] = $thumbnail_paths;
            }
            
            // Add thumbnail generation errors to response for debugging
            if (!empty($thumbnail_result['errors'])) {
                $response["thumbnail_generation"]["errors"] = $thumbnail_result['errors'];
            }
            
            return $response;
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
        
        $stmt = $this->mysqli->prepare("SELECT id, photo_type, image_path, original_filename, width, height, file_size, date_uploaded, thumbnails FROM system_images WHERE system_id = ? ORDER BY date_uploaded ASC");
        $stmt->bind_param("i", $system_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $photos = array();
        while ($row = $result->fetch_assoc()) {
            $photo_data = array(
                'id' => (int)$row['id'],
                'photo_type' => $row['photo_type'],
                'url' => $row['image_path'],
                'original_filename' => $row['original_filename'],
                'width' => $row['width'] ? (int)$row['width'] : null,
                'height' => $row['height'] ? (int)$row['height'] : null,
                'file_size' => $row['file_size'] ? (int)$row['file_size'] : null,
                'date_uploaded' => (int)$row['date_uploaded']
            );
            
            // Add thumbnails in optimized format (stored directly as array)
            $thumbnails = array();
            if ($row['thumbnails']) {
                $decoded_thumbnails = json_decode($row['thumbnails'], true);
                if ($decoded_thumbnails && is_array($decoded_thumbnails)) {
                    // Verify files exist and filter out missing ones
                    foreach ($decoded_thumbnails as $thumbnail) {
                        if (isset($thumbnail['url']) && file_exists($thumbnail['url'])) {
                            $thumbnails[] = $thumbnail;
                        }
                    }
                }
            }

            if (!empty($thumbnails)) {
                $photo_data['thumbnails'] = $thumbnails;
            }
            $photos[] = $photo_data;
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
        
        // Delete the main file from filesystem
        if (file_exists($row['image_path'])) {
            if (!unlink($row['image_path'])) {
                return array("success" => false, "message" => "Failed to delete image file");
            }
        }
        
        // Delete thumbnail files
        $this->thumbnail_generator->deleteThumbnails($row['image_path']);
        
        // Delete from database
        $stmt = $this->mysqli->prepare("DELETE FROM system_images WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        
        if ($stmt->execute()) {
            return array("success" => true, "message" => "Photo deleted successfully");
        } else {
            return array("success" => false, "message" => "Failed to delete photo from database");
        }
    }

    // Admin method to get all photos with pagination
    public function get_all_photos_admin($userid, $page = 1, $limit = 50) {
        $userid = (int) $userid;
        $page = (int) $page;
        $limit = (int) $limit;
        
        // Check if user is admin
        if ($this->system && !$this->system->is_admin($userid)) {
            return array("success" => false, "message" => "Admin access required");
        }
        
        $offset = ($page - 1) * $limit;
        
        // Get total count
        $count_result = $this->mysqli->query("SELECT COUNT(*) as total FROM system_images");
        $total_photos = $count_result->fetch_assoc()['total'];
        
        // Get photos with system info, ordered by upload date (newest first)
        $stmt = $this->mysqli->prepare("
            SELECT si.id, si.system_id, si.photo_type, si.image_path, si.original_filename, 
                   si.width, si.height, si.file_size, si.date_uploaded, si.thumbnails,
                   sm.location, sm.hp_manufacturer, sm.hp_model, sm.hp_output
            FROM system_images si 
            JOIN system_meta sm ON si.system_id = sm.id 
            ORDER BY si.date_uploaded DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $photos = array();
        while ($row = $result->fetch_assoc()) {
            $photo_data = array(
                'id' => (int)$row['id'],
                'system_id' => (int)$row['system_id'],
                'photo_type' => $row['photo_type'],
                'url' => $row['image_path'],
                'original_filename' => $row['original_filename'],
                'width' => $row['width'] ? (int)$row['width'] : null,
                'height' => $row['height'] ? (int)$row['height'] : null,
                'file_size' => $row['file_size'] ? (int)$row['file_size'] : null,
                'date_uploaded' => (int)$row['date_uploaded'],
                'system_location' => $row['location'],
                'system_info' => trim(($row['hp_output'] ? $row['hp_output'] . 'kW ' : '') . 
                                    ($row['hp_manufacturer'] ? $row['hp_manufacturer'] . ' ' : '') . 
                                    ($row['hp_model'] ? $row['hp_model'] : ''))
            );
            
            // Add thumbnails in optimized format (stored directly as array)
            if ($row['thumbnails']) {
                $decoded_thumbnails = json_decode($row['thumbnails'], true);
                if ($decoded_thumbnails && is_array($decoded_thumbnails)) {
                    // Verify files exist and filter out missing ones
                    $thumbnails = array();
                    foreach ($decoded_thumbnails as $thumbnail) {
                        if (isset($thumbnail['url']) && file_exists($thumbnail['url'])) {
                            $thumbnails[] = $thumbnail;
                        }
                    }
                    if (!empty($thumbnails)) {
                        $photo_data['thumbnails'] = $thumbnails;
                    }
                }
            }
            
            $photos[] = $photo_data;
        }
        $total_pages = ceil($total_photos / $limit);
        
        return array(
            "success" => true, 
            "photos" => $photos,
            "pagination" => array(
                "current_page" => $page,
                "total_pages" => $total_pages,
                "total_photos" => (int)$total_photos,
                "limit" => $limit,
                "has_next" => $page < $total_pages,
                "has_prev" => $page > 1
            )
        );
    }

    // Admin method to delete any photo (for admin interface)
    public function admin_delete_photo($userid, $photo_id) {
        $userid = (int) $userid;
        $photo_id = (int) $photo_id;
        
        // Check if user is admin
        if ($this->system && !$this->system->is_admin($userid)) {
            return array("success" => false, "message" => "Admin access required");
        }
        
        // Get photo details
        $stmt = $this->mysqli->prepare("SELECT * FROM system_images WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$row = $result->fetch_assoc()) {
            return array("success" => false, "message" => "Photo not found");
        }
        
        // Delete the file from filesystem
        if (file_exists($row['image_path'])) {
            if (!unlink($row['image_path'])) {
                return array("success" => false, "message" => "Failed to delete image file");
            }
        }
        
        // Delete thumbnail files
        $this->thumbnail_generator->deleteThumbnails($row['image_path']);
        
        // Delete from database
        $stmt = $this->mysqli->prepare("DELETE FROM system_images WHERE id = ?");
        $stmt->bind_param("i", $photo_id);
        
        if ($stmt->execute()) {
            return array("success" => true, "message" => "Photo deleted successfully");
        } else {
            return array("success" => false, "message" => "Failed to delete photo from database");
        }
    }

    /**
     * Generate thumbnails for existing images
     * Intelligently handles both missing thumbnails and new sizes
     * @param int|null $system_id If provided, only process images for this system
     * @param bool $force_all If true, regenerate all thumbnails even if they exist
     * @return array Results of thumbnail generation
     */
    public function generateThumbnails($system_id = null, $force_all = false) {
        $where_clause = "";
        $params = array();
        $types = "";
        
        if ($system_id !== null) {
            $where_clause = "WHERE system_id = ?";
            $params[] = $system_id;
            $types = "i";
        }
        
        // Get all images
        $sql = "SELECT id, system_id, image_path, original_filename, thumbnails FROM system_images" . 
               ($where_clause ? " $where_clause" : "");
        
        if ($system_id !== null) {
            $stmt = $this->mysqli->prepare($sql);
            $stmt->bind_param($types, ...$params);
        } else {
            $stmt = $this->mysqli->prepare($sql);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $results = array(
            'total_processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        // Get expected thumbnail configurations
        $expected_configs = $this->thumbnail_generator->getThumbnailSizes();
        
        while ($row = $result->fetch_assoc()) {
            $results['total_processed']++;
            $image_id = $row['id'];
            $image_path = $row['image_path'];
            
            if (!file_exists($image_path)) {
                $results['failed']++;
                $results['errors'][] = "Image file not found for ID $image_id: $image_path";
                continue;
            }
            
            // If not forcing all, check if thumbnails need updating
            if (!$force_all) {
                if ($this->thumbnail_generator->needsNewSizes($image_path, 
                    $row['thumbnails'] ? json_decode($row['thumbnails'], true) : null)) {
                    // Needs updating
                } else {
                    // Skip - thumbnails are complete and files exist
                    $results['skipped']++;
                    continue;
                }
            }
            
            // Generate thumbnails
            $thumbnail_result = $this->thumbnail_generator->generateThumbnails($image_path);
            
            if ($thumbnail_result['success']) {
                // Update database with thumbnail paths
                $thumbnail_paths = $thumbnail_result['thumbnails'];
                $thumbnails_json = json_encode($thumbnail_paths);
                
                $update_stmt = $this->mysqli->prepare(
                    "UPDATE system_images SET thumbnails = ? WHERE id = ?"
                );
                $update_stmt->bind_param("si", $thumbnails_json, $image_id);
                
                if ($update_stmt->execute()) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    $results['errors'][] = "Failed to update database for image ID $image_id";
                }
            } else {
                $results['failed']++;
                $results['errors'][] = "Failed to generate thumbnails for image ID $image_id: " . 
                                     implode(', ', $thumbnail_result['errors']);
            }
        }
        
        return $results;
    }
}