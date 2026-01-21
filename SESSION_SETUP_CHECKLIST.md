# Session Management Setup Checklist

## ✅ Completed (Already Done)

- [x] Created `user_sessions` table in database
- [x] Implemented `SessionManager` class
- [x] Integrated with `Auth` class
- [x] Updated `logout.php`
- [x] Created admin management interface
- [x] Created cleanup script
- [x] Created test scripts
- [x] Tested system functionality
- [x] Created documentation

## 🔧 Recommended Next Steps

### 1. Setup Automatic Cleanup (Important!)

**Option A: Windows Task Scheduler**
```
1. Open Task Scheduler
2. Create Basic Task
3. Name: "Cleanup Sessions"
4. Trigger: Daily, repeat every 1 hour
5. Action: Start a program
   - Program: C:\xampp\php\php.exe
   - Arguments: C:\xampp\htdocs\Law-website\cleanup_sessions.php
6. Save and test
```

**Option B: Manual Cron (if using Linux)**
```bash
crontab -e
# Add this line:
0 * * * * php /path/to/cleanup_sessions.php
```

**Option C: Manual Cleanup (Temporary)**
```bash
# Run this periodically
php cleanup_sessions.php
```

### 2. Change Cleanup Security Key

Edit `cleanup_sessions.php`:
```php
// Line 13 - Change this!
define('CLEANUP_KEY', 'your_secret_key_here_123456');
```

### 3. Test the System

```bash
# Run the test
php test_session_system.php

# Check sessions
php check_sessions.php
```

### 4. Add Session Management to Admin Menu

Edit `admin/partials/sidebar.php` and add:
```html
<li>
    <a href="manage_sessions.php">
        <i class="icon-sessions"></i>
        Session Management
    </a>
</li>
```

### 5. Configure Session Lifetime (Optional)

If 30 minutes is too short/long, edit `config/SessionManager.php`:
```php
// Line 9
private $session_lifetime = 1800; // Change to desired seconds
```

Or set dynamically in your code:
```php
$sessionManager = $auth->getSessionManager();
$sessionManager->setSessionLifetime(3600); // 1 hour
```

### 6. Enable Strict IP Validation (Optional)

If you want to enforce IP address matching (not recommended for mobile users):

Edit `config/SessionManager.php`, in `validateSession()` method around line 90:
```php
// Change from:
if ($session['ip_address'] !== $current_ip) {
    $this->logSessionEvent('ip_mismatch', ...);
}

// To:
if ($session['ip_address'] !== $current_ip) {
    $this->invalidateSession($session_hash);
    $this->logSessionEvent('ip_mismatch', ...);
    return false;
}
```

### 7. Monitor Sessions Regularly

- Check admin interface: `admin/manage_sessions.php`
- Review logs: `logs/application_*.log`
- Check statistics: `php check_sessions.php`

### 8. Test Logout Functionality

1. Login as a user
2. Check session in database: `php check_sessions.php`
3. Logout
4. Verify session status changed to 'logged_out'

### 9. Test Session Expiration

1. Login as a user
2. Wait 30 minutes (or change lifetime to 1 minute for testing)
3. Try to access a protected page
4. Should redirect to login

### 10. Test Multi-Device Logout

1. Login from multiple browsers/devices
2. Go to admin interface
3. Logout all sessions for a user
4. Verify all sessions are logged out

## 📋 Verification Checklist

Run these commands to verify everything is working:

```bash
# 1. Check table exists
php -r "require 'config/database.php'; $pdo = getDBConnection(); $stmt = $pdo->query('SHOW TABLES LIKE \"user_sessions\"'); echo $stmt->rowCount() > 0 ? '✓ Table exists' : '✗ Table missing';"

# 2. Test session system
php test_session_system.php

# 3. Check current sessions
php check_sessions.php

# 4. Test cleanup
php cleanup_sessions.php
```

## 🔍 Testing Scenarios

