<?php
/**
 * Migration Script: Hash Existing Plain Text Passwords
 * 
 * WARNING: This script will convert all plain text passwords to hashed passwords.
 * Make sure to backup your database before running this script.
 * 
 * Usage: php migrations/hash_existing_passwords.php
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Password Hashing Migration ===\n\n";
echo "This script will hash all plain text passwords in the users table.\n";
echo "WARNING: Make sure you have a database backup before proceeding!\n\n";

// Confirm before proceeding
echo "Do you want to continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$confirmation = trim(fgets($handle));
fclose($handle);

if (strtolower($confirmation) !== 'yes') {
    echo "Migration cancelled.\n";
    exit;
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    echo "\nConnected to database successfully.\n";
    
    // Get all users with their current passwords
    $stmt = $pdo->query("SELECT id, username, password FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($users) . " users to process.\n\n";
    
    $updated_count = 0;
    $skipped_count = 0;
    
    foreach ($users as $user) {
        // Check if password is already hashed (bcrypt hashes start with $2y$)
        if (substr($user['password'], 0, 4) === '$2y$') {
            echo "Skipping user '{$user['username']}' - password already hashed\n";
            $skipped_count++;
            continue;
        }
        
        // Hash the plain text password
        $hashed_password = password_hash($user['password'], PASSWORD_BCRYPT);
        
        // Update the user's password
        $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update_stmt->execute([$hashed_password, $user['id']]);
        
        echo "Updated password for user '{$user['username']}'\n";
        $updated_count++;
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Updated: $updated_count users\n";
    echo "Skipped: $skipped_count users (already hashed)\n";
    echo "\nAll passwords have been successfully hashed!\n";
    
} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
