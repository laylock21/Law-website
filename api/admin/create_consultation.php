<?php
/**
 * Admin API - Create Consultation
 * Handles manual consultation creation by admin
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once '../../config/database.php';
require_once '../../config/ErrorHandler.php';
require_once '../../config/Logger.php';

// Initialize error handling and logging
ErrorHandler::init();
Logger::init('INFO');

// Set headers for JSON response
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$practiceArea = trim($_POST['practiceArea'] ?? '');
$caseDescription = trim($_POST['caseDescription'] ?? '');
$lawyerId = (int)($_POST['lawyer'] ?? 0);
$lawyerName = trim($_POST['lawyerName'] ?? '');
$consultationDate = trim($_POST['consultationDate'] ?? '');
$consultationTime = trim($_POST['consultationTime'] ?? '');
$status = trim($_POST['status'] ?? 'pending');

// Validate required fields
$errors = [];

if (empty($fullName) || strlen($fullName) < 3) {
    $errors[] = 'Full name must be at least 3 characters';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email address is required';
}

if (empty($phone) || !preg_match('/^[0-9]{11}$/', $phone)) {
    $errors[] = 'Phone number must be exactly 11 digits';
}

if (empty($practiceArea)) {
    $errors[] = 'Practice area is required';
}

if (empty($caseDescription) || strlen($caseDescription) < 10) {
    $errors[] = 'Case description must be at least 10 characters';
}

if ($lawyerId <= 0) {
    $errors[] = 'Valid lawyer selection is required';
}

if (empty($consultationDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $consultationDate)) {
    $errors[] = 'Valid consultation date is required';
}

if (empty($consultationTime) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $consultationTime)) {
    $errors[] = 'Valid consultation time is required';
}

// Normalize time format to HH:MM:SS
if (!empty($consultationTime) && preg_match('/^\d{2}:\d{2}$/', $consultationTime)) {
    $consultationTime .= ':00'; // Add seconds if not present
}

if (!in_array($status, ['pending', 'confirmed'])) {
    $status = 'pending';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Verify lawyer exists and is active
    $lawyer_check = $pdo->prepare("
        SELECT u.user_id, lp.lp_fullname 
        FROM users u
        INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        WHERE u.user_id = ? AND u.role = 'lawyer' AND u.is_active = 1
    ");
    $lawyer_check->execute([$lawyerId]);
    $lawyer = $lawyer_check->fetch();
    
    if (!$lawyer) {
        throw new Exception('Selected lawyer is not available');
    }
    
    // Check if date is not in the past
    $today = date('Y-m-d');
    if ($consultationDate < $today) {
        throw new Exception('Consultation date cannot be in the past');
    }
    
    // Check lawyer's appointment limit for the selected date
    $limit_stmt = $pdo->prepare("
        SELECT max_appointments 
        FROM lawyer_availability 
        WHERE lawyer_id = ?
        LIMIT 1
    ");
    $limit_stmt->execute([$lawyerId]);
    $availability = $limit_stmt->fetch();
    
    if ($availability) {
        $max_appointments = $availability['max_appointments'];
        
        // Count existing appointments for this lawyer on the selected date
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) as appointment_count 
            FROM consultations 
            WHERE lawyer_id = ? 
            AND c_consultation_date = ? 
            AND c_status IN ('pending', 'confirmed')
        ");
        $count_stmt->execute([$lawyerId, $consultationDate]);
        $current_count = $count_stmt->fetch()['appointment_count'];
        
        // Check if adding this appointment would exceed the limit
        if ($current_count >= $max_appointments) {
            throw new Exception("Lawyer has reached the maximum number of appointments ($max_appointments) for this date");
        }
    }
    
    // Insert consultation
    $sql = "INSERT INTO consultations (
        c_full_name, 
        c_email, 
        c_phone, 
        c_practice_area, 
        c_case_description, 
        c_selected_lawyer, 
        c_selected_date, 
        lawyer_id, 
        c_consultation_date, 
        c_consultation_time, 
        c_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $practiceArea,
        $caseDescription,
        $lawyerName,
        $consultationDate,
        $lawyerId,
        $consultationDate,
        $consultationTime,
        $status
    ]);
    
    $consultationId = $pdo->lastInsertId();
    
    // Log the action
    Logger::userAction('admin_created_consultation', $_SESSION['user_id'] ?? null, [
        'consultation_id' => $consultationId,
        'client_name' => $fullName,
        'lawyer_id' => $lawyerId,
        'date' => $consultationDate,
        'time' => $consultationTime,
        'status' => $status
    ]);
    
    // Send email notification to lawyer
    try {
        require_once '../../vendor/autoload.php';
        require_once '../../includes/EmailNotification.php';
        $emailNotification = new EmailNotification($pdo);
        $emailNotification->notifyLawyerNewConsultation($consultationId);
    } catch (Exception $e) {
        error_log("Failed to send lawyer notification: " . $e->getMessage());
        // Don't fail the consultation creation if email fails
    }
    
    // Send confirmation email to client if status is confirmed
    if ($status === 'confirmed') {
        try {
            $emailNotification->notifyAppointmentConfirmed($consultationId);
        } catch (Exception $e) {
            error_log("Failed to send client confirmation: " . $e->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Consultation created successfully',
        'consultation_id' => $consultationId
    ]);
    
} catch (Exception $e) {
    Logger::error('Admin consultation creation failed', [
        'error' => $e->getMessage(),
        'admin_id' => $_SESSION['user_id'] ?? null
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($pdo)) {
    closeConnection($pdo);
}
?>
