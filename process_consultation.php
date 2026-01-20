<?php
/**
 * Process Consultation Form Submission
 * Handles the booking consultation form and saves to database
 */

// Include database configuration and error handling
require_once 'config/database.php';
require_once 'config/ErrorHandler.php';
require_once 'config/Logger.php';

// Initialize error handling and logging
ErrorHandler::init();
Logger::init('INFO');

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ErrorHandler::returnJsonError('Method not allowed', 405);
}

// Get form data
$input = json_decode(file_get_contents('php://input'), true);

// If JSON parsing failed, try POST data
if (!$input) {
    $input = $_POST;
}

// Feature: Updated required fields for new form structure
$required_fields = ['fullName', 'email', 'phone', 'service', 'lawyer', 'message'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    ErrorHandler::returnJsonError('Missing required fields: ' . implode(', ', $missing_fields), 400);
}

// Feature: Enhanced sanitization and validation with new field structure
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePhone($phone) {
    return preg_match('/^[0-9]{11}$/', $phone);
}

$full_name = sanitizeInput($input['fullName']);
$email = trim($input['email']);
$phone = trim($input['phone']);
// Server-side enforcement: keep digits only and validate exact length (11)
$phone = preg_replace('/\D+/', '', $phone);
$practice_area = sanitizeInput($input['service']);
$case_description = sanitizeInput($input['message']);
$selected_lawyer = sanitizeInput($input['lawyer']);
$selected_date = !empty($input['date']) ? $input['date'] : null;

// Enhanced validation
$validation_errors = [];

if (strlen($full_name) < 3) {
    $validation_errors[] = 'Full name must be at least 3 characters';
}
if (!validateEmail($email)) {
    $validation_errors[] = 'Invalid email address';
}
if (!validatePhone($phone)) {
    $validation_errors[] = 'Phone number must be exactly 11 digits';
}
if (strlen($case_description) < 10) {
    $validation_errors[] = 'Case description must be at least 10 characters';
}

