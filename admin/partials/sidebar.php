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
<style>
/* Admin Sidebar Minimal Styles - Unique Naming */
:root {
    --adm-white: #FFFFFF;
    --adm-navy: #3a3a3a;
    --adm-gold: #C5A253;
    --adm-gray-light: #F8F9FA;
    --adm-text-dark: #212529;
    --adm-text-light: #6C757D;
    --adm-font-serif: 'Playfair Display', serif;
    --adm-font-sans: 'Inter', sans-serif;
}

.adm-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 70px;
    background: #3a3a3a;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.adm-sidebar:not(.adm-collapsed) {
    width: 260px;
}

.adm-sidebar-header {
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    min-height: 80px;
}

.adm-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    overflow: hidden;
}

.adm-brand-icon {
    background: var(--adm-gold);
    color: var(--adm-navy);
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(197, 162, 83, 0.3);
}

.adm-brand-text {
    white-space: nowrap;
    opacity: 0;
    width: 0;
    transition: opacity 0.3s ease;
}

.adm-sidebar:not(.adm-collapsed) .adm-brand-text {
    opacity: 1;
    width: auto;
}

.adm-brand-text h2 {
    font-size: 18px;
    font-weight: 600;
    color: white !important;
    margin: 0;
    font-family: var(--adm-font-serif);
}

.adm-brand-text p {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    margin: 2px 0 0 0;
}

.adm-sidebar-toggle {
    background: transparent;
    border: none;
    color: white;
    font-size: 20px;
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.adm-sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

.adm-sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.adm-sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.adm-sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.adm-sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.adm-sidebar-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.adm-sidebar-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--adm-gold);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.adm-sidebar-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.adm-sidebar-link:hover::before {
    transform: scaleY(1);
}

.adm-sidebar-link.adm-active {
    background: rgba(197, 162, 83, 0.2);
    color: var(--adm-gold);
    font-weight: 600;
}

.adm-sidebar-link.adm-active::before {
    transform: scaleY(1);
}

.adm-sidebar-link i {
    font-size: 18px;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
}

.adm-sidebar-link span {
    white-space: nowrap;
    opacity: 0;
    width: 0;
    transition: opacity 0.3s ease;
}

.adm-sidebar:not(.adm-collapsed) .adm-sidebar-link span {
    opacity: 1;
    width: auto;
}

.adm-sidebar .adm-sidebar-link {
    justify-content: center;
    padding: 14px 0;
}

.adm-sidebar:not(.adm-collapsed) .adm-sidebar-link {
    justify-content: flex-start;
    padding: 14px 20px;
}

.adm-sidebar-footer {
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.adm-sidebar-link.adm-logout {
    color: rgba(255, 107, 122, 0.9);
}

.adm-sidebar-link.adm-logout:hover {
    background: rgba(220, 53, 69, 0.2);
    color: #ff6b7a;
}

.adm-topbar {
    position: fixed;
    top: 0;
    left: 70px;
    right: 0;
    height: 80px;
    background: white;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    z-index: 999;
    transition: none;
}

.adm-topbar::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    pointer-events: none;
    z-index: 1;
}

.adm-topbar.adm-dimmed::before {
    opacity: 1;
    visibility: visible;
}

.adm-topbar-content {
    position: relative;
    z-index: 2;
    height: 100%;
    padding: 0 30px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.adm-mobile-menu-toggle {
    display: none;
    background: transparent;
    border: none;
    font-size: 24px;
    color: var(--adm-navy);
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.adm-mobile-menu-toggle:hover {
    background: var(--adm-gray-light);
}

.adm-topbar-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--adm-navy) !important;
    margin: 0;
}

.adm-topbar-title p {
    font-size: 14px;
    color: var(--adm-text-light);
    margin: 4px 0 0 0;
}

.adm-page {
    padding-left: 70px;
    padding-top: 80px;
    transition: none;
}

.adm-main-content {
    min-height: calc(100vh - 80px);
}

