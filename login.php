<?php
/**
 * Unified Login System
 * Handles authentication for both admin and lawyer users
 * Automatically routes to appropriate dashboard based on role
 */

// Enable error display for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
        
        // Check if locked out
        if ($auth->isLockedOut()) {
            $lockout_info = $auth->getLockoutTimeRemaining();
            $minutes = $lockout_info['minutes'];
            $seconds = $lockout_info['seconds'];
            $tier = $lockout_info['tier'];
            
            $tier_names = [1 => 'Tier 1', 2 => 'Tier 2', 3 => 'Tier 3'];
            $tier_name = $tier_names[$tier] ?? '';
            
            if ($minutes > 0) {
                $error_message = "Device locked. Please try again in {$minutes} minute(s) and {$seconds} second(s).";
            } else {
                $error_message = "Device locked. Please try again in {$seconds} second(s).";
            }
        }
        // Verify CSRF token
        elseif (!$auth->verifyCSRFToken($submitted_csrf_token)) {
            $error_message = 'Invalid security token. Please try again.';
        } elseif (empty($username) || empty($password)) {
            $error_message = 'Please enter both username and password';
        } else {
            // Attempt authentication
            if ($auth->authenticate($username, $password)) {
                // Redirect based on role
                $user = $auth->getCurrentUser();
                if ($user && isset($user['role'])) {
                    if ($user['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                        exit;
                    } elseif ($user['role'] === 'lawyer') {
                        header('Location: lawyer/dashboard.php');
                        exit;
                    } else {
                        $error_message = 'Invalid user role';
                    }
                } else {
                    $error_message = 'Authentication failed - session error';
                }
            } else {
                // Check if now locked out after this attempt
                if ($auth->isLockedOut()) {
                    $lockout_info = $auth->getLockoutTimeRemaining();
                    $minutes = $lockout_info['minutes'];
                    $seconds = $lockout_info['seconds'];
                    $tier = $lockout_info['tier'];
                    $attempts = $auth->getLoginAttempts();
                    
                    $tier_messages = [
                        1 => "Too many failed attempts. Device locked for 2 minutes.",
                        2 => "Too many failed attempts. Device locked for 5 minutes.",
                        3 => "Too many failed attempts. Device locked for 15 minutes."
                    ];
                    
                    $error_message = $tier_messages[$tier] ?? "Device locked. Please try again later.";
                } else {
                    $attempts = $auth->getLoginAttempts();
                    $next_info = $auth->getNextLockoutInfo();
                    
                    // Show warning when approaching next threshold
                    if ($next_info['remaining'] <= 2 && $next_info['remaining'] > 0) {
                        $error_message = "Invalid username or password. Warning: {$next_info['remaining']} more failed attempt(s) will lock this device for {$next_info['lockout_duration']}.";
                    } else {
                        $error_message = 'Invalid username or password';
                    }
                }
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
    <link rel="stylesheet" href="src/css/styles.css">
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
