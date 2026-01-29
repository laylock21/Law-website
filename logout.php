<?php
/**
 * Unified Logout System
 * Handles logout for both admin and lawyer users
 * Now with database-backed session management
 */

session_start();

require_once 'config/database.php';
require_once 'config/Auth.php';

$pdo = getDBConnection();

if ($pdo) {
    $auth = new Auth($pdo);
    
    // Log the logout event before clearing session
    if (isset($_SESSION['user_id'])) {
        require_once 'config/Logger.php';
        Logger::init('INFO');
        Logger::security('user_logout', [
            'user_id' => $_SESSION['user_id'],
            'role' => $_SESSION['user_role'] ?? 'unknown'
        ]);
    }
    
    // Use Auth logout method (handles database session cleanup)
    $auth->logout();
} else {
    // Fallback if database connection fails
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Redirect to login page
header('Location: login.php');
exit;
?>
