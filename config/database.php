<?php
/**
 * Database Configuration for Law Firm Consultation System
 * Update these values according to your XAMPP setup
 */

// Database configuration
define('DB_HOST', 'localhost'); // Changed from 'localhost' to fix MariaDB connection
define('DB_PORT', '3306'); // MySQL port - change if different
define('DB_NAME', 'test');
define('DB_USER', 'root');
define('DB_PASS', '');

// Create connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

// Test connection function
function testConnection() {
    $pdo = getDBConnection();
    if ($pdo) {
        echo "Database connection successful!";
        return true;
    } else {
        echo "Database connection failed!";
        return false;
    }
}

// Close connection
function closeConnection($pdo) {
    $pdo = null;
}
?>
