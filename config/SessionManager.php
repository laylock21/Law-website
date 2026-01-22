<?php
/**
 * Session Manager
 * Handles database-backed session storage and validation
 */

class SessionManager {
    private $pdo;
    private $session_lifetime = 1800; // 30 minutes in seconds
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
    }
    
    /**
     * Create a new session in the database
     */
    public function createSession($user_id, $session_data = []) {
        try {
            // Generate session ID hash
            $session_id = session_id();
            $session_hash = hash('sha256', $session_id);
            
            // Get client information
            $ip_address = $this->getClientIP();
            $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
            
            // Calculate expiration time
            $expires_at = date('Y-m-d H:i:s', time() + $this->session_lifetime);
            
            // Insert session record
            $stmt = $this->pdo->prepare('
                INSERT INTO user_sessions 
                (id, user_id, ip_address, user_agent, status, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    ip_address = VALUES(ip_address),
                    user_agent = VALUES(user_agent),
                    status = VALUES(status),
                    last_activity = CURRENT_TIMESTAMP,
                    expires_at = VALUES(expires_at)
            ');
            
            $stmt->execute([
                $session_hash,
                $user_id,
                $ip_address,
                $user_agent,
                'active',
                $expires_at
            ]);
            
            // Store session hash in PHP session
            $_SESSION['session_hash'] = $session_hash;
            $_SESSION['session_created_at'] = time();
            
            $this->logSessionEvent('session_created', $user_id, $session_hash);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session creation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate current session
     */
    public function validateSession() {
        try {
            // Check if session hash exists
            if (!isset($_SESSION['session_hash'])) {
                return false;
            }
            
            $session_hash = $_SESSION['session_hash'];
            
            // Fetch session from database
            $stmt = $this->pdo->prepare('
                SELECT id, user_id, ip_address, user_agent, status, 
                       last_activity, expires_at
                FROM user_sessions
                WHERE id = ?
                LIMIT 1
            ');
            
            $stmt->execute([$session_hash]);
            $session = $stmt->fetch();
            
            if (!$session) {
                $this->logSessionEvent('session_not_found', null, $session_hash);
                return false;
            }
            
            // Check if session is active
            if ($session['status'] !== 'active') {
                $this->logSessionEvent('session_inactive', $session['user_id'], $session_hash, [
                    'status' => $session['status']
                ]);
                return false;
            }
            
            // Check if session has expired
            if (strtotime($session['expires_at']) < time()) {
                $this->expireSession($session_hash);
                $this->logSessionEvent('session_expired', $session['user_id'], $session_hash);
                return false;
            }
            
            // Validate IP address (optional - can be disabled for mobile users)
            $current_ip = $this->getClientIP();
            if ($session['ip_address'] !== $current_ip) {
                // Log suspicious activity but don't invalidate (mobile IPs can change)
                $this->logSessionEvent('ip_mismatch', $session['user_id'], $session_hash, [
                    'stored_ip' => $session['ip_address'],
                    'current_ip' => $current_ip
                ]);
            }
            
            // Validate user agent
            $current_user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 255);
            if ($session['user_agent'] !== $current_user_agent) {
                // This is more suspicious - invalidate session
                $this->invalidateSession($session_hash);
                $this->logSessionEvent('user_agent_mismatch', $session['user_id'], $session_hash, [
                    'stored_ua' => $session['user_agent'],
                    'current_ua' => $current_user_agent
                ]);
                return false;
            }
            
            // Update last activity
            $this->updateSessionActivity($session_hash);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session validation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update session activity timestamp
     */
    public function updateSessionActivity($session_hash = null) {
        try {
            if ($session_hash === null) {
                $session_hash = $_SESSION['session_hash'] ?? null;
            }
            
            if (!$session_hash) {
                return false;
            }
            
            // Update last_activity and extend expiration
            $new_expires_at = date('Y-m-d H:i:s', time() + $this->session_lifetime);
            
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET last_activity = CURRENT_TIMESTAMP,
                    expires_at = ?
                WHERE id = ? AND status = "active"
            ');
            
            $stmt->execute([$new_expires_at, $session_hash]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session activity update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Expire a session
     */
    public function expireSession($session_hash = null) {
        try {
            if ($session_hash === null) {
                $session_hash = $_SESSION['session_hash'] ?? null;
            }
            
            if (!$session_hash) {
                return false;
            }
            
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET status = "expired"
                WHERE id = ?
            ');
            
            $stmt->execute([$session_hash]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session expiration error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Invalidate a session (for security reasons)
     */
    public function invalidateSession($session_hash = null) {
        try {
            if ($session_hash === null) {
                $session_hash = $_SESSION['session_hash'] ?? null;
            }
            
            if (!$session_hash) {
                return false;
            }
            
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET status = "invalid"
                WHERE id = ?
            ');
            
            $stmt->execute([$session_hash]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session invalidation error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout - mark session as logged out
     */
    public function logoutSession($session_hash = null) {
        try {
            if ($session_hash === null) {
                $session_hash = $_SESSION['session_hash'] ?? null;
            }
            
            if (!$session_hash) {
                return false;
            }
            
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET status = "logged_out"
                WHERE id = ?
            ');
            
            $stmt->execute([$session_hash]);
            
            $this->logSessionEvent('session_logout', $_SESSION['user_id'] ?? null, $session_hash);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session logout error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Logout all sessions for a user
     */
    public function logoutAllUserSessions($user_id) {
        try {
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET status = "logged_out"
                WHERE user_id = ? AND status = "active"
            ');
            
            $stmt->execute([$user_id]);
            
            $this->logSessionEvent('all_sessions_logout', $user_id);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Logout all sessions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all active sessions for a user
     */
    public function getUserActiveSessions($user_id) {
        try {
            $stmt = $this->pdo->prepare('
                SELECT id, ip_address, user_agent, last_activity, created_at, expires_at
                FROM user_sessions
                WHERE user_id = ? AND status = "active"
                ORDER BY last_activity DESC
            ');
            
            $stmt->execute([$user_id]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get user sessions error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up expired sessions (run periodically)
     */
    public function cleanupExpiredSessions() {
        try {
            $stmt = $this->pdo->prepare('
                UPDATE user_sessions
                SET status = "expired"
                WHERE status = "active" 
                AND expires_at < NOW()
            ');
            
            $stmt->execute();
            
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                error_log("Cleaned up {$affected} expired sessions");
            }
            
            return $affected;
            
        } catch (Exception $e) {
            error_log("Session cleanup error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Delete old sessions (older than 30 days)
     */
    public function deleteOldSessions($days = 30) {
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM user_sessions
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ');
            
            $stmt->execute([$days]);
            
            $affected = $stmt->rowCount();
            
            if ($affected > 0) {
                error_log("Deleted {$affected} old sessions");
            }
            
            return $affected;
            
        } catch (Exception $e) {
            error_log("Delete old sessions error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle multiple IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return substr($ip, 0, 45); // Limit to 45 chars for IPv6
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Log session events
     */
    private function logSessionEvent($event_type, $user_id = null, $session_hash = null, $details = []) {
        if (class_exists('Logger')) {
            $context = array_merge([
                'ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'session_hash' => $session_hash,
                'user_id' => $user_id
            ], $details);
            
            Logger::security($event_type, $context);
        }
    }
    
    /**
     * Set session lifetime (in seconds)
     */
    public function setSessionLifetime($seconds) {
        $this->session_lifetime = $seconds;
    }
    
    /**
     * Get session lifetime
     */
    public function getSessionLifetime() {
        return $this->session_lifetime;
    }
}
?>
