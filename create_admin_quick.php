<?php
/**
 * Quick Admin Account Creator
 * Creates an admin account with predefined credentials
 */

require_once 'config/database.php';

echo "=== Quick Admin Account Creator ===\n\n";

// Admin credentials - CHANGE THESE!
$username = 'admin';
$email = 'admin@lawfirm.com';
$first_name = 'Admin';
$last_name = 'User';
$password = 'admin123'; // Change this to a secure password

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Check if admin already exists
    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check_stmt->execute([$username, $email]);
    $existing_user = $check_stmt->fetch();
    
    if ($existing_user) {
        // Update existing user
        $update_stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, 
                first_name = ?, 
                last_name = ?, 
                email = ?,
                role = 'admin',
                is_active = 1,
                temporary_password = NULL
            WHERE username = ?
        ");
        $update_stmt->execute([$hashed_password, $first_name, $last_name, $email, $username]);
        
        echo "✓ Admin account updated successfully!\n\n";
    } else {
        // Create new admin
        $insert_stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, role, is_active, temporary_password) 
            VALUES (?, ?, ?, ?, ?, 'admin', 1, NULL)
        ");
        $insert_stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
        
        echo "✓ Admin account created successfully!\n\n";
    }
    
    echo "=== Login Credentials ===\n";
    echo "Username: $username\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Role: admin\n";
    echo "\nYou can now login at: login.php\n";
    echo "\n⚠️  IMPORTANT: Change the password after first login!\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