if (!empty($validation_errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Validation errors: ' . implode(', ', $validation_errors)]);
    exit;
}

// Email validation is already handled in enhanced validation above

// Validate date format if provided
if ($selected_date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

try {
    // Get database connection
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Feature: Get lawyer ID from lawyer name
    $lawyer_id = null;
    if ($selected_lawyer) {
        // Remove "Atty. " prefix if present (frontend sends with prefix, database stores without)
        $lawyer_name_clean = preg_replace('/^Atty\.\s*/i', '', $selected_lawyer);
        
        $lawyer_stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE CONCAT(first_name, ' ', last_name) = ? 
            AND role = 'lawyer' 
            AND is_active = 1
        ");
        $lawyer_stmt->execute([$lawyer_name_clean]);
        $lawyer = $lawyer_stmt->fetch();
        $lawyer_id = $lawyer ? $lawyer['id'] : null;
    }
    
    // Feature: Check appointment limits before accepting new consultation
    if ($lawyer_id && $selected_date) {
        // Get lawyer's max appointments per day
        $limit_stmt = $pdo->prepare("
            SELECT max_appointments 
            FROM lawyer_availability 
            WHERE user_id = ?
        ");
        $limit_stmt->execute([$lawyer_id]);
        $availability = $limit_stmt->fetch();
        
        if ($availability) {
            $max_appointments = $availability['max_appointments'];
            
            // Count existing appointments for this lawyer on the selected date
            $count_stmt = $pdo->prepare("
                SELECT COUNT(*) as appointment_count 
                FROM consultations 
                WHERE lawyer_id = ? 
                AND consultation_date = ? 
                AND status IN ('pending', 'confirmed')
            ");
            $count_stmt->execute([$lawyer_id, $selected_date]);
            $current_count = $count_stmt->fetch()['appointment_count'];
            
            // Check if adding this appointment would exceed the limit
            if ($current_count >= $max_appointments) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => "Sorry, $selected_lawyer has reached the maximum number of appointments ($max_appointments) for $selected_date. Please select a different date or lawyer."
                ]);
                exit;
            }
        }
    }
    
    // Feature: Updated SQL statement with new field structure including consultation date and time
    $sql = "INSERT INTO consultations (full_name, email, phone, practice_area, case_description, selected_lawyer, lawyer_id, selected_date, consultation_date, consultation_time) 
            VALUES (:full_name, :email, :phone, :practice_area, :case_description, :selected_lawyer, :lawyer_id, :selected_date, :consultation_date, :consultation_time)";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
    $stmt->bindParam(':practice_area', $practice_area, PDO::PARAM_STR);
    $stmt->bindParam(':case_description', $case_description, PDO::PARAM_STR);
    $stmt->bindParam(':selected_lawyer', $selected_lawyer, PDO::PARAM_STR);
    $stmt->bindParam(':lawyer_id', $lawyer_id, PDO::PARAM_INT);
    $stmt->bindParam(':selected_date', $selected_date, PDO::PARAM_STR);
    $stmt->bindParam(':consultation_date', $selected_date, PDO::PARAM_STR); // Store the selected date as consultation date
    
    // Get selected time from form data
    $selected_time = $input['selected_time'] ?? null;
    $stmt->bindParam(':consultation_time', $selected_time, PDO::PARAM_STR);
    
    // Execute the statement
    if ($stmt->execute()) {
        $consultation_id = $pdo->lastInsertId();
        
        // Log successful consultation submission
        Logger::userAction('consultation_submitted', null, [
            'consultation_id' => $consultation_id,
            'practice_area' => $practice_area,
            'lawyer' => $selected_lawyer,
            'date' => $selected_date,
            'time' => $selected_time
        ]);
        
        // Send email notification to lawyer(s)
        $email_queued = false;
        try {
            require_once 'includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            $email_queued = $emailNotification->notifyLawyerNewConsultation($consultation_id);
            
            if ($email_queued) {
                error_log("Lawyer notification queued successfully for consultation ID: $consultation_id");
            } else {
                error_log("Failed to queue lawyer notification for consultation ID: $consultation_id");
            }
        } catch (Exception $e) {
            error_log("Failed to send lawyer notification: " . $e->getMessage());
            // Don't fail the consultation submission if email fails
        }
        
        // Feature: Send success response with updated details including time
        echo json_encode([
            'success' => true,
            'message' => 'Consultation request submitted successfully! The assigned lawyer has been notified.',
            'consultation_id' => $consultation_id,
            'email_queued' => $email_queued,
            'details' => [
                'name' => $full_name,
                'practice_area' => $practice_area,
                'lawyer' => $selected_lawyer,
                'date' => $selected_date ?: 'No date selected',
                'time' => $selected_time ? date('g:i A', strtotime($selected_time)) : 'No time selected'
            ]
        ]);
        
        // Optional: Send email notification to admin
        sendAdminNotification($full_name, $email, $practice_area, $selected_lawyer, $selected_date);
        
    } else {
        throw new Exception('Failed to save consultation request');
    }
    
} catch (Exception $e) {
    // Log the error
    Logger::error('Consultation submission failed', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ErrorHandler::returnJsonError('An error occurred while submitting your request. Please try again later.', 500);
}

// Close database connection
if (isset($pdo)) {
    closeConnection($pdo);
}

/**
 * Send email notification to admin (optional)
 */
function sendAdminNotification($name, $email, $practice_area, $lawyer, $date) {
    $to = 'admin@lexandco.com';
    $subject = 'New Consultation Request - ' . $name;
    
    $message = "A new consultation request has been submitted:\n\n";
    $message .= "Name: $name\n";
    $message .= "Email: $email\n";
    $message .= "Practice Area: $practice_area\n";
    $message .= "Preferred Lawyer: $lawyer\n";
    $message .= "Preferred Date: " . ($date ?: 'No date selected') . "\n\n";
    $message .= "Please review and respond to this request.";
    
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    
    // Uncomment the line below to enable email notifications
    // mail($to, $subject, $message, $headers);
}
?>
