<?php
/**
 * Remove Profile Picture Handler
 * Handles removal of lawyer profile pictures
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/upload_config.php';

header('Content-Type: application/json');

try {
    $lawyer_id = $_SESSION['lawyer_id'];
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || $input['action'] !== 'remove') {
        throw new Exception('Invalid request');
    }
    
    $pdo = getDBConnection();
    
    // Get current profile picture
    $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ? AND role = 'lawyer'");
    $stmt->execute([$lawyer_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    $current_picture = $user['profile_picture'];
    
    // Remove file from filesystem if it exists
    if ($current_picture && file_exists("../uploads/profile_pictures/" . $current_picture)) {
        unlink("../uploads/profile_pictures/" . $current_picture);
    }
    
    // Update database to remove profile picture reference
    $update_stmt = $pdo->prepare("UPDATE users SET profile_picture = NULL WHERE id = ? AND role = 'lawyer'");
    $update_stmt->execute([$lawyer_id]);
    
    if ($update_stmt->rowCount() === 0) {
        throw new Exception('Failed to update database');
    }
    
    // Return success with default profile picture URL
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture removed successfully',
        'defaultUrl' => getProfilePictureUrl('')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>