<?php
/**
 * Session System Test Script
 * Tests the database-backed session management system
 */

require_once 'config/database.php';
require_once 'config/SessionManager.php';

echo "<h1>Session System Test</h1>";
echo "<pre>";

// Get database connection
$pdo = getDBConnection();
if (!$pdo) {
    die("❌ Database connection failed\n");
}
echo "✓ Database connection successful\n\n";

// Check if user_sessions table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_sessions'");
    if ($stmt->rowCount() === 0) {
        die("❌ user_sessions table does not exist. Run migration first!\n");
    }
    echo "✓ user_sessions table exists\n\n";
} catch (Exception $e) {
    die("❌ Error checking table: " . $e->getMessage() . "\n");
}

// Initialize session manager
session_start();
$sessionManager = new SessionManager($pdo);
echo "✓ SessionManager initialized\n\n";

// Test 1: Create a test session
echo "Test 1: Creating test session...\n";
$_SESSION['test_user_id'] = 999;
$_SESSION['test_username'] = 'test_user';

try {
    $result = $sessionManager->createSession(999);
    if ($result) {
        echo "✓ Test session created successfully\n";
        echo "  Session hash: " . ($_SESSION['session_hash'] ?? 'N/A') . "\n\n";
    } else {
        echo "❌ Failed to create test session\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error creating session: " . $e->getMessage() . "\n\n";
}

// Test 2: Validate session
echo "Test 2: Validating session...\n";
try {
    $valid = $sessionManager->validateSession();
    if ($valid) {
        echo "✓ Session validation successful\n\n";
    } else {
        echo "❌ Session validation failed\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error validating session: " . $e->getMessage() . "\n\n";
}

// Test 3: Update session activity
echo "Test 3: Updating session activity...\n";
try {
    $result = $sessionManager->updateSessionActivity();
    if ($result) {
        echo "✓ Session activity updated\n\n";
    } else {
        echo "❌ Failed to update session activity\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error updating activity: " . $e->getMessage() . "\n\n";
}

// Test 4: Get session from database
echo "Test 4: Fetching session from database...\n";
try {
    $session_hash = $_SESSION['session_hash'] ?? null;
    if ($session_hash) {
        $stmt = $pdo->prepare('SELECT * FROM user_sessions WHERE id = ?');
        $stmt->execute([$session_hash]);
        $session = $stmt->fetch();
        
        if ($session) {
            echo "✓ Session found in database\n";
            echo "  User ID: " . $session['user_id'] . "\n";
            echo "  IP Address: " . $session['ip_address'] . "\n";
            echo "  Status: " . $session['status'] . "\n";
            echo "  Created: " . $session['created_at'] . "\n";
            echo "  Expires: " . $session['expires_at'] . "\n\n";
        } else {
            echo "❌ Session not found in database\n\n";
        }
    } else {
        echo "❌ No session hash in PHP session\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error fetching session: " . $e->getMessage() . "\n\n";
}

// Test 5: Cleanup expired sessions
echo "Test 5: Testing cleanup function...\n";
try {
    $expired_count = $sessionManager->cleanupExpiredSessions();
    echo "✓ Cleanup completed\n";
    echo "  Expired sessions cleaned: {$expired_count}\n\n";
} catch (Exception $e) {
    echo "❌ Error during cleanup: " . $e->getMessage() . "\n\n";
}

// Test 6: Get session statistics
echo "Test 6: Getting session statistics...\n";
try {
    $stmt = $pdo->query('
        SELECT 
            status,
            COUNT(*) as count
        FROM user_sessions
        GROUP BY status
    ');
    
    echo "✓ Session statistics:\n";
    while ($row = $stmt->fetch()) {
        echo "  {$row['status']}: {$row['count']}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "❌ Error getting statistics: " . $e->getMessage() . "\n\n";
}

// Test 7: Logout test session
echo "Test 7: Logging out test session...\n";
try {
    $result = $sessionManager->logoutSession();
    if ($result) {
        echo "✓ Test session logged out successfully\n\n";
    } else {
        echo "❌ Failed to logout test session\n\n";
    }
} catch (Exception $e) {
    echo "❌ Error logging out: " . $e->getMessage() . "\n\n";
}

// Test 8: Verify logout
echo "Test 8: Verifying logout...\n";
try {
    $session_hash = $_SESSION['session_hash'] ?? null;
    if ($session_hash) {
        $stmt = $pdo->prepare('SELECT status FROM user_sessions WHERE id = ?');
        $stmt->execute([$session_hash]);
        $session = $stmt->fetch();
        
        if ($session && $session['status'] === 'logged_out') {
            echo "✓ Session status correctly set to 'logged_out'\n\n";
        } else {
            echo "❌ Session status not updated correctly\n\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Error verifying logout: " . $e->getMessage() . "\n\n";
}

echo "=================================\n";
echo "All tests completed!\n";
echo "=================================\n";

echo "</pre>";

// Cleanup
session_destroy();
?>
