<?php
/**
 * AJAX endpoint for bulk updating consultation statuses
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Fallback to POST data if JSON parsing fails
    $input = $_POST;
}

if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'No input data provided']);
    exit;
}

$consultation_ids = $input['consultation_ids'] ?? [];
$new_status = $input['new_status'] ?? '';
$cancellation_reason = $input['cancellation_reason'] ?? 'Bulk action by lawyer';

// Validate input
if (empty($consultation_ids) || !is_array($consultation_ids)) {
    echo json_encode(['success' => false, 'message' => 'No consultation IDs provided']);
    exit;
}

if (!in_array($new_status, ['pending', 'confirmed', 'cancelled', 'completed'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Sanitize consultation IDs
$consultation_ids = array_map('intval', $consultation_ids);
$consultation_ids = array_filter($consultation_ids, function($id) { return $id > 0; });

if (empty($consultation_ids)) {
    echo json_encode(['success' => false, 'message' => 'No valid consultation IDs provided']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Log the bulk operation for debugging
    error_log("Bulk status update: " . count($consultation_ids) . " consultations to '$new_status' by lawyer $lawyer_id");
    
    // Start transaction for atomicity
    $pdo->beginTransaction();
    
    $results = [
        'success_count' => 0,
        'skipped_count' => 0,
        'error_count' => 0,
        'skipped_reasons' => [],
        'error_reasons' => [],
        'updated_consultations' => []
    ];
    
    // Status transition rules
    $allowed_transitions = [
        'pending' => ['confirmed', 'cancelled', 'completed'],
        'confirmed' => ['completed'], // Can only complete, cannot cancel
        'cancelled' => [], // Final state, no changes allowed
        'completed' => []  // Final state, no changes allowed
    ];
    
    // Process each consultation
    foreach ($consultation_ids as $consultation_id) {
        try {
            // Verify consultation belongs to this lawyer or is unassigned
            $check = $pdo->prepare('SELECT c_id as id, c_status as status, lawyer_id FROM consultations WHERE c_id = ? AND (lawyer_id = ? OR lawyer_id IS NULL)');
            $check->execute([$consultation_id, $lawyer_id]);
            $current_consultation = $check->fetch();
            
            if (!$current_consultation) {
                $results['skipped_count']++;
                $results['skipped_reasons'][] = "#$consultation_id: Not found or access denied";
                continue;
            }
            
            $old_status = $current_consultation['status'];
            
            // Check if the transition is allowed
            if (!isset($allowed_transitions[$old_status])) {
                $results['skipped_count']++;
                $results['skipped_reasons'][] = "#$consultation_id: Invalid current status";
                continue;
            }
            
            if (!in_array($new_status, $allowed_transitions[$old_status])) {
                $status_messages = [
                    'cancelled' => 'Already cancelled',
                    'completed' => 'Already completed',
                    'confirmed' => 'Cannot cancel confirmed consultation'
                ];
                
                $message = $status_messages[$old_status] ?? 'Status transition not allowed';
                $results['skipped_count']++;
                $results['skipped_reasons'][] = "#$consultation_id: $message";
                continue;
            }
            
            // If trying to change to the same status, skip
            if ($old_status === $new_status) {
                $results['skipped_count']++;
                $results['skipped_reasons'][] = "#$consultation_id: Already $new_status";
                continue;
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
            
            if ($upd->rowCount() > 0) {
                $results['success_count']++;
                $results['updated_consultations'][] = [
                    'id' => $consultation_id,
                    'old_status' => $old_status,
                    'new_status' => $new_status
                ];
                
                // If completing consultation and no assigned lawyer, assign current lawyer
                if ($new_status === 'completed' && !$current_consultation['lawyer_id']) {
                    $assign_stmt = $pdo->prepare('UPDATE consultations SET lawyer_id = ? WHERE c_id = ?');
                    $assign_stmt->execute([$lawyer_id, $consultation_id]);
                }
            } else {
                $results['skipped_count']++;
                $results['skipped_reasons'][] = "#$consultation_id: No changes made";
            }
            
        } catch (Exception $e) {
            $results['error_count']++;
            $results['error_reasons'][] = "#$consultation_id: " . $e->getMessage();
        }
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Queue email notifications for successfully updated consultations
    $email_queued = false;
    if ($results['success_count'] > 0) {
        require_once '../../vendor/autoload.php';
        require_once '../../includes/EmailNotification.php';
        $emailNotification = new EmailNotification($pdo);
        
        foreach ($results['updated_consultations'] as $consultation) {
            $consultation_id = $consultation['id'];
            $old_status = $consultation['old_status'];
            
            try {
                if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
                    $emailNotification->notifyAppointmentConfirmed($consultation_id);
                    $email_queued = true;
                } elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
                    $emailNotification->notifyAppointmentCancelled($consultation_id, $cancellation_reason);
                    $email_queued = true;
                } elseif ($new_status === 'completed' && $old_status !== 'completed') {
                    $emailNotification->notifyAppointmentCompleted($consultation_id);
                    $email_queued = true;
                }
            } catch (Exception $e) {
                error_log("Email notification error for consultation $consultation_id: " . $e->getMessage());
            }
        }
    }
    
    // Build response message
    $message_parts = [];
    if ($results['success_count'] > 0) {
        $action_name = $new_status === 'confirmed' ? 'confirmed' : ($new_status === 'cancelled' ? 'cancelled' : 'completed');
        $message_parts[] = "✅ Successfully $action_name {$results['success_count']} consultation(s)";
    }
    if ($results['skipped_count'] > 0) {
        $message_parts[] = "⚠️ {$results['skipped_count']} consultation(s) skipped";
    }
    if ($results['error_count'] > 0) {
        $message_parts[] = "❌ {$results['error_count']} error(s) occurred";
    }
    
    $response = [
        'success' => $results['success_count'] > 0,
        'message' => implode('. ', $message_parts),
        'results' => $results,
        'email_queued' => $email_queued
    ];
    
    // Trigger async email processing if emails were queued
    if ($email_queued) {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'X-Requested-With: XMLHttpRequest',
                'timeout' => 1
            ]
        ]);
        
        @file_get_contents(
            'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . 
            dirname(dirname($_SERVER['REQUEST_URI'])) . '/process_emails_async.php',
            false,
            $context
        );
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    
    error_log("Bulk status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing bulk update: ' . $e->getMessage()
    ]);
}
?>