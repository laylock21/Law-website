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
require_once '../../config/Logger.php';

Logger::init('INFO');

$lawyer_id = $_SESSION['lawyer_id'];

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed. Please check your database configuration.");
    }
    
    // Start transaction for data consistency
    $pdo->beginTransaction();
    
    // Determine which form was submitted
    $form_type = $_POST['form_type'] ?? 'personal_info';
    
    $fields_updated = [];
    
    // Handle Personal Information Form
    if ($form_type === 'personal_info') {
        // Validate and sanitize input data
        $lawyer_prefix = trim($_POST['lawyer_prefix'] ?? '');
        $fullname = trim($_POST['fullname'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Basic validation
        if (empty($fullname) || empty($email)) {
            throw new Exception("Full name and email are required fields.");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }
        
        // Check if email is already taken by another user
        $email_check_stmt = $pdo->prepare("
            SELECT user_id FROM users 
            WHERE email = ? AND user_id != ? AND role = 'lawyer'
        ");
        $email_check_stmt->execute([$email, $lawyer_id]);
        
        if ($email_check_stmt->fetch()) {
            throw new Exception("This email address is already registered to another lawyer.");
        }
        
        // Update user information in users table
        $update_user_stmt = $pdo->prepare("
            UPDATE users 
            SET email = ?, 
                phone = ?
            WHERE user_id = ? AND role = 'lawyer'
        ");
        
        $update_result = $update_user_stmt->execute([
            $email,
            $phone,
            $lawyer_id
        ]);
        
        if (!$update_result) {
            throw new Exception("Failed to update profile information.");
        }
        
        // Update lawyer_profile table
        // Store fullname without prefix (prefix is stored separately)
        $update_profile_stmt = $pdo->prepare("
            UPDATE lawyer_profile 
            SET lawyer_prefix = ?,
                lp_fullname = ?,
                lp_description = ?
            WHERE lawyer_id = ?
        ");
        
        $update_profile_result = $update_profile_stmt->execute([
            $lawyer_prefix,
            $fullname,
            $description,
            $lawyer_id
        ]);
        
        if (!$update_profile_result) {
            throw new Exception("Failed to update lawyer profile.");
        }
        
        // Update session data with new name
        $_SESSION['lawyer_name'] = $fullname;
        
        $fields_updated = ['lawyer_prefix', 'fullname', 'email', 'phone', 'description'];
    }
    
    // Handle Specializations Form
    elseif ($form_type === 'specializations') {
        $specializations = $_POST['specializations'] ?? [];
        
        // Validate specializations
        if (empty($specializations)) {
            throw new Exception("Please select at least one legal specialization.");
        }
        
        // Handle specializations update
        // First, remove all existing specializations for this lawyer
        $delete_specializations_stmt = $pdo->prepare("
            DELETE FROM lawyer_specializations 
            WHERE lawyer_id = ?
        ");
        $delete_specializations_stmt->execute([$lawyer_id]);
        
        // Then, insert the new specializations
        $insert_specialization_stmt = $pdo->prepare("
            INSERT INTO lawyer_specializations (lawyer_id, pa_id) 
            VALUES (?, ?)
        ");
        
        foreach ($specializations as $practice_area_id) {
            // Validate that the practice area exists and is active
            $area_check_stmt = $pdo->prepare("
                SELECT pa_id FROM practice_areas 
                WHERE pa_id = ? AND is_active = 1
            ");
            $area_check_stmt->execute([$practice_area_id]);
            
            if ($area_check_stmt->fetch()) {
                $insert_specialization_stmt->execute([$lawyer_id, $practice_area_id]);
            }
        }
        
        $fields_updated = ['specializations'];
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log the profile update
    Logger::security('profile_updated', [
        'user_id' => $lawyer_id,
        'role' => 'lawyer',
        'form_type' => $form_type,
        'fields_updated' => $fields_updated
    ]);
    error_log("Profile updated successfully for lawyer ID: $lawyer_id (form: $form_type)");
    
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
