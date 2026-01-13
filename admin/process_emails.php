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
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin-page">
    <?php include 'partials/header.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <div class="page-header">
                <h1>📧 Email Processor</h1>
                <p>Manually trigger email sending for pending notifications</p>
                <div style="margin-top: 12px;">
                    <a href="notification_queue.php" class="btn btn-secondary" style="
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
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?> 

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid" style=gap:16px;margin-bottom:32px;>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                <div>Pending Emails</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['sent'] ?? 0; ?></div>
                <div>Sent Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['failed'] ?? 0; ?></div>
                <div>Failed</div>
            </div>
        </div>

        <!-- Manual Processing -->
        <div class="section" style="padding: 32px 48px 32px 48px">
            <h3><i class="fas fa-paper-plane"></i> Manual Email Processing</h3>
            
            <?php if (($stats['pending'] ?? 0) > 0): ?>
                <div class="alert alert-info">
                    <strong>📧 Ready to Send:</strong> There are <?php echo $stats['pending']; ?> pending email(s) in the queue.
                </div>
                
            <?php else: ?>
                <div class="alert alert-info">
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
                    <button type="submit" class="btn btn-primary" style="padding: 15px 30px; font-size: 18px;">
                        <i class="fas fa-paper-plane"></i> Send All Pending Emails
                    </button>
                </form>
        </div>



        <!-- Quick Actions -->
        <div class="section" style="text-align: center; padding: 32px 32px 32px 32px;">
            <h3 style="margin-bottom: 16px;"><i class="fas fa-tools"></i> Quick Actions</h3>
            <div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; align-items: center;">
                <a href="notification_queue.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View Full Queue
                </a>
                <a href="dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                </a>
                <a href="manage_lawyers.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Lawyers
                </a>
            </div>
        </div>

        <!-- Instructions -->
        <div class="section" style="
            background: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 16px 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        ">
            <h3 style="margin: 0 0 10px 0;"><i class="fas fa-info-circle"></i> How It Works</h3>
            <ol style="margin: 0 0 0 18px; padding: 0;">
                <li><strong>Automatic:</strong> Emails are sent automatically when lawyers block dates</li>
                <li><strong>Manual:</strong> Use this page to send any pending emails manually</li>
                <li><strong>Monitoring:</strong> Check the notification queue for failed emails</li>
                <li><strong>Retry:</strong> Failed emails are automatically retried up to 3 times</li>
            </ol>
        </div>

        <!-- Gmail Setup Instructions -->
        <div class="section" style="
            background: #f8f9fa;
            border-left: 4px solid #17a2b8;
            padding: 16px 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        ">
            <h3 style="margin: 0 0 10px 0;"><i class="fas fa-envelope-circle-check"></i> How to Enable Email Notifications</h3>
            <ol style="margin: 0 0 0 18px; padding: 0;">
                <li>
                    <strong>Get Gmail App Password:</strong>
                    <ul style="margin-top: 6px; list-style: disc; margin-left: 18px;">
                        <li>Go to Google Account → Security</li>
                        <li>Enable 2-Step Verification</li>
                        <li>Generate App Password for "Mail"</li>
                    </ul>
                </li>
                <li>Edit <code>/includes/EmailNotification.php</code></li>
                <li>Add your Gmail credentials to <code>$smtp_config</code></li>
                <li>Set <code>$smtp_enabled = true</code></li>
                <li>Install PHPMailer: <code>composer require phpmailer/phpmailer</code></li>
                <li><strong>Done:</strong> Notifications will be sent automatically!</li>
            </ol>
        </div>
        </div>
    </main>
</body>
</html>
