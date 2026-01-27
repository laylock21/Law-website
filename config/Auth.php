<?php
/**
 * Unified Authentication System
 * Handles all authentication logic for admin and lawyer users
 */

class Auth {
    private $pdo;
    private $session;
    private $sessionManager;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->session = &$_SESSION;
        
        // Initialize session manager
        require_once __DIR__ . '/SessionManager.php';
        $this->sessionManager = new SessionManager($database_connection);
    }
    
    /**
     * Authenticate user with username and password
     */
    public function authenticate($username, $password) {
        // Check if account is locked out
        if ($this->isLockedOut()) {
            $remaining = $this->getLockoutTimeRemaining();
            error_log("Login attempt during lockout for username: $username from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
        
        try {
            // Fetch user by username
            $stmt = $this->pdo->prepare('
                SELECT user_id, username, password, email, phone, role, is_active 
                FROM users 
                WHERE username = ? LIMIT 1
            ');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Verify password and user status
            if ($user && $user['is_active'] == 1) {
                // Use password_verify for hashed passwords
                if (password_verify($password, $user['password'])) {
                    // Successful login - log it
                    $this->logSecurityEvent('successful_login', [
                        'user_id' => $user['user_id'],
                        'username' => $username,
                        'role' => $user['role']
                    ]);
                    
                    return $this->createSession($user);
                }
            }
            
            // Failed login - increment attempts and log
            $this->incrementLoginAttempts($username);
            $this->logSecurityEvent('failed_login', [
                'username' => $username,
                'attempts' => $_SESSION['login_attempts'] ?? 1
            ]);
            
            return false;
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create user session
     */
    private function createSession($user) {
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);
        
        // Set unified session variables
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['username']; // Use username since no name fields
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        // Set role-specific session variables for backward compatibility
        if ($user['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
        } elseif ($user['role'] === 'lawyer') {
            $_SESSION['lawyer_logged_in'] = true;
            $_SESSION['lawyer_id'] = $user['user_id'];
            $_SESSION['lawyer_username'] = $user['username'];
            $_SESSION['lawyer_name'] = $user['username']; // Use username since no name fields
            $_SESSION['lawyer_email'] = $user['email'];
        }
        
        // Try to create database session record (don't fail if table doesn't exist)
        try {
            $this->sessionManager->createSession($user['user_id']);
        } catch (Exception $e) {
            error_log("SessionManager error (non-fatal): " . $e->getMessage());
            // Continue anyway - session will work without database backing
        }
        
        // Clear any failed login attempts
        unset($_SESSION['login_attempts']);
        unset($_SESSION['last_attempt_time']);
        unset($_SESSION['lockout_until']);
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        // Check PHP session
        if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
            return false;
        }
        
        // Try to validate against database session (don't fail if table doesn't exist)
        try {
            if (!$this->sessionManager->validateSession()) {
                // Session invalid - clear PHP session
                $this->logout();
                return false;
            }
        } catch (Exception $e) {
            error_log("SessionManager validation error (non-fatal): " . $e->getMessage());
            // Continue anyway - rely on PHP session only
        }
        
        return true;
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['user_role'] === $role;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'],
            'role' => $_SESSION['user_role'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email']
        ];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Try to mark session as logged out in database
        try {
            $this->sessionManager->logoutSession();
        } catch (Exception $e) {
            error_log("SessionManager logout error (non-fatal): " . $e->getMessage());
        }
        
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Require authentication for page access
     */
    public function requireAuth($required_role = null) {
        if (!$this->isLoggedIn()) {
            header('Location: ../login.php');
            exit;
        }
        
        if ($required_role && !$this->hasRole($required_role)) {
            header('Location: ../login.php?error=unauthorized');
            exit;
        }
        
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check session timeout (30 minutes)
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn() && isset($_SESSION['login_time'])) {
            $timeout = 30 * 60; // 30 minutes
            if (time() - $_SESSION['login_time'] > $timeout) {
                $this->logout();
                return false;
            }
        }
        return true;
    }
    
    /**
     * Update last activity time
     */
    public function updateLastActivity() {
        if ($this->isLoggedIn()) {
            $_SESSION['last_activity'] = time();
            $this->sessionManager->updateSessionActivity();
        }
    }
    
    /**
     * Get session manager instance
     */
    public function getSessionManager() {
        return $this->sessionManager;
    }
    
    /**
     * Increment failed login attempts
     */
    private function incrementLoginAttempts($username) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = 0;
            $_SESSION['attempted_username'] = $username;
        }
        
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        
        // Tiered lockout system
        $attempts = $_SESSION['login_attempts'];
        
        if ($attempts >= 15) {
            // 15+ attempts = 15 minutes lockout
            $_SESSION['lockout_until'] = time() + (15 * 60);
            $_SESSION['lockout_tier'] = 3;
        } elseif ($attempts >= 10) {
            // 10-14 attempts = 5 minutes lockout
            $_SESSION['lockout_until'] = time() + (5 * 60);
            $_SESSION['lockout_tier'] = 2;
        } elseif ($attempts >= 5) {
            // 5-9 attempts = 2 minutes lockout
            $_SESSION['lockout_until'] = time() + (2 * 60);
            $_SESSION['lockout_tier'] = 1;
        }
    }
    
    /**
     * Check if account is locked out
     */
    public function isLockedOut() {
        if (isset($_SESSION['lockout_until'])) {
            if (time() < $_SESSION['lockout_until']) {
                return true;
            } else {
                // Lockout expired, clear it
                unset($_SESSION['login_attempts']);
                unset($_SESSION['last_attempt_time']);
                unset($_SESSION['lockout_until']);
                unset($_SESSION['lockout_tier']);
                unset($_SESSION['attempted_username']);
                return false;
            }
        }
        return false;
    }
    
    /**
     * Get remaining lockout time in minutes and seconds
     */
    public function getLockoutTimeRemaining() {
        if (isset($_SESSION['lockout_until'])) {
            $remaining_seconds = $_SESSION['lockout_until'] - time();
            if ($remaining_seconds > 0) {
                $minutes = floor($remaining_seconds / 60);
                $seconds = $remaining_seconds % 60;
                return [
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'total_seconds' => $remaining_seconds,
                    'tier' => $_SESSION['lockout_tier'] ?? 1
                ];
            }
        }
        return ['minutes' => 0, 'seconds' => 0, 'total_seconds' => 0, 'tier' => 0];
    }
    
    /**
     * Get number of failed login attempts
     */
    public function getLoginAttempts() {
        return $_SESSION['login_attempts'] ?? 0;
    }
    
    /**
     * Get next lockout threshold info
     */
    public function getNextLockoutInfo() {
        $attempts = $this->getLoginAttempts();
        
        if ($attempts < 5) {
            return [
                'next_threshold' => 5,
                'remaining' => 5 - $attempts,
                'lockout_duration' => '2 minutes'
            ];
        } elseif ($attempts < 10) {
            return [
                'next_threshold' => 10,
                'remaining' => 10 - $attempts,
                'lockout_duration' => '5 minutes'
            ];
        } elseif ($attempts < 15) {
            return [
                'next_threshold' => 15,
                'remaining' => 15 - $attempts,
                'lockout_duration' => '15 minutes'
            ];
        } else {
            return [
                'next_threshold' => null,
                'remaining' => 0,
                'lockout_duration' => '15 minutes'
            ];
        }
    }
    
    /**
     * Log security events
     */
    private function logSecurityEvent($event_type, $details = []) {
        // Only log if Logger class is available
        if (class_exists('Logger')) {
            $context = array_merge([
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'timestamp' => date('Y-m-d H:i:s')
            ], $details);
            
            Logger::security($event_type, $context);
        }
    }
}
?>
