<?php
/**
 * Admin Sidebar Partial with Expandable Sidebar
 * Reusable sidebar component for admin pages
 * 
 * Usage: include 'partials/sidebar.php';
 * 
 * Variables that can be set before including:
 * - $page_title: Page title (default: "Admin Panel")
 * - $active_page: Current page identifier for navigation highlighting
 */

// Set defaults if not provided
$page_title = $page_title ?? "Admin Panel";
$active_page = $active_page ?? "";
?>
<!-- Expandable Sidebar Navigation -->
<aside class="lawyer-sidebar collapsed" id="lawyerSidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <img src="../src/img/logo.svg" alt="MD Law Logo" style="width: 40px; height: 40px;">
            </div>
            <div class="brand-text">
                <h2>MD Law</h2>
                <p>Admin Panel</p>
            </div>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="sidebar-link <?php echo ($active_page === 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="consultations.php" class="sidebar-link <?php echo ($active_page === 'consultations') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Consultations</span>
        </a>
        <a href="manage_lawyers.php" class="sidebar-link <?php echo ($active_page === 'lawyers') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Lawyers</span>
        </a>
        <a href="process_emails.php" class="sidebar-link <?php echo ($active_page === 'emails') ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Email System</span>
        </a>
        <a href="notification_queue.php" class="sidebar-link <?php echo ($active_page === 'queue') ? 'active' : ''; ?>">
            <i class="fas fa-list"></i>
            <span>Queue</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="sidebar-link logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay for expanded sidebar -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Top Header Bar -->
<header class="lawyer-topbar">
    <div class="topbar-content">
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($active_page === 'dashboard'): ?>
                <p>Consultation and lawyers system overview</p>
            <?php elseif ($active_page === 'consultations'): ?>
                <p>Consultation and lawyer management oversight</p>
            <?php elseif ($active_page === 'lawyers'): ?>
                <p>Create, manage, and monitor lawyer accounts</p>
            <?php elseif ($active_page === 'emails'): ?>
                <p>Configure email sending for pending notifications</p>
            <?php elseif ($active_page === 'queue'): ?>
                <p>Monitor and manage email notification queue status</p>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('lawyerSidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const topbar = document.querySelector('.lawyer-topbar');
    
    // Function to expand sidebar
    function expandSidebar() {
        sidebar.classList.remove('collapsed');
        if (window.innerWidth > 768) {
            sidebarOverlay.classList.add('active');
            if (topbar) topbar.classList.add('dimmed');
        }
    }
    
    // Function to collapse sidebar
    function collapseSidebar() {
        sidebar.classList.add('collapsed');
        sidebarOverlay.classList.remove('active');
        sidebar.classList.remove('mobile-open');
        if (topbar) topbar.classList.remove('dimmed');
    }
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('collapsed')) {
                expandSidebar();
            } else {
                collapseSidebar();
            }
        });
    }
    
    // Mobile toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('mobile-open');
            sidebar.classList.remove('collapsed');
        });
    }
    
    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            collapseSidebar();
        });
    }
    
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !mobileMenuToggle.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });
    
    // Prevent clicks inside sidebar from closing it
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>
