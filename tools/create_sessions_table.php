<?php
/**
 * Create user_sessions table
 */

require_once 'config/database.php';

$pdo = getDBConnection();
if (!$pdo) {
    die("Database connection failed\n");
}

echo "Creating user_sessions table...\n";

try {
    $sql = "
    CREATE TABLE IF NOT EXISTS user_sessions (
        id VARCHAR(128) NOT NULL COMMENT 'SHA-256 hash of session_id()',
        user_id INT(11) NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent VARCHAR(255) NOT NULL,
        status ENUM('active', 'expired', 'logged_out', 'invalid') DEFAULT 'active',
        last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($sql);
    
    echo "✓ Table created successfully!\n";
    
    // Verify table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table verified in database\n";
        
        // Show table structure
        echo "\nTable structure:\n";
        $stmt = $pdo->query("DESCRIBE user_sessions");
        while ($row = $stmt->fetch()) {
            echo "  {$row['Field']} - {$row['Type']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
