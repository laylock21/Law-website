<?php
/**
 * Lawyer Header Partial
 * Reusable header component for lawyer pages
 * 
 * Usage: include 'partials/header.php';
 * 
 * Variables that can be set before including:
 * - $page_title: Page title (default: "Lawyer Portal")
 * - $active_page: Current page identifier for navigation highlighting
 */

// Set defaults if not provided
$page_title = $page_title ?? "Lawyer Portal";
$active_page = $active_page ?? "";
?>
<!-- Bootstrap-Inspired Header Section -->
<div class="lawyering-dashboard-header" style="width: 100%; max-width: 100%;">
    <div class="container" style="max-width: 100%; padding-left: 24px; padding-right: 24px;">
        <div class="lawyering-dashboard-nav">
            <div class="lawyering-brand-section">
                <div class="lawyering-brand-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="lawyering-brand-info">
                    <h1>MD Law - <?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if ($active_page === 'dashboard'): ?>
                        <p>Manage consultations, lawyers, and system overview</p>
                    <?php elseif ($active_page === 'consultations'): ?>
                        <p>View and manage consultation requests</p>
                    <?php elseif ($active_page === 'availability'): ?>
                        <p>Manage your schedule and availability</p>
                    <?php elseif ($active_page === 'profile'): ?>
                        <p>Update your profile information and settings</p>
                    <?php else: ?>
                        <p>Manage consultations, lawyers, and system overview</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="lawyering-nav-actions">
                <a href="dashboard.php" class="lawyering-btn-nav <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="consultations.php" class="lawyering-btn-nav <?php echo ($active_page === 'consultations') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="availability.php" class="lawyering-btn-nav <?php echo ($active_page === 'availability') ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> Availability
                </a>
                <a href="edit_profile.php" class="lawyering-btn-nav <?php echo ($active_page === 'profile') ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="../logout.php" class="lawyering-btn-nav logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>