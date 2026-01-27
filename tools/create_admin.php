<?php
/**
 * Create or Update Admin Account
 * 
 * This script creates a new admin account or updates an existing one with a hashed password.
 * 
 * Usage: php create_admin.php
 */

require_once 'config/database.php';

echo "=== Admin Account Creator ===\n\n";

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    echo "Connected to database successfully.\n\n";
    
    // Get admin details
    echo "Enter admin username: ";
    $username = trim(fgets(STDIN));
    
    echo "Enter admin email: ";
    $email = trim(fgets(STDIN));
    
    echo "Enter admin phone (optional): ";
    $phone = trim(fgets(STDIN));
    
    echo "Enter admin password: ";
    $password = trim(fgets(STDIN));
    
    // Validate input
    if (empty($username) || empty($email) || empty($password)) {
        throw new Exception("Username, email, and password are required!");
    }
    
    if (strlen($password) < 8) {
        throw new Exception("Password must be at least 8 characters long!");
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    // Check if admin already exists
    $check_stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $check_stmt->execute([$username, $email]);
    $existing_user = $check_stmt->fetch();
    
    if ($existing_user) {
        echo "\nUser with this username or email already exists.\n";
        echo "Do you want to update their password? (yes/no): ";
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) === 'yes') {
            // Update existing user
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, 
                    email = ?,
                    phone = ?,
                    role = 'admin',
                    is_active = 1,
                    temporary_password = NULL
                WHERE username = ?
            ");
            $update_stmt->execute([$hashed_password, $email, $phone ?: null, $username]);
            
            echo "\n✓ Admin account updated successfully!\n";
        } else {
            echo "\nOperation cancelled.\n";
            exit;
        }
    } else {
        // Create new admin
        $insert_stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, phone, role, is_active, temporary_password) 
            VALUES (?, ?, ?, ?, 'admin', 1, NULL)
        ");
        $insert_stmt->execute([$username, $email, $hashed_password, $phone ?: null]);
        
        echo "\n✓ Admin account created successfully!\n";
    }
    
    echo "\n=== Login Credentials ===\n";
    echo "Username: $username\n";
    echo "Email: $email\n";
    echo "Password: [the password you entered]\n";
    echo "Role: admin\n";
    echo "\nYou can now login at: login.php\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
