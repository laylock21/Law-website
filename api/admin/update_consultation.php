<?php
/**
 * API Endpoint: Update Consultation Details
 * Handles AJAX requests to update consultation information
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
require_once '../../includes/EmailNotification.php';

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['consultation_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$consultation_id = (int)$data['consultation_id'];

try {
    $pdo = getDBConnection();
    
    // Get current consultation data for comparison
    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE c_id = ?");
    $stmt->execute([$consultation_id]);
    $current = $stmt->fetch();
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Consultation not found']);
        exit;
    }
    
    // Build update query based on provided fields
    $updates = [];
    $params = [];
    
    // Client information fields
    if (isset($data['c_full_name'])) {
        $updates[] = "c_full_name = ?";
        $params[] = $data['c_full_name'];
    }
    if (isset($data['c_email'])) {
        $updates[] = "c_email = ?";
        $params[] = $data['c_email'];
    }
    if (isset($data['c_phone'])) {
        $updates[] = "c_phone = ?";
        $params[] = $data['c_phone'];
    }
    if (isset($data['c_practice_area'])) {
        $updates[] = "c_practice_area = ?";
        $params[] = $data['c_practice_area'];
    }
    
    // Case description
    if (isset($data['c_case_description'])) {
        $updates[] = "c_case_description = ?";
        $params[] = $data['c_case_description'];
    }
    
    // Schedule fields
    if (isset($data['lawyer_id'])) {
        $updates[] = "lawyer_id = ?";
        $params[] = $data['lawyer_id'] !== '' ? $data['lawyer_id'] : null;
    }
    if (isset($data['c_consultation_date'])) {
        $updates[] = "c_consultation_date = ?";
        $params[] = $data['c_consultation_date'] !== '' ? $data['c_consultation_date'] : null;
    }
    if (isset($data['c_consultation_time'])) {
        $updates[] = "c_consultation_time = ?";
        $params[] = $data['c_consultation_time'] !== '' ? $data['c_consultation_time'] : null;
    }
    
    // Status fields
    $status_changed = false;
    $new_status = null;
    if (isset($data['c_status'])) {
        $new_status = $data['c_status'];
        $status_changed = ($current['c_status'] !== $new_status);
        $updates[] = "c_status = ?";
        $params[] = $new_status;
        
        // Handle cancellation reason
        if ($new_status === 'cancelled' && isset($data['cancellation_reason'])) {
            $updates[] = "c_cancellation_reason = ?";
            $params[] = $data['cancellation_reason'];
        } elseif ($new_status !== 'cancelled') {
            $updates[] = "c_cancellation_reason = NULL";
        }
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    // Add consultation ID to params
    $params[] = $consultation_id;
    
    // Execute update
    $sql = "UPDATE consultations SET " . implode(", ", $updates) . " WHERE c_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    // Handle email notifications for status changes
    $email_sent = false;
    if ($status_changed && $new_status) {
        $emailNotification = new EmailNotification($pdo);
        
        if ($new_status === 'confirmed' && $current['c_status'] !== 'confirmed') {
            $email_sent = $emailNotification->notifyAppointmentConfirmed($consultation_id);
        } elseif ($new_status === 'cancelled' && $current['c_status'] !== 'cancelled') {
            $cancellation_reason = $data['cancellation_reason'] ?? 'Administrative decision';
            $email_sent = $emailNotification->notifyAppointmentCancelled($consultation_id, $cancellation_reason);
        } elseif ($new_status === 'completed' && $current['c_status'] !== 'completed') {
            $email_sent = $emailNotification->notifyAppointmentCompleted($consultation_id);
        }
        
        // Trigger async email processing if email was queued
        if ($email_sent) {
            // This will be handled by the frontend via fetch to process_emails_async.php
        }
    }
    
    $message = 'Consultation updated successfully';
    if ($email_sent) {
        $message .= ' and notification email queued';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'email_queued' => $email_sent
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
