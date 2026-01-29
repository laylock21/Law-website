<?php
require_once 'config/database.php';

$pdo = getDBConnection();
$stmt = $pdo->query("SELECT id, username, email, role, is_active FROM users WHERE role = 'admin'");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Current Admin Accounts:\n";
echo "======================\n\n";

if (empty($admins)) {
    echo "No admin accounts found!\n";
} else {
    foreach ($admins as $admin) {
        echo "ID: {$admin['id']}\n";
        echo "Username: {$admin['username']}\n";
        echo "Email: {$admin['email']}\n";
        echo "Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
        echo "---\n";
    }
}
?>