### Scenario 1: Normal Login/Logout
```
1. Login → Check session created (status: active)
2. Access dashboard → Session validated
3. Logout → Check session status (should be: logged_out)
```

### Scenario 2: Session Expiration
```
1. Login → Note the expires_at time
2. Wait for expiration (or manually update in DB)
3. Try to access page → Should redirect to login
4. Check session status → Should be: expired
```

### Scenario 3: Session Hijacking Detection
```
1. Login → Note the user_agent
2. Manually change user_agent in database
3. Try to access page → Should redirect to login
4. Check session status → Should be: invalid
```

### Scenario 4: Multiple Sessions
```
1. Login from Browser A
2. Login from Browser B
3. Check admin interface → Should see 2 active sessions
4. Logout all → Both sessions should be logged_out
```

## 📊 Monitoring

### Daily Checks
- [ ] Check session statistics in admin interface
- [ ] Review any 'invalid' sessions (potential security issues)
- [ ] Verify cleanup is running

### Weekly Checks
- [ ] Review security logs for suspicious patterns
- [ ] Check database size (user_sessions table)
- [ ] Verify old sessions are being deleted

### Monthly Checks
- [ ] Review session lifetime settings
- [ ] Check for any performance issues
- [ ] Update documentation if needed

## 🚨 Troubleshooting

### Sessions not being created?
```bash
# Check database connection
php -r "require 'config/database.php'; var_dump(getDBConnection());"

# Check table structure
php -r "require 'config/database.php'; $pdo = getDBConnection(); $stmt = $pdo->query('DESCRIBE user_sessions'); while($row = $stmt->fetch()) print_r($row);"

# Check error logs
type logs\application_*.log
```

### Sessions expiring too quickly?
```php
// Check current lifetime
$sessionManager = $auth->getSessionManager();
echo $sessionManager->getSessionLifetime(); // Should be 1800 (30 min)

// Increase if needed
$sessionManager->setSessionLifetime(3600); // 1 hour
```

### Cleanup not working?
```bash
# Test cleanup manually
php cleanup_sessions.php

# Check if scheduled task is running (Windows)
# Task Scheduler → Task Scheduler Library → Find "Cleanup Sessions"

# Check cron (Linux)
crontab -l
```

## 📚 Documentation Reference

- **Complete Guide**: `SESSION_MANAGEMENT_GUIDE.md`
- **Quick Reference**: `SESSION_QUICK_REFERENCE.md`
- **Flow Diagrams**: `SESSION_FLOW_DIAGRAM.md`
- **Implementation Summary**: `SESSION_IMPLEMENTATION_SUMMARY.md`

## ✅ Final Verification

Run this complete test:

```bash
# 1. Create table (if not done)
php create_sessions_table.php

# 2. Test system
php test_session_system.php

# 3. Check sessions
php check_sessions.php

# 4. Test cleanup
php cleanup_sessions.php

# 5. Login via browser
# Visit: http://localhost/Law-website/login.php
# Login with valid credentials

# 6. Check session was created
php check_sessions.php

# 7. Access admin interface
# Visit: http://localhost/Law-website/admin/manage_sessions.php

# 8. Logout
# Visit: http://localhost/Law-website/logout.php

# 9. Verify logout
php check_sessions.php
```

## 🎉 Success Criteria

Your session management system is fully operational when:

- [x] Table exists and has correct structure
- [x] Test script passes all tests
- [x] Login creates session in database
- [x] Session validation works on protected pages
- [x] Logout marks session as 'logged_out'
- [x] Admin interface shows sessions
- [x] Cleanup script runs successfully
- [ ] Automatic cleanup is scheduled (recommended)
- [ ] Security key is changed (recommended)

## 📞 Support

If you encounter issues:

1. Check error logs: `logs/application_*.log`
2. Run test script: `php test_session_system.php`
3. Check database: `php check_sessions.php`
4. Review documentation in the files listed above

---

**Current Status**: ✅ System Implemented and Tested

**Next Action**: Setup automatic cleanup (Step 1 above)
