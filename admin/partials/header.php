<?php
/**
 * Admin Header Partial
 * Reusable header component for admin pages
 * 
 * Usage: include 'partials/header.php';
 * 
 * Variables that can be set before including:
 * - $page_title: Page title (default: "Admin Panel")
 * - $active_page: Current page identifier for navigation highlighting
 */

// Set defaults if not provided
$page_title = $page_title ?? "Admin Panel";
$active_page = $active_page ?? "";
?>

<!-- Bootstrap-Inspired Header Section -->
<div class="admin-dashboard-header" style="width: 100%; max-width: 100%;">
    <div class="container" style="max-width: 100%; padding-left: 24px; padding-right: 24px;">
        <div class="admin-dashboard-nav">
            <div class="admin-brand-section">
                <div class="admin-brand-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="admin-brand-info">
                    <h1>MD Law - <?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if ($active_page === 'consultations'): ?>
                        <p>Consultation and lawyer management oversight</p>
                    <?php elseif ($active_page === 'emails'): ?>
                        <p>Configure email sending for pending notifications</p>
                    <?php elseif ($active_page === 'lawyers'): ?>
                        <p>Create, manage, and monitor lawyer accounts</p>
                    <?php elseif ($active_page === 'queue'): ?>
                        <p>Monitor and manage email notification queue status</p>
                    <?php else: ?>
                        <p>Consultation and lawyers system overview</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="admin-nav-actions">
                <a href="dashboard.php" class="admin-btn-nav <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="consultations.php" class="admin-btn-nav <?php echo ($active_page === 'consultations') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="manage_lawyers.php" class="admin-btn-nav <?php echo ($active_page === 'lawyers') ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> Lawyers
                </a>
                <a href="process_emails.php" class="admin-btn-nav <?php echo ($active_page === 'emails') ? 'active' : ''; ?>"> 
                    <i class="fas fa-envelope"></i> Email
                </a>
                <a href="notification_queue.php" class="admin-btn-nav <?php echo ($active_page === 'queue') ? 'active' : ''; ?>">
                    <i class="fas fa-list"></i> Queue
                </a>
                <a href="../logout.php" class="admin-btn-nav logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>