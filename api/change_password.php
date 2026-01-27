<?php
/**
 * Change Password API Endpoint
 * Handles password changes for authenticated users
 */

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['current_password']) || !isset($input['new_password']) || !isset($input['confirm_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$current_password = $input['current_password'];
$new_password = $input['new_password'];
$confirm_password = $input['confirm_password'];

// Validate new password
if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit;
}

try {
    require_once '../config/database.php';
    $pdo = getDBConnection();
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Get user ID from session - handle both lawyer and regular user sessions
    $user_id = null;
    if (isset($_SESSION['lawyer_id'])) {
        $user_id = $_SESSION['lawyer_id'];
    } elseif (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        echo json_encode(['success' => false, 'message' => 'User ID not found in session']);
        exit;
    }
    
    // Get current user data
    $stmt = $pdo->prepare("SELECT password, temporary_password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Verify current password using password_verify
    if (!password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }
    
    // Hash the new password using password_hash with bcrypt
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Update password and clear temporary_password flag
    $update_stmt = $pdo->prepare("UPDATE users SET password = ?, temporary_password = NULL WHERE user_id = ?");
    $update_stmt->execute([$hashed_password, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
    
} catch (Exception $e) {
    error_log("Password change error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while changing password']);
}
?>