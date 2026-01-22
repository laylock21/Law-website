# Session Management Flow Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     User Authentication Flow                     │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐         ┌──────────┐         ┌─────────────────┐
│  User    │────────>│ login.php│────────>│   Auth Class    │
│ (Browser)│         │          │         │                 │
└──────────┘         └──────────┘         └────────┬────────┘
                                                    │
                                                    v
                                          ┌─────────────────┐
                                          │ authenticate()  │
                                          │ - Verify user   │
                                          │ - Check password│
                                          └────────┬────────┘
                                                   │
                                                   v
                                          ┌─────────────────┐
                                          │ createSession() │
                                          └────────┬────────┘
                                                   │
                                                   v
                                          ┌─────────────────────┐
                                          │  SessionManager     │
                                          │  createSession()    │
                                          └──────────┬──────────┘
                                                     │
                                                     v
                                          ┌──────────────────────┐
                                          │  user_sessions table │
                                          │  - id (hash)         │
                                          │  - user_id           │
                                          │  - ip_address        │
                                          │  - user_agent        │
                                          │  - status: 'active'  │
                                          │  - expires_at        │
                                          └──────────────────────┘
```

## Session Validation Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Protected Page Access                         │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐         ┌──────────────┐         ┌─────────────────┐
│  User    │────────>│ dashboard.php│────────>│   Auth Class    │
│ (Browser)│         │              │         │                 │
└──────────┘         └──────────────┘         └────────┬────────┘
                                                        │
                                                        v
                                              ┌─────────────────┐
                                              │ requireAuth()   │
                                              │ isLoggedIn()    │
                                              └────────┬────────┘
                                                       │
                                                       v
                                              ┌─────────────────────┐
                                              │  SessionManager     │
                                              │  validateSession()  │
                                              └──────────┬──────────┘
                                                         │
                                                         v
                                    ┌────────────────────────────────────┐
                                    │  Validation Checks:                │
                                    │  1. Session exists in database?    │
                                    │  2. Status = 'active'?             │
                                    │  3. Not expired?                   │
                                    │  4. User agent matches?            │
                                    │  5. IP logged (not enforced)       │
                                    └────────────┬───────────────────────┘
                                                 │
                                    ┌────────────┴────────────┐
                                    │                         │
                                    v                         v
                            ┌───────────┐           ┌─────────────┐
                            │   VALID   │           │   INVALID   │
                            │           │           │             │
                            │ - Update  │           │ - Logout    │
                            │   activity│           │ - Redirect  │
                            │ - Extend  │           │   to login  │
                            │   expires │           │             │
                            └───────────┘           └─────────────┘
```

## Logout Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                         User Logout                              │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐         ┌──────────┐         ┌─────────────────┐
│  User    │────────>│logout.php│────────>│   Auth Class    │
│ (Browser)│         │          │         │                 │
└──────────┘         └──────────┘         └────────┬────────┘
                                                    │
                                                    v
                                          ┌─────────────────┐
                                          │   logout()      │
                                          └────────┬────────┘
                                                   │
                                                   v
                                          ┌─────────────────────┐
                                          │  SessionManager     │
                                          │  logoutSession()    │
                                          └──────────┬──────────┘
                                                     │
                                                     v
                                          ┌──────────────────────┐
                                          │  user_sessions table │
                                          │  UPDATE status =     │
                                          │  'logged_out'        │
                                          └──────────┬───────────┘
                                                     │
                                                     v
                                          ┌──────────────────────┐
                                          │  Destroy PHP Session │
                                          │  Clear cookies       │
                                          │  Redirect to login   │
                                          └──────────────────────┘
```

## Session Cleanup Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Automatic Maintenance                         │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐         ┌────────────────────┐
│  Cron Job    │────────>│ cleanup_sessions   │
│  (Hourly)    │         │      .php          │
└──────────────┘         └─────────┬──────────┘
                                   │
                                   v
                         ┌─────────────────────┐
                         │  SessionManager     │
                         └──────────┬──────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
                    v                               v
        ┌───────────────────────┐     ┌────────────────────────┐
        │ cleanupExpiredSessions│     │  deleteOldSessions     │
        │                       │     │                        │
        │ UPDATE status =       │     │  DELETE sessions       │
        │ 'expired'             │     │  older than 30 days    │
        │ WHERE expires_at <    │     │                        │
        │ NOW()                 │     │                        │
        └───────────────────────┘     └────────────────────────┘
```

## Admin Management Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                    Admin Session Management                      │
└─────────────────────────────────────────────────────────────────┘

