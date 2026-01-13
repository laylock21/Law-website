<?php
/**
 * Unified Authentication System
 * Handles all authentication logic for admin and lawyer users
 */

class Auth {
    private $pdo;
    private $session;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->session = &$_SESSION;
    }
    
    /**
     * Authenticate user with username and password
     */
    public function authenticate($username, $password) {
        try {
            // Fetch user by username
            $stmt = $this->pdo->prepare('
                SELECT id, username, password, first_name, last_name, email, role, is_active 
                FROM users 
                WHERE username = ? LIMIT 1
            ');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Verify password and user status
            // NOTE: Plain text password comparison per request (no hashing)
            if ($user && $password === $user['password'] && $user['is_active'] == 1) {
                return $this->createSession($user);
            }
            
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
        // Set unified session variables
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
        
        // Set role-specific session variables for backward compatibility
        if ($user['role'] === 'admin') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['admin_role'] = $user['role'];
        } elseif ($user['role'] === 'lawyer') {
            $_SESSION['lawyer_logged_in'] = true;
            $_SESSION['lawyer_id'] = $user['id'];
            $_SESSION['lawyer_username'] = $user['username'];
            $_SESSION['lawyer_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['lawyer_email'] = $user['email'];
        }
        
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
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
        }
    }
}
?>
