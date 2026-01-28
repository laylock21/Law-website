<?php
/**
 * Process Consultation Form Submission
 * Handles the booking consultation form and saves to database
 */

// Include database configuration and error handling
require_once '../config/database.php';
require_once '../config/ErrorHandler.php';
require_once '../config/Logger.php';

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

    $consultations_columns_stmt = $pdo->query("DESCRIBE consultations");
    $consultations_columns_rows = $consultations_columns_stmt ? $consultations_columns_stmt->fetchAll() : [];
    $consultations_columns = array_map(function($r) { return $r['Field']; }, $consultations_columns_rows);

    $consultation_date_column = in_array('consultation_date', $consultations_columns, true)
        ? 'consultation_date'
        : (in_array('c_consultation_date', $consultations_columns, true) ? 'c_consultation_date' : null);

    $consultation_time_column = in_array('consultation_time', $consultations_columns, true)
        ? 'consultation_time'
        : (in_array('c_consultation_time', $consultations_columns, true) ? 'c_consultation_time' : null);

    $consultation_status_column = in_array('c_status', $consultations_columns, true)
        ? 'c_status'
        : (in_array('status', $consultations_columns, true) ? 'status' : null);

    $full_name_column = in_array('c_full_name', $consultations_columns, true)
        ? 'c_full_name'
        : (in_array('full_name', $consultations_columns, true) ? 'full_name' : null);

    $email_column = in_array('c_email', $consultations_columns, true)
        ? 'c_email'
        : (in_array('email', $consultations_columns, true) ? 'email' : null);

    $phone_column = in_array('c_phone', $consultations_columns, true)
        ? 'c_phone'
        : (in_array('phone', $consultations_columns, true) ? 'phone' : null);

    $message_description_column = in_array('case_description', $consultations_columns, true)
        ? 'case_description'
        : (in_array('c_case_description', $consultations_columns, true)
            ? 'c_case_description'
            : (in_array('c_case_description_old', $consultations_columns, true) ? 'c_case_description_old' : null));

    $service_description_column = null;
    if (in_array('c_case_description', $consultations_columns, true) && in_array('case_description', $consultations_columns, true)) {
        $service_description_column = 'c_case_description';
    }

    $practice_area_column = in_array('c_practice_area', $consultations_columns, true)
        ? 'c_practice_area'
        : (in_array('practice_area', $consultations_columns, true) ? 'practice_area' : null);

    $selected_lawyer_column = in_array('c_selected_lawyer', $consultations_columns, true)
        ? 'c_selected_lawyer'
        : (in_array('selected_lawyer', $consultations_columns, true) ? 'selected_lawyer' : null);

    $selected_date_column = in_array('c_selected_date', $consultations_columns, true)
        ? 'c_selected_date'
        : (in_array('selected_date', $consultations_columns, true) ? 'selected_date' : null);

    if ($consultation_date_column === null || $consultation_time_column === null || $consultation_status_column === null ||
        $full_name_column === null || $email_column === null || $phone_column === null || $message_description_column === null) {
        throw new Exception('Consultations table schema mismatch (missing date/time/status columns)');
    }
    
    // Feature: Get lawyer ID from lawyer name
    $lawyer_id = null;
    if ($selected_lawyer) {
        // Clean the lawyer name - remove all common prefixes
        $lawyer_name_clean = $selected_lawyer;
        $prefixes = ['Atty. Jr.', 'Atty.', 'Esq.', 'Mr.', 'Ms.', 'Mrs.', 'Dr.'];
        
        // Remove prefixes (try longest first to avoid partial matches)
        foreach ($prefixes as $prefix) {
            $lawyer_name_clean = preg_replace('/^' . preg_quote($prefix, '/') . '\s+/i', '', $lawyer_name_clean);
        }
        $lawyer_name_clean = trim($lawyer_name_clean);
        
        // Try multiple matching strategies
        $lawyer_stmt = $pdo->prepare("
            SELECT u.user_id as id FROM users u
            INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
            WHERE (
                lp.lp_fullname = ?
                OR CONCAT(COALESCE(lp.lawyer_prefix, ''), ' ', lp.lp_fullname) = ?
                OR CONCAT(COALESCE(lp.lawyer_prefix, ''), lp.lp_fullname) = ?
                OR lp.lp_fullname = ?
            )
            AND u.role = 'lawyer' 
            AND u.is_active = 1
            LIMIT 1
        ");
        $lawyer_stmt->execute([
            $selected_lawyer,           // Try exact match with prefix
            $selected_lawyer,           // Try with space between prefix and name
            $selected_lawyer,           // Try without space
            $lawyer_name_clean          // Try cleaned name without prefix
        ]);
        $lawyer = $lawyer_stmt->fetch();
        $lawyer_id = $lawyer ? $lawyer['id'] : null;
        
        // Log if lawyer not found for debugging
        if (!$lawyer_id) {
            error_log("WARNING: Could not find lawyer for name: '$selected_lawyer' (cleaned: '$lawyer_name_clean')");
        }
    }
    
    // Feature: Check appointment limits before accepting new consultation
    if ($lawyer_id && $selected_date) {
        // Get lawyer's max appointments per day
        $limit_stmt = $pdo->prepare("
            SELECT max_appointments 
            FROM lawyer_availability 
            WHERE lawyer_id = ?
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
                AND {$consultation_date_column} = ? 
                AND {$consultation_status_column} IN ('pending', 'confirmed')
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
    $insert_columns = "{$full_name_column}, {$email_column}, {$phone_column}, {$message_description_column}, lawyer_id";
    $insert_values = ":full_name, :email, :phone, :case_description, :lawyer_id";

    if ($practice_area_column !== null) {
        $insert_columns .= ", {$practice_area_column}";
        $insert_values .= ", :practice_area";
    } elseif ($service_description_column !== null) {
        $insert_columns .= ", {$service_description_column}";
        $insert_values .= ", :service_description";
    }

    if ($selected_lawyer_column !== null) {
        $insert_columns .= ", {$selected_lawyer_column}";
        $insert_values .= ", :selected_lawyer";
    }

    if ($selected_date_column !== null) {
        $insert_columns .= ", {$selected_date_column}";
        $insert_values .= ", :selected_date";
    }

    $insert_columns .= ", {$consultation_date_column}, {$consultation_time_column}";
    $insert_values .= ", :consultation_date, :consultation_time";

    $sql = "INSERT INTO consultations ({$insert_columns}) VALUES ({$insert_values})";
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':phone', $phone, PDO::PARAM_STR);
    $stmt->bindParam(':case_description', $case_description, PDO::PARAM_STR);
    $stmt->bindParam(':lawyer_id', $lawyer_id, PDO::PARAM_INT);

    if ($practice_area_column !== null) {
        $stmt->bindParam(':practice_area', $practice_area, PDO::PARAM_STR);
    } elseif ($service_description_column !== null) {
        $stmt->bindParam(':service_description', $practice_area, PDO::PARAM_STR);
    }

    if ($selected_lawyer_column !== null) {
        $stmt->bindParam(':selected_lawyer', $selected_lawyer, PDO::PARAM_STR);
    }

    if ($selected_date_column !== null) {
        $stmt->bindParam(':selected_date', $selected_date, PDO::PARAM_STR);
    }

    $stmt->bindParam(':consultation_date', $selected_date, PDO::PARAM_STR);
    
    // Get selected time from form data. Frontend may send a range like "10:00 AM - 1:00 PM".
    $selected_time_raw = $input['selected_time'] ?? null;
    $selected_time = null;
    if (!empty($selected_time_raw)) {
        $time_part = $selected_time_raw;
        if (strpos($time_part, '-') !== false) {
            $parts = explode('-', $time_part, 2);
            $time_part = trim($parts[0]);
        }
        $ts = strtotime($time_part);
        if ($ts !== false) {
            $selected_time = date('H:i:s', $ts);
        }
    }
    $stmt->bindParam(':consultation_time', $selected_time, PDO::PARAM_STR);
    
    // Execute the statement
    if ($stmt->execute()) {
        $consultation_id = $pdo->lastInsertId();
        
        // Log successful consultation submission
        Logger::security('consultation_booked', [
            'consultation_id' => $consultation_id,
            'practice_area' => $practice_area,
            'lawyer' => $selected_lawyer,
            'lawyer_id' => $lawyer_id,
            'date' => $selected_date,
            'time' => $selected_time,
            'client_email' => $email
        ]);
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
            require_once '../includes/EmailNotification.php';
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