┌──────────┐         ┌──────────────────┐         ┌──────────────┐
│  Admin   │────────>│ manage_sessions  │────────>│  Database    │
│  User    │         │     .php         │         │              │
└──────────┘         └────────┬─────────┘         └──────────────┘
                              │
                              v
                    ┌─────────────────────┐
                    │  View All Sessions  │
                    │  - Active           │
                    │  - Expired          │
                    │  - Logged out       │
                    │  - Invalid          │
                    └─────────┬───────────┘
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    v                   v
        ┌───────────────────┐   ┌──────────────────┐
        │ Logout Individual │   │ Logout All User  │
        │     Session       │   │    Sessions      │
        │                   │   │                  │
        │ UPDATE status =   │   │ UPDATE status =  │
        │ 'logged_out'      │   │ 'logged_out'     │
        │ WHERE id = ?      │   │ WHERE user_id = ?│
        └───────────────────┘   └──────────────────┘
```

## Session Status State Machine

```
┌─────────────────────────────────────────────────────────────────┐
│                    Session Status States                         │
└─────────────────────────────────────────────────────────────────┘

                    ┌──────────────┐
                    │   CREATED    │
                    │              │
                    └──────┬───────┘
                           │
                           v
                    ┌──────────────┐
                    │    ACTIVE    │<──────┐
                    │              │       │
                    └──────┬───────┘       │
                           │               │
                           │          (activity)
                           │               │
        ┌──────────────────┼───────────────┼──────────────┐
        │                  │               │              │
        v                  v               │              v
┌───────────────┐  ┌──────────────┐       │      ┌──────────────┐
│   EXPIRED     │  │  LOGGED_OUT  │       │      │   INVALID    │
│               │  │              │       │      │              │
│ (timeout)     │  │ (user action)│       │      │ (security)   │
└───────────────┘  └──────────────┘       │      └──────────────┘
                                           │
                                           │
                                    (validation)
```

## Data Flow Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                      Component Interaction                       │
└─────────────────────────────────────────────────────────────────┘

┌──────────────┐
│   Browser    │
│  (PHP $_SESSION)
└──────┬───────┘
       │
       │ session_id()
       │
       v
┌──────────────────┐
│   Auth Class     │
│  - authenticate()│
│  - isLoggedIn()  │
│  - logout()      │
└──────┬───────────┘
       │
       │ uses
       │
       v
┌──────────────────────┐
│  SessionManager      │
│  - createSession()   │
│  - validateSession() │
│  - logoutSession()   │
│  - cleanup()         │
└──────┬───────────────┘
       │
       │ reads/writes
       │
       v
┌──────────────────────┐
│  user_sessions       │
│  (Database Table)    │
│  - id (hash)         │
│  - user_id           │
│  - status            │
│  - expires_at        │
└──────────────────────┘
```

## Security Validation Chain

```
┌─────────────────────────────────────────────────────────────────┐
│                    Security Checks (in order)                    │
└─────────────────────────────────────────────────────────────────┘

Request Received
      │
      v
┌─────────────────┐
│ 1. PHP Session  │  ──> No session? ──> Redirect to login
│    Exists?      │
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 2. Session Hash │  ──> No hash? ──> Redirect to login
│    in $_SESSION?│
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 3. Session in   │  ──> Not found? ──> Redirect to login
│    Database?    │
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 4. Status =     │  ──> Not active? ──> Redirect to login
│    'active'?    │
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 5. Not Expired? │  ──> Expired? ──> Mark expired, redirect
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 6. User Agent   │  ──> Mismatch? ──> Invalidate, redirect
│    Matches?     │
└────────┬────────┘
         │ Yes
         v
┌─────────────────┐
│ 7. Log IP       │  ──> Different? ──> Log warning (continue)
│    (informational)
└────────┬────────┘
         │
         v
┌─────────────────┐
│ 8. Update       │
│    Activity &   │
│    Extend       │
│    Expiration   │
└────────┬────────┘
         │
         v
    ✅ VALID
    Allow Access
```

## Key Features Illustrated

### 1. Session Fixation Prevention
```
Login → session_regenerate_id() → New session ID → Hash stored in DB
```

### 2. Session Hijacking Detection
```
Each request → Validate user agent → Mismatch? → Invalidate session
```

### 3. Automatic Expiration
```
No activity for 30 min → expires_at < NOW() → Status = 'expired'
```

### 4. Activity Tracking
```
Each valid request → Update last_activity → Extend expires_at
```

### 5. Multi-Device Support
```
User → Multiple sessions → Each tracked separately → Can logout all
```

## File Relationships

```
login.php
    └─> config/Auth.php
            └─> config/SessionManager.php
                    └─> user_sessions table

dashboard.php
    └─> config/Auth.php
            └─> config/SessionManager.php
                    └─> user_sessions table

logout.php
    └─> config/Auth.php
            └─> config/SessionManager.php
                    └─> user_sessions table

admin/manage_sessions.php
    └─> config/Auth.php
            └─> config/SessionManager.php
                    └─> user_sessions table

cleanup_sessions.php
    └─> config/SessionManager.php
            └─> user_sessions table
```

This visual guide shows how all components work together to provide secure, database-backed session management.
