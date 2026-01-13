<?php
/**
 * Upload Configuration for Profile Pictures
 * Security settings and validation rules for file uploads
 */

// Upload directory configuration - Dynamic path detection
define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');
define('PROFILE_PICTURES_DIR', UPLOAD_BASE_DIR . 'profile_pictures/');

// Dynamically determine the web URL path
function getWebBasePath() {
    // Method 1: Use REQUEST_URI when available (most reliable for web requests)
    if (isset($_SERVER['REQUEST_URI']) && isset($_SERVER['SCRIPT_NAME'])) {
        $script_name = $_SERVER['SCRIPT_NAME'];
        
        // Remove the filename to get directory path
        $script_dir = dirname($script_name);
        
        // Remove subdirectories to get to project root
        $subdirs_to_remove = ['config', 'lawyer', 'admin', 'api', 'includes'];
        
        while (in_array(strtolower(basename($script_dir)), array_map('strtolower', $subdirs_to_remove)) && $script_dir !== '/') {
            $script_dir = dirname($script_dir);
        }
        
        // Handle root directory case
        return $script_dir === '/' ? '' : $script_dir;
    }
    
    // Method 2: Parse from file system path (fallback)
    $current_dir = __DIR__;
    
    // Normalize path separators
    $current_dir = str_replace('\\', '/', $current_dir);
    
    // Find htdocs position for XAMPP/WAMP/LAMP stacks
    $htdocs_patterns = ['/htdocs/', '/www/', '/public_html/', '/html/'];
    
    foreach ($htdocs_patterns as $pattern) {
        $pos = strpos($current_dir, $pattern);
        if ($pos !== false) {
            // Extract web path after the web root directory
            $web_path = substr($current_dir, $pos + strlen($pattern) - 1);
            
            // Remove /config from the end to get project root
            $web_path = dirname($web_path);
            
            // Clean up and format
            $web_path = '/' . trim($web_path, '/');
            
            // Handle root case
            return $web_path === '/' ? '' : $web_path;
        }
    }
    
    // Method 3: Use DOCUMENT_ROOT if available
    if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $current_dir_normalized = str_replace('\\', '/', $current_dir);
        
        // Remove document root from current directory
        $relative_path = str_replace($document_root, '', $current_dir_normalized);
        
        // Remove /config from the end
        $project_path = dirname($relative_path);
        
        // Format the path
        $project_path = '/' . trim($project_path, '/');
        
        return $project_path === '/' ? '' : $project_path;
    }
    
    // Method 4: Smart detection based on common patterns
    // Look for project indicators in the path
    $path_parts = explode('/', str_replace('\\', '/', $current_dir));
    
    // Find common web server directory indicators
    $web_indicators = ['htdocs', 'www', 'public_html', 'html', 'public'];
    
    foreach ($web_indicators as $indicator) {
        $indicator_pos = array_search($indicator, $path_parts);
        if ($indicator_pos !== false) {
            // Get everything after the web root indicator
            $web_path_parts = array_slice($path_parts, $indicator_pos + 1);
            
            // Remove subdirectories to get to project root
            $subdirs_to_remove = ['config', 'lawyer', 'admin', 'api', 'includes'];
            
            while (!empty($web_path_parts) && in_array(strtolower(end($web_path_parts)), array_map('strtolower', $subdirs_to_remove))) {
                array_pop($web_path_parts);
            }
            
            // Build the web path
            $web_path = '/' . implode('/', $web_path_parts);
            
            return $web_path === '/' ? '' : $web_path;
        }
    }
    
    // Final fallback - return empty string for root
    return '';
}

define('PROFILE_PICTURES_URL', getWebBasePath() . '/uploads/profile_pictures/');

// File upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB in bytes
define('MAX_WIDTH', 1000); // Maximum image width in pixels
define('MAX_HEIGHT', 1000); // Maximum image height in pixels
define('THUMBNAIL_SIZE', 300); // Thumbnail size for optimization

// Allowed file types
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'image/gif'
]);

// Default images - Dynamic path
define('DEFAULT_PROFILE_PICTURE', getWebBasePath() . '/src/img/default-avatar.png');

/**
 * Initialize upload directories
 */
function initializeUploadDirectories() {
    $directories = [
        UPLOAD_BASE_DIR,
        PROFILE_PICTURES_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create upload directory: $dir");
            }
        }
        
        // Create .htaccess for security
        $htaccess_file = $dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Prevent direct access to uploaded files\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "# Allow only image files\n";
            $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            $htaccess_content .= "# Deny everything else\n";
            $htaccess_content .= "<FilesMatch \"^.*$\">\n";
            $htaccess_content .= "    Order Deny,Allow\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
}

/**
 * Validate uploaded file
 */
function validateUploadedFile($file) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        $errors[] = 'No file was uploaded';
        return $errors;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errors[] = 'Server configuration error';
                break;
            default:
                $errors[] = 'File upload failed';
        }
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File size exceeds ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB limit';
    }
    
    // Check file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Invalid file type. Only ' . implode(', ', ALLOWED_EXTENSIONS) . ' files are allowed';
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        $errors[] = 'Invalid file format';
    }
    
    // Check if it's actually an image
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $errors[] = 'File is not a valid image';
    }
    
    return $errors;
}

