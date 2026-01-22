# Session Management - Quick Reference

## Setup (One-time)

```bash
# Create the table
php create_sessions_table.php

# Test the system
php test_session_system.php

# Check sessions
php check_sessions.php
```

## Basic Usage

### In Your Login Page

```php
<?php
require_once 'config/database.php';
require_once 'config/Auth.php';

$pdo = getDBConnection();
$auth = new Auth($pdo);

// Login
if ($auth->authenticate($username, $password)) {
    // ✓ Session automatically created in database
    header('Location: dashboard.php');
}
?>
```

### In Protected Pages

```php
<?php
require_once 'config/database.php';
require_once 'config/Auth.php';

$pdo = getDBConnection();
$auth = new Auth($pdo);

// Require authentication
$auth->requireAuth('admin'); // or 'lawyer'

// ✓ Session automatically validated against database
?>
```

### Logout

```php
<?php
$auth->logout();
// ✓ Session marked as 'logged_out' in database
header('Location: login.php');
?>
```

## Advanced Usage

### Get Session Manager

```php
$sessionManager = $auth->getSessionManager();
```

### View User's Active Sessions

```php
$sessions = $sessionManager->getUserActiveSessions($user_id);
foreach ($sessions as $session) {
    echo "IP: {$session['ip_address']}, Last: {$session['last_activity']}";
}
```

### Logout All User Sessions

```php
// Force logout from all devices
$sessionManager->logoutAllUserSessions($user_id);
```

### Custom Session Lifetime

```php
// Set to 1 hour instead of default 30 minutes
$sessionManager->setSessionLifetime(3600);
```

### Manual Session Control

```php
// Expire a session
$sessionManager->expireSession($session_hash);

// Invalidate (security breach)
$sessionManager->invalidateSession($session_hash);

// Update activity
$sessionManager->updateSessionActivity();
```

## Maintenance

### Automatic Cleanup (Cron)

```bash
# Add to crontab (run every hour)
0 * * * * php /path/to/cleanup_sessions.php
```

### Manual Cleanup

```bash
# Command line
php cleanup_sessions.php

# Or via browser
http://yoursite.com/cleanup_sessions.php?key=YOUR_SECRET_KEY
```

### Admin Interface

Access at: `http://yoursite.com/admin/manage_sessions.php`

- View all active sessions
- Logout individual sessions
- See statistics
- Run cleanup

## Session Status

| Status | Meaning |
|--------|---------|
| `active` | Currently valid |
| `expired` | Timed out (30 min) |
| `logged_out` | User logged out |
| `invalid` | Security issue detected |

## Security Features

✓ **Session Fixation Prevention**: ID regenerated on login  
✓ **Hijacking Detection**: User agent validation  
✓ **Auto Expiration**: 30 minutes of inactivity  
✓ **IP Logging**: Tracks IP changes (logged, not enforced)  
✓ **Security Logging**: All events logged for audit  

## Troubleshooting

### Sessions not working?

1. Check table exists: `php check_sessions.php`
2. Check logs: `logs/application_*.log`
3. Verify database connection

### Sessions expiring too fast?

```php
$sessionManager->setSessionLifetime(3600); // 1 hour
```

### Need to force logout a user?

```php
$sessionManager->logoutAllUserSessions($user_id);
```

## Files Reference

| File | Purpose |
|------|---------|
| `config/SessionManager.php` | Core session management |
| `config/Auth.php` | Authentication with sessions |
| `admin/manage_sessions.php` | Admin interface |
| `cleanup_sessions.php` | Cleanup script |
| `test_session_system.php` | Test script |
| `check_sessions.php` | View sessions |

## Database Queries

### View active sessions
```sql
SELECT * FROM user_sessions WHERE status = 'active';
```

### Count by status
```sql
SELECT status, COUNT(*) FROM user_sessions GROUP BY status;
```

### Find user's sessions
```sql
SELECT * FROM user_sessions WHERE user_id = 123;
```

### Cleanup expired
```sql
UPDATE user_sessions 
SET status = 'expired' 
WHERE status = 'active' AND expires_at < NOW();
```

### Delete old sessions
```sql
DELETE FROM user_sessions 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## Configuration

### Change session lifetime
In `config/SessionManager.php`:
```php
private $session_lifetime = 1800; // 30 minutes (in seconds)
```

### Enable strict IP validation
In `SessionManager.php`, `validateSession()` method:
```php
if ($session['ip_address'] !== $current_ip) {
    $this->invalidateSession($session_hash);
    return false;
}
```

### Change cleanup key
In `cleanup_sessions.php`:
```php
define('CLEANUP_KEY', 'your_secret_key_here');
```

## Best Practices

1. ✓ Run cleanup regularly (cron job)
2. ✓ Monitor session statistics
3. ✓ Review security logs
4. ✓ Test logout functionality
5. ✓ Backup `user_sessions` table
6. ✓ Use HTTPS in production
7. ✓ Keep session lifetime reasonable
8. ✓ Force logout on password change

## Need Help?

- Check `SESSION_MANAGEMENT_GUIDE.md` for detailed documentation
- Review error logs in `logs/` directory
- Test with `test_session_system.php`
- Check database with `check_sessions.php`
