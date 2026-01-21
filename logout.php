<?php
/**
 * Unified Logout System
 * Handles logout for both admin and lawyer users
 */

session_start();

// Log the logout event before clearing session
if (isset($_SESSION['user_id'])) {
    require_once 'config/Logger.php';
    Logger::security('user_logout', [
        'user_id' => $_SESSION['user_id'],
        'role' => $_SESSION['user_role'] ?? 'unknown'
    ]);
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to main website
header('Location: login.php');
exit;
?>
