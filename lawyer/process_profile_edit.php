<?php
/**
 * Process Lawyer Profile Edit
 * Handles form submission and updates lawyer profile information
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    header('Location: ../login.php');
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: edit_profile.php');
    exit;
}

require_once '../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check your database configuration.");
    }
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    // Validate and sanitize input data
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $specializations = $_POST['specializations'] ?? [];
    
    // Password change data
    $change_password = isset($_POST['change_password']);
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_new_password = trim($_POST['confirm_new_password'] ?? '');
    
    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($email)) {
        throw new Exception("First name, last name, and email are required fields.");
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Please enter a valid email address.");
    }
    
    if (empty($specializations)) {
        throw new Exception("Please select at least one legal specialization.");
    }
    
    // Password change validation
    if ($change_password) {
        if (empty($current_password)) {
            throw new Exception("Current password is required to change password.");
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
        
        // Verify current password (plain text comparison for development)
        $current_password_stmt = $pdo->prepare("
            SELECT password FROM users 
            WHERE id = ? AND role = 'lawyer'
        ");
        $current_password_stmt->execute([$lawyer_id]);
        $user_data = $current_password_stmt->fetch();
        
        if (!$user_data || $current_password !== $user_data['password']) {
            throw new Exception("Current password is incorrect.");
        }
    }
    
    // Check if email is already taken by another user
    $email_check_stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ? AND role = 'lawyer'
    ");
    $email_check_stmt->execute([$email, $lawyer_id]);
    
    if ($email_check_stmt->fetch()) {
        throw new Exception("This email address is already registered to another lawyer.");
    }
    
    // Update user information in users table
    if ($change_password) {
        // Update profile with new password (plain text for development)
        // Also clear temporary_password flag since user is changing their password
        $update_user_stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?, 
                description = ?,
                password = ?,
                temporary_password = NULL
            WHERE id = ? AND role = 'lawyer'
        ");
        
        $update_result = $update_user_stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone,
            $description,
            $new_password,  // Store plain text password
            $lawyer_id
        ]);
    } else {
        // Update profile without changing password
        $update_user_stmt = $pdo->prepare("
            UPDATE users 
            SET first_name = ?, 
                last_name = ?, 
                email = ?, 
                phone = ?, 
                description = ?
            WHERE id = ? AND role = 'lawyer'
        ");
        
        $update_result = $update_user_stmt->execute([
            $first_name,
            $last_name,
            $email,
            $phone,
            $description,
            $lawyer_id
        ]);
    }
    
    if (!$update_result) {
        throw new Exception("Failed to update profile information.");
    }
    
    // Handle specializations update
    // First, remove all existing specializations for this lawyer
    $delete_specializations_stmt = $pdo->prepare("
        DELETE FROM lawyer_specializations 
        WHERE user_id = ?
    ");
    $delete_specializations_stmt->execute([$lawyer_id]);
    
    // Then, insert the new specializations
    $insert_specialization_stmt = $pdo->prepare("
        INSERT INTO lawyer_specializations (user_id, practice_area_id) 
        VALUES (?, ?)
    ");
    
    foreach ($specializations as $practice_area_id) {
        // Validate that the practice area exists and is active
        $area_check_stmt = $pdo->prepare("
            SELECT id FROM practice_areas 
            WHERE id = ? AND is_active = 1
        ");
        $area_check_stmt->execute([$practice_area_id]);
        
        if ($area_check_stmt->fetch()) {
            $insert_specialization_stmt->execute([$lawyer_id, $practice_area_id]);
        }
    }
    
    // Update session data with new name
    $_SESSION['lawyer_name'] = $first_name . ' ' . $last_name;
    
    // Commit transaction
    $pdo->commit();
    
    // Log the profile update
    $log_message = "Profile updated successfully for lawyer ID: $lawyer_id";
    if ($change_password) {
        $log_message .= " (password changed)";
    }
    error_log($log_message);
    
    // Redirect with success message
    $success_param = $change_password ? 'success=1&password_changed=1' : 'success=1';
    header("Location: edit_profile.php?$success_param");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo) && $pdo && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    // Log the error
    error_log("Profile update error for lawyer ID $lawyer_id: " . $e->getMessage());
    
    // Redirect with error message
    $error_message = urlencode($e->getMessage());
    header("Location: edit_profile.php?error=" . $error_message);
    exit;
}
?>
