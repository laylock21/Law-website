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
        u.email as user_email
    FROM notification_queue nq
    LEFT JOIN users u ON nq.user_id = u.user_id
    ORDER BY nq.created_at DESC
    LIMIT 100
");
$stmt->execute();
$notifications = $stmt->fetchAll();

// Get statistics
$stats_stmt = $pdo->query("
    SELECT 
        nq_status,
        COUNT(*) as count
    FROM notification_queue
    GROUP BY nq_status
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
    <link rel="stylesheet" href="../src/admin/css/styles.css?v=2.2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php 
            $pending_count = $stats['pending'] ?? 0;
            if ($pending_count > 0): ?>
                <div class="alert alert-warning nq-mobile-alert">
                    <div style="display:flex;align-items:center;">
                        <strong>📧 Action Required:</strong> There are <?php echo $pending_count; ?> pending email(s) waiting to be sent.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success nq-mobile-alert">
                    <strong>Email System Active:</strong> All notifications are being sent automatically.
                </div>
            <?php endif; ?>
            

            
            
            
            <h2 class="nq-mobile-heading" style="padding:16px 0 16px 0;">Recent Notifications</h2>
            
            <?php if (empty($notifications)): ?>
                <p class="nq-mobile-empty" style="text-align: center; padding: 40px; color: #6c757d;">
                    No notifications in queue.
                </p>
            <?php else: ?>
                <div class="nq-mobile-table-wrapper">
                    <table class="notification-table nq-mobile-table">
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
                                    <td><?php echo $notif['nq_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($notif['email']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($notif['subject']); ?></td>
                                    <td><?php echo ucwords(str_replace('_', ' ', $notif['notification_type'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $notif['nq_status']; ?>">
                                            <?php echo ucfirst($notif['nq_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></td>
                                    <td><?php echo $notif['attempts']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
