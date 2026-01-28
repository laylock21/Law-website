<?php
/**
 * Profile Picture Upload Handler
 * Handles secure upload and processing of lawyer profile pictures
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in output
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json');

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once '../../config/database.php';
    require_once '../../config/upload_config.php';
    require_once '../../config/Logger.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

$lawyer_id = $_SESSION['lawyer_id'];

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['profile_picture'])) {
        throw new Exception("No file uploaded");
    }
    
    $uploaded_file = $_FILES['profile_picture'];
    
    // Validate the uploaded file
    $validation_errors = validateUploadedFile($uploaded_file);
    if (!empty($validation_errors)) {
        throw new Exception(implode(', ', $validation_errors));
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
    
    // Generate secure filename
    $filename = generateProfilePictureFilename($lawyer_id, 'jpg'); // Always save as JPG
    $destination_path = PROFILE_PICTURES_DIR . $filename;
    
    // Get current profile picture for cleanup
    $current_picture_stmt = $pdo->prepare("SELECT profile FROM lawyer_profile WHERE lawyer_id = ?");
    $current_picture_stmt->execute([$lawyer_id]);
    $current_picture = $current_picture_stmt->fetchColumn();
    
    // Process and save the image
    processProfilePicture($uploaded_file['tmp_name'], $destination_path);
    
    // Read the processed image file as binary data for BLOB storage
    $image_binary_data = file_get_contents($destination_path);
    
    if ($image_binary_data === false) {
        throw new Exception("Failed to read processed image file");
    }
    
    // Update database with binary image data in lawyer_profile table
    // First check if lawyer_profile record exists
    $check_profile_stmt = $pdo->prepare("SELECT lawyer_id FROM lawyer_profile WHERE lawyer_id = ?");
    $check_profile_stmt->execute([$lawyer_id]);
    
    if ($check_profile_stmt->fetch()) {
        // Update existing record with BLOB data
        $update_stmt = $pdo->prepare("
            UPDATE lawyer_profile 
            SET profile = ? 
            WHERE lawyer_id = ?
        ");
    } else {
        // Insert new record with BLOB data
        $update_stmt = $pdo->prepare("
            INSERT INTO lawyer_profile (lawyer_id, profile, lp_fullname) 
            VALUES (?, ?, '')
        ");
    }
    
    $update_result = $update_stmt->execute([$image_binary_data, $lawyer_id]);
    
    if (!$update_result) {
        // If database update fails, remove the uploaded file
        if (file_exists($destination_path)) {
            unlink($destination_path);
        }
        throw new Exception("Failed to update profile picture in database");
    }
    
    // Clean up old profile picture (if exists and different from new one)
    if ($current_picture && $current_picture !== $filename) {
        secureCleanupOldProfilePicture($current_picture);
    }
    
    // Generate URL for the new profile picture
    $profile_picture_url = getProfilePictureUrl($filename);
    
    // Log the successful upload
    Logger::security('profile_picture_uploaded', [
        'user_id' => $lawyer_id,
        'filename' => $filename,
        'file_size' => $uploaded_file['size']
    ]);
    error_log("Profile picture uploaded successfully for lawyer ID: $lawyer_id, filename: $filename, URL: $profile_picture_url");
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'filename' => $filename,
        'url' => $profile_picture_url,
        'timestamp' => time(),
        'debug' => [
            'lawyer_id' => $lawyer_id,
            'original_filename' => $uploaded_file['name'],
            'file_size' => $uploaded_file['size'],
            'destination_path' => $destination_path,
            'file_exists' => file_exists($destination_path)
        ]
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("Profile picture upload error for lawyer ID $lawyer_id: " . $e->getMessage());
    
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
