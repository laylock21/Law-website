<?php
/**
 * Hash Admin Password
 * Simple script to update admin password with a hashed version
 */

require_once 'config/database.php';

echo "=== Hash Admin Password ===\n\n";

// Get the admin username
echo "Enter admin username (default: admin): ";
$username = trim(fgets(STDIN));
if (empty($username)) {
    $username = 'admin';
}

// Get the new password
echo "Enter new password for '$username': ";
$password = trim(fgets(STDIN));

if (empty($password)) {
    echo "Error: Password cannot be empty!\n";
    exit(1);
}

if (strlen($password) < 6) {
    echo "Warning: Password is less than 6 characters.\n";
    echo "Continue anyway? (yes/no): ";
    $confirm = trim(fgets(STDIN));
    if (strtolower($confirm) !== 'yes') {
        echo "Cancelled.\n";
        exit;
    }
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Check if user exists
    $check_stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE username = ?");
    $check_stmt->execute([$username]);
    $user = $check_stmt->fetch();
    
    if (!$user) {
        echo "\nError: User '$username' not found!\n";
        echo "\nWould you like to create a new admin account? (yes/no): ";
        $create = trim(fgets(STDIN));
        
        if (strtolower($create) === 'yes') {
            echo "Enter email: ";
            $email = trim(fgets(STDIN));
            echo "Enter first name: ";
            $first_name = trim(fgets(STDIN));
            echo "Enter last name: ";
            $last_name = trim(fgets(STDIN));
            
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, first_name, last_name, role, is_active) 
                VALUES (?, ?, ?, ?, ?, 'admin', 1)
            ");
            $insert_stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
            
            echo "\n✓ New admin account created successfully!\n";
            echo "\nUsername: $username\n";
            echo "Email: $email\n";
            echo "Password: [hashed securely]\n";
        } else {
            echo "Cancelled.\n";
        }
        exit;
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Update the password
    $update_stmt = $pdo->prepare("UPDATE users SET password = ?, temporary_password = NULL WHERE username = ?");
    $update_stmt->execute([$hashed_password, $username]);
    
    echo "\n✓ Password updated successfully!\n\n";
    echo "Account Details:\n";
    echo "----------------\n";
    echo "Username: {$user['username']}\n";
    echo "Email: {$user['email']}\n";
    echo "Role: {$user['role']}\n";
    echo "Password: [hashed securely]\n";
    echo "\nYou can now login with your new password.\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
