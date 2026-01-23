<?php
/**
 * Manual Email Processor
 * Allows admin to manually trigger email sending
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/EmailNotification.php';

$message = '';
$error = '';

// Handle manual email processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_emails') {
        try {
            $pdo = getDBConnection();
            
            if (!$pdo) {
                throw new Exception('Database connection failed');
            }
            
            $emailNotification = new EmailNotification($pdo);
            
            $result = $emailNotification->processPendingNotifications();
            
            if ($result['status'] === 'processed') {
                $message = "✅ Email processing completed! Sent: {$result['sent']}, Failed: {$result['failed']}, Total processed: {$result['pending']}";
            } elseif ($result['status'] === 'waiting') {
                $error = "⚠️ SMTP not configured. Please configure Gmail credentials in EmailNotification.php";
            } else {
                $error = "❌ Error: " . ($result['message'] ?? 'Unknown error occurred');
            }
            
        } catch (Exception $e) {
            $error = "❌ Error processing emails: " . $e->getMessage();
        }
    }
}

// Get current queue statistics
try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    $stats_stmt = $pdo->query("
        SELECT 
            status,
            COUNT(*) as count
        FROM notification_queue
        GROUP BY status
    ");
    $stats = $stats_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Get recent pending notifications
    $pending_stmt = $pdo->query("
        SELECT 
            nq.*,
            u.first_name,
            u.last_name
        FROM notification_queue nq
        LEFT JOIN users u ON nq.user_id = u.id
        WHERE nq.status = 'pending'
        ORDER BY nq.created_at DESC
        LIMIT 10
    ");
    $pending_notifications = $pending_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stats = [];
    $pending_notifications = [];
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Email Processor";
$active_page = "emails";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Emails - Admin</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <div class="page-header ep-mobile-header">
                <h1>📧 Email Processor</h1>
                <p>Manually trigger email sending for pending notifications</p>
                <div style="margin-top: 12px;">
                    <a href="notification_queue.php" class="btn btn-secondary ep-mobile-btn" style="
                        display: inline-flex;
                        align-items: center;
                        gap: 8px;
                        padding: 8px 16px;
                        font-size: 14px;
                        text-decoration: none;
                        background: #6c757d;
                        color: white;
                    border-radius: 6px;
                    transition: background-color 0.2s ease;
                " onmouseover="this.style.background='#5a6268'" onmouseout="this.style.background='#6c757d'">
                    <i class="fas fa-list"></i> View Queue
                </a>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success ep-mobile-alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?> 

        <?php if ($error): ?>
            <div class="alert alert-error ep-mobile-alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid ep-mobile-stats-grid" style=gap:16px;margin-bottom:32px;>
            <div class="stat-card ep-mobile-stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div>Pending Emails</div>
            </div>
            <div class="stat-card ep-mobile-stat-card">
                <div class="stat-number"><?php echo $stats['sent'] ?? 0; ?></div>
                <div>Sent Today</div>
            </div>
            <div class="stat-card ep-mobile-stat-card">
                <div class="stat-number"><?php echo $stats['failed'] ?? 0; ?></div>
                <div>Failed</div>
            </div>
        </div>

        <!-- Manual Processing -->
        <div class="section ep-mobile-section" style="padding: 32px 48px 32px 48px">
            <h3><i class="fas fa-paper-plane"></i> Manual Email Processing</h3>
            
            <?php if (($stats['pending'] ?? 0) > 0): ?>
                <div class="alert alert-info ep-mobile-alert">
                    <div class="ep-mobile-alert-content">
                        <div>
                            <strong>📧 Ready to Send:</strong> There are <?php echo $stats['pending']; ?> pending email(s) in the queue.
                        </div>
                        <a href="#SendAllPendingEmails" class="btn btn-primary ep-mobile-btn">
                            <i class="fas fa-paper-plane"></i> Send Now
                        </a>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="alert alert-info ep-mobile-alert">
                    <strong>✅ All Clear:</strong> No pending emails in the queue.
                </div>
            <?php endif; ?>

            <!-- Pending Notifications Preview -->
            <?php if (!empty($pending_notifications)): ?>
                    <h3><i class="fas fa-clock" style="margin-bottom:16px"></i> Pending Notifications</h3>
                    
                    <div class="table-responsive">
                        <table class="admin-consultations-table">
                            <thead>
                                <tr>
                                    <th style="color: white !important; background: #3a3a3a !important;">ID</th>
                                    <th style="color: white !important; background: #3a3a3a !important;">Recipient</th>
                                    <th style="color: white !important; background: #3a3a3a !important;">Subject</th>
                                    <th style="color: white !important; background: #3a3a3a !important;">Type</th>
                                    <th style="color: white !important; background: #3a3a3a !important;">Created</th>
                                    <th style="color: white !important; background: #3a3a3a !important;">Attempts</th> 
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_notifications as $notif): ?>
                                    <tr>
                                        <td><?php echo $notif['id']; ?></td>
                                        <td>
                                            <?php if ($notif['first_name']): ?>
                                                <strong><?php echo htmlspecialchars($notif['first_name'] . ' ' . $notif['last_name']); ?></strong><br>
                                            <?php endif; ?>
                                            <small><?php echo htmlspecialchars($notif['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($notif['subject']); ?></td>
                                        <td>
                                            <span class="admin-status-badge admin-status-<?php echo $notif['notification_type']; ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $notif['notification_type'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></td>
                                        <td><?php echo $notif['attempts']; ?>/3</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            <?php endif; ?>
                <form method="POST" style="text-align: center; margin: 20px 0; padding-top:32px">
                    <input type="hidden" name="action" value="process_emails">
                    <button type="submit" class="btn btn-primary" style="padding: 15px 30px; font-size: 18px;" id="SendAllPendingEmails">
                        <i class="fas fa-paper-plane"></i> Send All Pending Emails
                    </button>
                </form>
        </div>

        </div>
    </main>
</body>
</html>
