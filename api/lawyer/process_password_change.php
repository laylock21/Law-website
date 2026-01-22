<?php
/**
 * Process Password Change
 * Handles password change separately from profile updates
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    header('Location: ../login.php');
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../lawyer/edit_profile.php');
    exit;
}

require_once '../../config/database.php';
require_once '../../config/Logger.php';

$lawyer_id = $_SESSION['lawyer_id'];

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check your database configuration.");
    }
    
    // Get password data
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_new_password = trim($_POST['confirm_new_password'] ?? '');
    
    // Validation
    if (empty($current_password)) {
        throw new Exception("Current password is required.");
    }
    
    if (empty($new_password)) {
        throw new Exception("New password is required.");
    }
    
    if (strlen($new_password) < 8) {
        throw new Exception("New password must be at least 8 characters long.");
    }
    
    if ($new_password !== $confirm_new_password) {
        throw new Exception("New passwords do not match.");
    }
    
    // Verify current password using password_verify
    $current_password_stmt = $pdo->prepare("
        SELECT password FROM users 
        WHERE id = ? AND role = 'lawyer'
    ");
    $current_password_stmt->execute([$lawyer_id]);
    $user_data = $current_password_stmt->fetch();
    
    if (!$user_data || !password_verify($current_password, $user_data['password'])) {
        throw new Exception("Current password is incorrect.");
    }
    
    // Hash the new password using password_hash with bcrypt
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    
    // Update password and clear temporary_password flag
    $update_password_stmt = $pdo->prepare("
        UPDATE users 
        SET password = ?,
            temporary_password = NULL
        WHERE id = ? AND role = 'lawyer'
    ");
    
    $update_result = $update_password_stmt->execute([
        $hashed_password,
        $lawyer_id
    ]);
    
    if (!$update_result) {
        throw new Exception("Failed to update password.");
    }
    
    // Log the password change
    Logger::security('password_changed', [
        'user_id' => $lawyer_id,
        'role' => 'lawyer'
    ]);
    error_log("Password changed successfully for lawyer ID: $lawyer_id");
    
    // Redirect with success message
    header("Location: ../../lawyer/edit_profile.php?success=1&password_changed=1");
    exit;
    
} catch (Exception $e) {
    // Log the error
    error_log("Password change error for lawyer ID $lawyer_id: " . $e->getMessage());
    
    // Redirect with error message
    $error_message = urlencode($e->getMessage());
    header("Location: ../../lawyer/edit_profile.php?error=" . $error_message);
    exit;
}
?>
