# Session Management System

## Overview

This system provides database-backed session management with enhanced security features including:

- **Database-backed sessions**: All sessions stored in `user_sessions` table
- **Session validation**: Validates IP address and user agent on each request
- **Automatic expiration**: Sessions expire after 30 minutes of inactivity
- **Security tracking**: Logs all session events for audit purposes
- **Session management**: Admin interface to view and manage active sessions

## Database Schema

```sql
CREATE TABLE user_sessions (
    id VARCHAR(128) NOT NULL COMMENT 'SHA-256 hash of session_id()',
    user_id INT(11) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    status ENUM('active', 'expired', 'logged_out', 'invalid') DEFAULT 'active',
    last_activity TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Installation

### 1. Run the Migration

```bash
php run_migration.php migrations/007_create_user_sessions_table.sql
```

Or import directly into your database:

```bash
mysql -u root -p testLaw < migrations/007_create_user_sessions_table.sql
```

### 2. Update Existing Code

The system is already integrated into `config/Auth.php`. No additional changes needed for basic functionality.

## Usage

### Basic Authentication

The session system works automatically with the existing Auth class:

```php
<?php
require_once 'config/database.php';
require_once 'config/Auth.php';

$pdo = getDBConnection();
$auth = new Auth($pdo);

// Login
if ($auth->authenticate($username, $password)) {
    // Session automatically created in database
    header('Location: dashboard.php');
}

// Check if logged in
if ($auth->isLoggedIn()) {
    // Session automatically validated against database
    echo "User is logged in";
}

// Logout
$auth->logout();
// Session marked as 'logged_out' in database
?>
```

### Session Validation

Sessions are automatically validated on every `isLoggedIn()` call:

- **IP Address**: Logged but not enforced (mobile users can change IPs)
- **User Agent**: Strictly enforced - session invalidated if changed
- **Expiration**: Sessions expire after 30 minutes of inactivity
- **Status**: Only 'active' sessions are valid

### Manual Session Management

```php
<?php
$sessionManager = $auth->getSessionManager();

// Get all active sessions for a user
$sessions = $sessionManager->getUserActiveSessions($user_id);

// Logout all sessions for a user
$sessionManager->logoutAllUserSessions($user_id);

// Manually expire a session
$sessionManager->expireSession($session_hash);

// Invalidate a session (security breach)
$sessionManager->invalidateSession($session_hash);

// Update session activity
$sessionManager->updateSessionActivity();

// Set custom session lifetime (in seconds)
$sessionManager->setSessionLifetime(3600); // 1 hour
?>
```

## Session Cleanup

### Automatic Cleanup (Recommended)

Set up a cron job to run the cleanup script:

```bash
# Run every hour
0 * * * * php /path/to/your/project/cleanup_sessions.php
```

### Manual Cleanup

Via command line:
```bash
php cleanup_sessions.php
```

Via browser (requires security key):
```
http://yoursite.com/cleanup_sessions.php?key=YOUR_SECRET_KEY
```

**Important**: Change the `CLEANUP_KEY` in `cleanup_sessions.php` before using browser access!

## Admin Interface

Access the session management interface at:
```
http://yoursite.com/admin/manage_sessions.php
```

Features:
- View all active sessions
- See session statistics (active, expired, logged out, invalid)
- Logout individual sessions
- Logout all sessions for a user
- Run cleanup manually

## Session Status Types

| Status | Description |
|--------|-------------|
| `active` | Session is currently active and valid |
| `expired` | Session has expired due to inactivity |
| `logged_out` | User explicitly logged out |
| `invalid` | Session invalidated due to security concerns (e.g., user agent mismatch) |

## Security Features

### 1. Session Fixation Prevention
- Session ID regenerated on login
- Old session ID invalidated

### 2. Session Hijacking Detection
- User agent validation (strict)
- IP address logging (informational)
- Suspicious activity logged

### 3. Automatic Expiration
- Sessions expire after 30 minutes of inactivity
- Expiration time extended on each activity

### 4. Security Logging
All session events are logged:
- `session_created`: New session created
- `session_not_found`: Session not found in database
- `session_inactive`: Session status is not 'active'
- `session_expired`: Session has expired
- `ip_mismatch`: IP address changed (logged but not enforced)
- `user_agent_mismatch`: User agent changed (session invalidated)
- `session_logout`: User logged out
- `all_sessions_logout`: All user sessions logged out

## Configuration

### Session Lifetime

Default: 30 minutes (1800 seconds)

To change:
```php
$sessionManager = $auth->getSessionManager();
$sessionManager->setSessionLifetime(3600); // 1 hour
```

### IP Validation

Currently disabled by default (only logged). To enable strict IP validation, modify `SessionManager.php`:

```php
// In validateSession() method, change:
if ($session['ip_address'] !== $current_ip) {
    $this->invalidateSession($session_hash);
    return false;
}
```

## Troubleshooting

### Sessions Not Being Created

1. Check database connection
2. Verify `user_sessions` table exists
3. Check error logs: `logs/application_*.log`

### Sessions Expiring Too Quickly

1. Check session lifetime setting
2. Verify `updateSessionActivity()` is being called
3. Check server time vs database time

### "Session Invalid" Errors

1. Check if user agent is changing (browser extensions, VPN)
2. Verify session exists in database
3. Check session status in database

### Old Sessions Not Cleaning Up

1. Set up cron job for `cleanup_sessions.php`
2. Run manual cleanup
3. Check database permissions

## Best Practices

1. **Run cleanup regularly**: Set up a cron job to clean expired sessions
2. **Monitor session statistics**: Check admin interface regularly
3. **Review security logs**: Look for suspicious patterns
4. **Adjust session lifetime**: Based on your security requirements
5. **Test logout functionality**: Ensure sessions are properly terminated
6. **Backup session data**: Include `user_sessions` table in backups

## API Reference

### SessionManager Methods

#### `createSession($user_id, $session_data = [])`
Creates a new session in the database.

#### `validateSession()`
Validates the current session against database records.

#### `updateSessionActivity($session_hash = null)`
Updates the last activity timestamp and extends expiration.

#### `expireSession($session_hash = null)`
Marks a session as expired.

#### `invalidateSession($session_hash = null)`
Marks a session as invalid (security breach).

#### `logoutSession($session_hash = null)`
Marks a session as logged out.

#### `logoutAllUserSessions($user_id)`
Logs out all active sessions for a user.

#### `getUserActiveSessions($user_id)`
Returns all active sessions for a user.

#### `cleanupExpiredSessions()`
Marks expired sessions as 'expired' status.

#### `deleteOldSessions($days = 30)`
Deletes sessions older than specified days.

#### `setSessionLifetime($seconds)`
Sets the session lifetime in seconds.

#### `getSessionLifetime()`
Returns the current session lifetime.

## Migration from Old System

If you're migrating from the old session system:

1. Run the migration to create the `user_sessions` table
2. The new system is backward compatible - no code changes needed
3. Old sessions will continue to work
4. New logins will use the database-backed system
5. Run cleanup to remove old expired sessions

## Support

For issues or questions:
1. Check error logs in `logs/` directory
2. Review security logs for session events
3. Check database for session records
4. Verify configuration settings
