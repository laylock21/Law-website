<?php
/**
 * Async Email Processor
 * Processes emails in background without user waiting
 */

// Prevent direct browser access
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    // Allow CLI access for cron jobs
    if (php_sapi_name() !== 'cli') {
        http_response_code(403);
        exit('Access denied');
    }
}

require_once 'config/database.php';
require_once 'includes/EmailNotification.php';

try {
    $pdo = getDBConnection();
    $emailNotification = new EmailNotification($pdo);
    
    // Process emails
    $result = $emailNotification->processPendingNotifications();
    
    // Validate result structure
    if (!is_array($result)) {
        throw new Exception('Invalid result from email processor');
    }
    
    // Ensure required keys exist
    $result['sent'] = $result['sent'] ?? 0;
    $result['failed'] = $result['failed'] ?? 0;
    $result['status'] = $result['status'] ?? 'unknown';
    
    // Return JSON response for AJAX calls
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: application/json');
        echo json_encode($result);
    } else {
        // CLI output for cron jobs
        echo "Processed: {$result['sent']} sent, {$result['failed']} failed\n";
    }
    
} catch (Exception $e) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'sent' => 0,
            'failed' => 0,
            'status' => 'error'
        ]);
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
