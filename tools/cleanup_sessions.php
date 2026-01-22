<?php
/**
 * Session Cleanup Script
 * Run this periodically (via cron job) to clean up expired and old sessions
 * 
 * Usage:
 * - Via cron: php cleanup_sessions.php
 * - Via browser: cleanup_sessions.php?key=YOUR_SECRET_KEY
 */

require_once 'config/database.php';
require_once 'config/SessionManager.php';

// Security key for browser access (change this!)
define('CLEANUP_KEY', 'change_this_secret_key_123');

// Check if running from command line or browser
$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Browser access - require security key
    $provided_key = $_GET['key'] ?? '';
    if ($provided_key !== CLEANUP_KEY) {
        http_response_code(403);
        die('Unauthorized access');
    }
}

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed\n");
}

// Initialize session manager
$sessionManager = new SessionManager($pdo);

echo "Starting session cleanup...\n";

// Clean up expired sessions
$expired_count = $sessionManager->cleanupExpiredSessions();
echo "Expired sessions cleaned: {$expired_count}\n";

// Delete old sessions (older than 30 days)
$deleted_count = $sessionManager->deleteOldSessions(30);
echo "Old sessions deleted: {$deleted_count}\n";

// Get statistics
try {
    $stmt = $pdo->query('
        SELECT 
            status,
            COUNT(*) as count
        FROM user_sessions
        GROUP BY status
    ');
    
    echo "\nCurrent session statistics:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['status']}: {$row['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\nCleanup completed successfully!\n";
?>
