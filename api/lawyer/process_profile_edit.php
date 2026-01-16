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
    header('Location: ../../lawyer/edit_profile.php');
    exit;
}

require_once '../../config/database.php';

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
    
    // Check if email is already taken by another user
    $email_check_stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE email = ? AND id != ? AND role = 'lawyer'
    ");
    $email_check_stmt->execute([$email, $lawyer_id]);
    
    if ($email_check_stmt->fetch()) {
        throw new Exception("This email address is already registered to another lawyer.");
    }
    
    // Update user information in users table (no password change)
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
    error_log("Profile updated successfully for lawyer ID: $lawyer_id");
    
    // Redirect with success message
    header("Location: ../../lawyer/edit_profile.php?success=1");
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
    header("Location: ../../lawyer/edit_profile.php?error=" . $error_message);
    exit;
}
?>
