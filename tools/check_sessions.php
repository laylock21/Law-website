<?php
/**
 * Check sessions in database
 */

require_once 'config/database.php';

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed\n");
}

echo "Current sessions in database:\n\n";

try {
    $stmt = $pdo->query('
        SELECT 
            s.id,
            s.user_id,
            s.ip_address,
            s.status,
            s.last_activity,
            s.created_at,
            s.expires_at,
            u.username,
            u.first_name,
            u.last_name
        FROM user_sessions s
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.created_at DESC
        LIMIT 20
    ');
    
    $sessions = $stmt->fetchAll();
    
    if (empty($sessions)) {
        echo "No sessions found.\n";
    } else {
        foreach ($sessions as $session) {
            echo "Session ID: " . substr($session['id'], 0, 16) . "...\n";
            echo "  User: " . ($session['username'] ?? 'N/A') . " (ID: {$session['user_id']})\n";
            echo "  IP: {$session['ip_address']}\n";
            echo "  Status: {$session['status']}\n";
            echo "  Created: {$session['created_at']}\n";
            echo "  Expires: {$session['expires_at']}\n";
            echo "  Last Activity: {$session['last_activity']}\n";
            echo "\n";
        }
    }
    
    // Statistics
    echo "\n=== Statistics ===\n";
    $stmt = $pdo->query('
        SELECT status, COUNT(*) as count
        FROM user_sessions
        GROUP BY status
    ');
    
    while ($row = $stmt->fetch()) {
        echo "{$row['status']}: {$row['count']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
