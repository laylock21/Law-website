<?php
/**
 * Notification Queue Viewer
 * View pending email notifications
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

$pdo = getDBConnection();

// Get all notifications
$stmt = $pdo->prepare("
    SELECT 
        nq.*,
        u.first_name,
        u.last_name
    FROM notification_queue nq
    LEFT JOIN users u ON nq.user_id = u.id
    ORDER BY nq.created_at DESC
    LIMIT 100
");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get statistics
$stats_stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM notification_queue
    GROUP BY status
");
$stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<?php
// Set page-specific variables for the header
$page_title = "Notification Queue";
$active_page = "queue";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Queue - Admin</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php 
            $pending_count = $stats['pending'] ?? 0;
            if ($pending_count > 0): ?>
                <div class="alert alert-warning">
                    <strong>📧 Action Required:</strong> There are <?php echo $pending_count; ?> pending email(s) waiting to be sent.
                    <a href="process_emails.php" class="btn btn-primary" style="margin-left: 15px;">
                        <i class="fas fa-paper-plane"></i> Send Now
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <strong>✅ Email System Active:</strong> All notifications are being sent automatically.
                </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['sent'] ?? 0; ?></div>
                    <div class="stat-label">Sent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['failed'] ?? 0; ?></div>
                    <div class="stat-label">Failed</div>
                </div>
            </div>
            
            <h2 style="padding:16px 0 16px 0;">Recent Notifications</h2>
            
            <?php if (empty($notifications)): ?>
                <p style="text-align: center; padding: 40px; color: #6c757d;">
                    No notifications in queue.
                </p>
            <?php else: ?>
                <table class="notification-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Attempts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notif): ?>
                            <tr>
                                <td><?php echo $notif['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($notif['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($notif['subject']); ?></td>
                                <td><?php echo ucwords(str_replace('_', ' ', $notif['notification_type'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $notif['status']; ?>">
                                        <?php echo ucfirst($notif['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></td>
                                <td><?php echo $notif['attempts']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