.adm-sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.adm-sidebar-overlay.adm-active {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 768px) {
    .adm-sidebar {
        transform: translateX(-100%);
        width: 260px;
    }

    .adm-sidebar.adm-mobile-open {
        transform: translateX(0);
    }

    .adm-topbar {
        left: 0 !important;
    }

    .adm-page {
        padding-left: 0 !important;
    }

    .adm-mobile-menu-toggle {
        display: block;
    }

    .adm-topbar-content {
        padding: 0 15px;
    }

    .adm-topbar-title h1 {
        font-size: 18px;
    }

    .adm-topbar-title p {
        font-size: 12px;
    }

    .adm-main-content {
        padding: 20px 15px;
    }

    .adm-sidebar.adm-mobile-open::after {
        content: '';
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: -1;
    }
}
</style>
<!-- Expandable Sidebar Navigation -->
<aside class="adm-sidebar adm-collapsed" id="admSidebar">
    <div class="adm-sidebar-header">
        <div class="adm-sidebar-brand">
            <div class="adm-brand-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="adm-brand-text">
                <h2>MD Law</h2>
                <p>Admin Panel</p>
            </div>
        </div>
        <button class="adm-sidebar-toggle" id="admSidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="adm-sidebar-nav">
        <a href="dashboard.php" class="adm-sidebar-link <?php echo ($active_page === 'dashboard') ? 'adm-active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="consultations.php" class="adm-sidebar-link <?php echo ($active_page === 'consultations') ? 'adm-active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Consultations</span>
        </a>
        <a href="create_consultation.php" class="adm-sidebar-link <?php echo ($active_page === 'create_consultation') ? 'adm-active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Create Consultation</span>
        </a>
        <a href="manage_lawyers.php" class="adm-sidebar-link <?php echo ($active_page === 'lawyers') ? 'adm-active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Lawyers</span>
        </a>
        <a href="practice_areas.php" class="adm-sidebar-link <?php echo ($active_page === 'practice_areas') ? 'adm-active' : ''; ?>">
            <i class="fas fa-briefcase"></i>
            <span>Practice Areas</span>
        </a>
        <a href="process_emails.php" class="adm-sidebar-link <?php echo ($active_page === 'emails') ? 'adm-active' : ''; ?>">
            <i class="fas fa-envelope"></i>
            <span>Email System</span>
        </a>
        <a href="notification_queue.php" class="adm-sidebar-link <?php echo ($active_page === 'queue') ? 'adm-active' : ''; ?>">
            <i class="fas fa-list"></i>
            <span>Queue</span>
        </a>
        <a href="export.php" class="adm-sidebar-link <?php echo ($active_page === 'export') ? 'adm-active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span>Export</span>
        </a>
    </nav>
    
    <div class="adm-sidebar-footer">
        <a href="../logout.php" class="adm-sidebar-link adm-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay for expanded sidebar -->
<div class="adm-sidebar-overlay" id="admSidebarOverlay"></div>

<!-- Top Header Bar -->
<header class="adm-topbar">
    <div class="adm-topbar-content">
        <button class="adm-mobile-menu-toggle" id="admMobileMenuToggle" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="adm-topbar-title">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($active_page === 'dashboard'): ?>
                <p>Consultation and lawyers system overview</p>
            <?php elseif ($active_page === 'consultations'): ?>
                <p>Consultation and lawyer management oversight</p>
            <?php elseif ($active_page === 'create_consultation'): ?>
                <p>Manually create a new consultation request</p>
            <?php elseif ($active_page === 'lawyers'): ?>
                <p>Create, manage, and monitor lawyer accounts</p>
            <?php elseif ($active_page === 'practice_areas'): ?>
                <p>Manage legal service categories and specializations</p>
            <?php elseif ($active_page === 'emails'): ?>
                <p>Configure email sending for pending notifications</p>
            <?php elseif ($active_page === 'queue'): ?>
                <p>Monitor and manage email notification queue status</p>
            <?php elseif ($active_page === 'export'): ?>
                <p>Export consultation data in various formats</p>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Admin Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('admSidebar');
    const sidebarToggle = document.getElementById('admSidebarToggle');
    const sidebarOverlay = document.getElementById('admSidebarOverlay');
    const mobileMenuToggle = document.getElementById('admMobileMenuToggle');
    const topbar = document.querySelector('.adm-topbar');
    
    // Function to expand sidebar
    function expandSidebar() {
        sidebar.classList.remove('adm-collapsed');
        if (window.innerWidth > 768) {
            sidebarOverlay.classList.add('adm-active');
            if (topbar) topbar.classList.add('adm-dimmed');
        }
    }
    
    // Function to collapse sidebar
    function collapseSidebar() {
        sidebar.classList.add('adm-collapsed');
        sidebarOverlay.classList.remove('adm-active');
        sidebar.classList.remove('adm-mobile-open');
        if (topbar) topbar.classList.remove('adm-dimmed');
    }
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('adm-collapsed')) {
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
            sidebar.classList.toggle('adm-mobile-open');
            sidebar.classList.remove('adm-collapsed');
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
                sidebar.classList.remove('adm-mobile-open');
            }
        }
    });
    
    // Prevent clicks inside sidebar from closing it
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>
