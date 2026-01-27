<?php
/**
 * Session Management Page
 * Allows admins to view and manage active user sessions
 */

session_start();

require_once '../config/database.php';
require_once '../config/Auth.php';

$pdo = getDBConnection();
if (!$pdo) {
    die('Database connection failed');
}

$auth = new Auth($pdo);
$auth->requireAuth('admin');

$sessionManager = $auth->getSessionManager();

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!$auth->verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token';
    } else {
        switch ($action) {
            case 'logout_session':
                $session_id = $_POST['session_id'] ?? '';
                if ($sessionManager->logoutSession($session_id)) {
                    $message = 'Session logged out successfully';
                } else {
                    $error = 'Failed to logout session';
                }
                break;
                
            case 'logout_all_user':
                $user_id = $_POST['user_id'] ?? 0;
                if ($sessionManager->logoutAllUserSessions($user_id)) {
                    $message = 'All user sessions logged out successfully';
                } else {
                    $error = 'Failed to logout user sessions';
                }
                break;
                
            case 'cleanup':
                $expired = $sessionManager->cleanupExpiredSessions();
                $message = "Cleaned up {$expired} expired sessions";
                break;
        }
    }
}

// Get all active sessions
try {
    $stmt = $pdo->query('
        SELECT 
            s.id,
            s.user_id,
            s.ip_address,
            s.user_agent,
            s.status,
            s.last_activity,
            s.created_at,
            s.expires_at,
            u.username,
            u.first_name,
            u.last_name,
            u.role
        FROM user_sessions s
        LEFT JOIN users u ON s.user_id = u.user_id
        WHERE s.status = "active"
        ORDER BY s.last_activity DESC
    ');
    
    $active_sessions = $stmt->fetchAll();
    
    // Get session statistics
    $stats_stmt = $pdo->query('
        SELECT 
            status,
            COUNT(*) as count
        FROM user_sessions
        GROUP BY status
    ');
    
    $stats = [];
    while ($row = $stats_stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
} catch (Exception $e) {
    $error = 'Error fetching sessions: ' . $e->getMessage();
    $active_sessions = [];
    $stats = [];
}

$csrf_token = $auth->generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Management - Admin Dashboard</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <style>
        .session-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
        }
        
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .sessions-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sessions-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .sessions-table th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        
        .sessions-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        
        .sessions-table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .user-agent {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php include 'partials/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1>Session Management</h1>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="cleanup">
                <button type="submit" class="btn-primary btn-small">Cleanup Expired Sessions</button>
            </form>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="session-stats">
            <div class="stat-card">
                <h3>Active Sessions</h3>
                <div class="number"><?php echo $stats['active'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Expired Sessions</h3>
                <div class="number"><?php echo $stats['expired'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Logged Out</h3>
                <div class="number"><?php echo $stats['logged_out'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Invalid Sessions</h3>
                <div class="number"><?php echo $stats['invalid'] ?? 0; ?></div>
            </div>
        </div>
        
        <div class="sessions-table">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Last Activity</th>
                        <th>Expires At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($active_sessions)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                No active sessions found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($active_sessions as $session): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($session['username']); ?></strong><br>
                                    <small>User ID: <?php echo htmlspecialchars($session['user_id']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($session['role'])); ?></td>
                                <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                <td>
                                    <div class="user-agent" title="<?php echo htmlspecialchars($session['user_agent']); ?>">
                                        <?php echo htmlspecialchars($session['user_agent']); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y H:i:s', strtotime($session['last_activity'])); ?></td>
                                <td><?php echo date('M d, Y H:i:s', strtotime($session['expires_at'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="logout_session">
                                        <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['id']); ?>">
                                        <button type="submit" class="btn-danger btn-small" 
                                                onclick="return confirm('Logout this session?')">
                                            Logout
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
