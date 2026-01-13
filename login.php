<?php
/**
 * Unified Login System
 * Handles authentication for both admin and lawyer users
 * Automatically routes to appropriate dashboard based on role
 */

session_start();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if ($_SESSION['user_role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } elseif ($_SESSION['user_role'] === 'lawyer') {
        header('Location: lawyer/dashboard.php');
        exit;
    }
}

require_once 'config/database.php';
require_once 'config/Auth.php';

$error_message = '';
$success_message = '';

// Initialize authentication system
$pdo = getDBConnection();
if (!$pdo) {
    $error_message = 'Database connection failed';
} else {
    $auth = new Auth($pdo);
    
    // Generate CSRF token
    $csrf_token = $auth->generateCSRFToken();
    
    // Handle login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $submitted_csrf_token = $_POST['csrf_token'] ?? '';
        
        // Verify CSRF token
        if (!$auth->verifyCSRFToken($submitted_csrf_token)) {
            $error_message = 'Invalid security token. Please try again.';
        } elseif (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password';
        } else {
            // Attempt authentication
            if ($auth->authenticate($username, $password)) {
                // Redirect based on role
                $user = $auth->getCurrentUser();
                if ($user['role'] === 'admin') {
                    header('Location: admin/dashboard.php');
                } elseif ($user['role'] === 'lawyer') {
                    header('Location: lawyer/dashboard.php');
                } else {
                    $error_message = 'Invalid user role';
                }
                exit;
            } else {
                $error_message = 'Invalid username or password';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MD Law</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="login-page">
    <div class="login-image">
        <img src="src/img/placeholdimg1.jpg" alt="Law Office">
    </div>
    <div class="login-container">
        <div class="login-header">
            <h1>MD Law</h1>
            <p>Staff Portal Login</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="login-error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="login-success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="login-form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="login-form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="login-btn">Login to Portal</button>
        </form>
        
        <div class="login-back-link">
            <a href="index.html">← Back to Website</a>
        </div>

        <div class="login-role-info">
            <strong>Note:</strong> You will be automatically redirected to your appropriate dashboard based on your role.
        </div>
    </div>
</body>
</html>