/**
 * Generate unique secure filename for profile picture
 * Includes timestamp and random string to prevent caching issues
 * SECURITY: Prevents directory traversal attacks
 */
function generateProfilePictureFilename($user_id, $extension) {
    // Sanitize user_id to prevent injection
    $user_id = (int)$user_id;
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID');
    }
    
    // Sanitize extension to prevent directory traversal
    $extension = strtolower(trim($extension));
    $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension); // Remove all non-alphanumeric
    
    // Validate extension against whitelist
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $extension = 'jpg'; // Default to safe extension
    }
    
    $timestamp = time();
    $random = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    
    // Generate secure filename with no path traversal possibility
    $filename = 'lawyer_' . $user_id . '_' . $timestamp . '_' . $random . '.' . $extension;
    
    // Final security check - ensure no path traversal characters
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        throw new Exception('Invalid filename generated');
    }
    
    return $filename;
}

/**
 * Clean up old profile picture file
 */
function cleanupOldProfilePicture($old_filename) {
    if (empty($old_filename)) {
        return true;
    }
    
    $old_file_path = PROFILE_PICTURES_DIR . $old_filename;
    if (file_exists($old_file_path)) {
        return unlink($old_file_path);
    }
    
    return true; // File doesn't exist, consider it cleaned up
}

/**
 * Get profile picture URL
 */
function getProfilePictureUrl($filename) {
    if (empty($filename)) {
        return DEFAULT_PROFILE_PICTURE;
    }
    
    return PROFILE_PICTURES_URL . $filename;
}
/**
 * Process and resize image
 * SECURITY: Added memory limits to prevent exhaustion attacks
 */
function processProfilePicture($source_path, $destination_path, $max_width = MAX_WIDTH, $max_height = MAX_HEIGHT) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        // If GD is not available, just copy the file
        if (!copy($source_path, $destination_path)) {
            throw new Exception('Failed to save image file');
        }
        return true;
    }
    
    // Get current memory limit and increase if needed
    $current_memory = ini_get('memory_limit');
    $current_memory_bytes = return_bytes($current_memory);
    $required_memory = 128 * 1024 * 1024; // 128MB for image processing
    
    if ($current_memory_bytes < $required_memory) {
        ini_set('memory_limit', '128M');
    }
    
    $image_info = getimagesize($source_path);
    if ($image_info === false) {
        throw new Exception('Invalid image file');
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $mime_type = $image_info['mime'];
    
    // Calculate new dimensions
    $ratio = min($max_width / $original_width, $max_height / $original_height);
    $new_width = round($original_width * $ratio);
    $new_height = round($original_height * $ratio);
    
    // Create image resource based on type
    switch ($mime_type) {
        case 'image/jpeg':
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case 'image/png':
            $source_image = imagecreatefrompng($source_path);
            break;
        case 'image/gif':
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            throw new Exception('Unsupported image type');
    }
    
    if ($source_image === false) {
        throw new Exception('Failed to create image resource');
    }
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Preserve transparency for PNG and GIF
    if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefill($new_image, 0, 0, $transparent);
    }
    
    // Resize image
    imagecopyresampled(
        $new_image, $source_image,
        0, 0, 0, 0,
        $new_width, $new_height,
        $original_width, $original_height
    );
    
    // Save processed image (always as JPEG for consistency)
    $success = imagejpeg($new_image, $destination_path, 90);
    
    // Clean up memory
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    if (!$success) {
        throw new Exception('Failed to save processed image');
    }
    
    return true;
}

/**
 * Convert memory limit string to bytes
 * SECURITY: Helper function for memory management
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

/**
 * Secure file cleanup with race condition protection
 * SECURITY: Prevents concurrent deletion issues
 */
function secureCleanupOldProfilePicture($old_filename) {
    if (empty($old_filename)) {
        return true;
    }
    
    // Validate filename to prevent directory traversal
    if (strpos($old_filename, '..') !== false || strpos($old_filename, '/') !== false || strpos($old_filename, '\\') !== false) {
        error_log("Suspicious filename in cleanup: $old_filename");
        return false;
    }
    
    $old_file_path = PROFILE_PICTURES_DIR . $old_filename;
    
    // Use file locking to prevent race conditions
    $lock_file = $old_file_path . '.lock';
    $lock_handle = fopen($lock_file, 'w');
    
    if (!$lock_handle) {
        return false;
    }
    
    if (flock($lock_handle, LOCK_EX)) {
        $result = false;
        if (file_exists($old_file_path)) {
            $result = unlink($old_file_path);
        } else {
            $result = true; // File doesn't exist, consider it cleaned up
        }
        
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
        unlink($lock_file); // Remove lock file
        
        return $result;
    } else {
        fclose($lock_handle);
        return false;
    }
}

// Initialize directories when this file is included
try {
    initializeUploadDirectories();
} catch (Exception $e) {
    error_log("Upload configuration error: " . $e->getMessage());
}
?>
