<?php
/**
 * AJAX endpoint for updating consultation status from modal
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$consultation_id = isset($_POST['consultation_id']) ? (int)$_POST['consultation_id'] : 0;
$new_status = $_POST['new_status'] ?? '';
$cancellation_reason = $_POST['cancellation_reason'] ?? 'Lawyer decision';

if (!$consultation_id || !in_array($new_status, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID or status']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Verify consultation belongs to this lawyer or is unassigned
    $check = $pdo->prepare('SELECT c_id as id, c_status as status, lawyer_id FROM consultations WHERE c_id = ? AND (lawyer_id = ? OR lawyer_id IS NULL)');
    $check->execute([$consultation_id, $lawyer_id]);
    $current_consultation = $check->fetch();
    
    if (!$current_consultation) {
        echo json_encode(['success' => false, 'message' => 'Consultation not found or access denied']);
        exit;
    }
    
    $old_status = $current_consultation['status'];
    
    // Validate status transitions
    $allowed_transitions = [
        'pending' => ['confirmed', 'cancelled', 'completed'],
        'confirmed' => ['completed'], // Can only complete, cannot cancel
        'cancelled' => [], // Final state, no changes allowed
        'completed' => []  // Final state, no changes allowed
    ];
    
    // Check if the transition is allowed
    if (!isset($allowed_transitions[$old_status])) {
        echo json_encode(['success' => false, 'message' => 'Invalid current status']);
        exit;
    }
    
    if (!in_array($new_status, $allowed_transitions[$old_status])) {
        $status_messages = [
            'cancelled' => 'Cannot change status - consultation is already cancelled',
            'completed' => 'Cannot change status - consultation is already completed',
            'confirmed' => 'Cannot cancel a confirmed consultation - only completion is allowed'
        ];
        
        $message = $status_messages[$old_status] ?? 'Status transition not allowed';
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
    
    // If trying to change to the same status, skip
    if ($old_status === $new_status) {
        echo json_encode(['success' => false, 'message' => 'Consultation is already ' . $new_status]);
        exit;
    }
    
    // Update status and cancellation reason if applicable
    if ($new_status === 'cancelled') {
        $upd = $pdo->prepare('UPDATE consultations SET c_status = ?, c_cancellation_reason = ? WHERE c_id = ?');
        $upd->execute([$new_status, $cancellation_reason, $consultation_id]);
    } else {
        // Clear cancellation reason if status is not cancelled
        $upd = $pdo->prepare('UPDATE consultations SET c_status = ?, c_cancellation_reason = NULL WHERE c_id = ?');
        $upd->execute([$new_status, $consultation_id]);
    }
    
    if ($upd->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'No changes made to consultation status']);
        exit;
    }
    
    // Send email notifications for status changes
    require_once '../../includes/EmailNotification.php';
    $emailNotification = new EmailNotification($pdo);
    $queued = false;
    $email_type = '';
    
    if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
        $queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
        $email_type = 'Confirmation';
    } elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
        $queued = $emailNotification->notifyAppointmentCancelled($consultation_id, $cancellation_reason);
        $email_type = 'Cancellation';
    } elseif ($new_status === 'completed' && $old_status !== 'completed') {
        // If consultation has no assigned lawyer, assign current lawyer
        if (!$current_consultation['lawyer_id']) {
            $assign_stmt = $pdo->prepare('UPDATE consultations SET lawyer_id = ? WHERE id = ?');
            $assign_stmt->execute([$lawyer_id, $consultation_id]);
        }
        $queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
        $email_type = 'Completion';
    }
    
    // Trigger async email processing if email was queued
    if ($queued) {
        // Use a simple background request to trigger email processing
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'X-Requested-With: XMLHttpRequest',
                'timeout' => 1 // Don't wait for response
            ]
        ]);
        
        // Trigger async email processing (fire and forget)
        @file_get_contents(
            'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . 
            dirname(dirname($_SERVER['REQUEST_URI'])) . '/process_emails_async.php',
            false,
            $context
        );
        
        $message = "Status updated successfully! {$email_type} email sent to client.";
    } else {
        $message = 'Status updated successfully!';
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'new_status' => $new_status,
        'old_status' => $old_status,
        'email_sent' => $queued
    ]);
    
} catch (Exception $e) {
    error_log("Status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}
?>