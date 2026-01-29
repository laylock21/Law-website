<?php
/**
 * Diagnostic script to check lawyer data
 */

require_once '../config/database.php';

try {
    $pdo = getDBConnection();
    
    echo "<h2>Database Diagnostics</h2>";
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $columns = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    // Check all users
    echo "<h3>All Users:</h3>";
    $users = $pdo->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
    // Count lawyers
    echo "<h3>Lawyer Counts:</h3>";
    $total = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer'")->fetchColumn();
    echo "Total lawyers: $total<br>";
    
    $active = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer' AND is_active = 1")->fetchColumn();
    echo "Active lawyers: $active<br>";
    
    // Check lawyer_profile table
    echo "<h3>Lawyer Profile Table:</h3>";
    $profiles = $pdo->query("SELECT lawyer_id, lp_fullname FROM lawyer_profile")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($profiles);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
