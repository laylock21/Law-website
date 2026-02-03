<?php
/**
 * Lawyer Header Partial with Expandable Sidebar
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
<style>
/* Lawyer Sidebar Minimal Styles - Unique Naming */
:root {
    --lw-white: #FFFFFF;
    --lw-navy: #3a3a3a;
    --lw-gold: #C5A253;
    --lw-gray-light: #F8F9FA;
    --lw-text-dark: #212529;
    --lw-text-light: #6C757D;
    --lw-font-serif: 'Playfair Display', serif;
    --lw-font-sans: 'Inter', sans-serif;
}

.lw-sidebar {
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

.lw-sidebar:not(.lw-collapsed) {
    width: 260px;
}

.lw-sidebar-header {
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    min-height: 80px;
}

.lw-sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    overflow: hidden;
}

.lw-brand-icon {
    background: var(--lw-gold);
    color: var(--lw-navy);
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

.lw-brand-text {
    white-space: nowrap;
    opacity: 0;
    width: 0;
    transition: opacity 0.3s ease;
}

.lw-sidebar:not(.lw-collapsed) .lw-brand-text {
    opacity: 1;
    width: auto;
}

.lw-brand-text h2 {
    font-size: 18px;
    font-weight: 600;
    color: white !important;
    margin: 0;
    font-family: var(--lw-font-serif);
}

.lw-brand-text p {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
    margin: 2px 0 0 0;
}

.lw-sidebar-toggle {
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

.lw-sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

.lw-sidebar-nav {
    flex: 1;
    padding: 20px 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.lw-sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.lw-sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
}

.lw-sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.lw-sidebar-link {
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

.lw-sidebar-link::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: var(--lw-gold);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}

.lw-sidebar-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.lw-sidebar-link:hover::before {
    transform: scaleY(1);
}

.lw-sidebar-link.lw-active {
    background: rgba(197, 162, 83, 0.2);
    color: var(--lw-gold);
    font-weight: 600;
}

.lw-sidebar-link.lw-active::before {
    transform: scaleY(1);
}

.lw-sidebar-link i {
    font-size: 18px;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
}

.lw-sidebar-link span {
    white-space: nowrap;
    opacity: 0;
    width: 0;
    transition: opacity 0.3s ease;
}

.lw-sidebar:not(.lw-collapsed) .lw-sidebar-link span {
    opacity: 1;
    width: auto;
}

.lw-sidebar .lw-sidebar-link {
    justify-content: center;
    padding: 14px 0;
}

.lw-sidebar:not(.lw-collapsed) .lw-sidebar-link {
    justify-content: flex-start;
    padding: 14px 20px;
}

.lw-sidebar-footer {
    padding: 20px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 32px;
}

.lw-sidebar-link.lw-logout {
    color: rgba(255, 107, 122, 0.9);
}

.lw-sidebar-link.lw-logout:hover {
    background: rgba(220, 53, 69, 0.2);
    color: #ff6b7a;
}

.lw-topbar {
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

.lw-topbar::before {
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

.lw-topbar.lw-dimmed::before {
    opacity: 1;
    visibility: visible;
}

.lw-topbar-content {
    position: relative;
    z-index: 2;
    height: 100%;
    padding: 0 30px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.lw-mobile-menu-toggle {
    display: none;
    background: transparent;
    border: none;
    font-size: 24px;
    color: var(--lw-navy);
    cursor: pointer;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.lw-mobile-menu-toggle:hover {
    background: var(--lw-gray-light);
}

.lw-topbar-title h1 {
    font-size: 24px;
    font-weight: 600;
    color: var(--lw-navy) !important;
    margin: 0;
}

.lw-topbar-title p {
    font-size: 14px;
    color: var(--lw-text-light);
    margin: 4px 0 0 0;
}

.lw-page {
    padding-left: 70px;
    padding-top: 80px;
    transition: none;
}

.lw-main-content {
    min-height: calc(100vh - 80px);
}

.lw-sidebar-overlay {
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

.lw-sidebar-overlay.lw-active {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 768px) {
    .lw-sidebar {
        transform: translateX(-100%);
        width: 260px;
    }

    .lw-sidebar.lw-mobile-open {
        transform: translateX(0);
    }

    .lw-topbar {
        left: 0 !important;
    }

    .lw-page {
        padding-left: 0 !important;
    }

    .lw-mobile-menu-toggle {
        display: block;
    }

    .lw-topbar-content {
        padding: 0 15px;
    }

    .lw-topbar-title h1 {
        font-size: 18px;
    }

    .lw-topbar-title p {
        font-size: 12px;
    }

    .lw-main-content {
        padding: 20px 15px;
    }

    .lw-sidebar.lw-mobile-open::after {
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
<aside class="lw-sidebar lw-collapsed" id="lwSidebar">
    <div class="lw-sidebar-header">
        <div class="lw-sidebar-brand">
            <div class="lw-brand-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="lw-brand-text">
                <h2>MD Law</h2>
                <p>Lawyer Portal</p>
            </div>
        </div>
        <button class="lw-sidebar-toggle" id="lwSidebarToggle" aria-label="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="lw-sidebar-nav">
        <a href="dashboard.php" class="lw-sidebar-link <?php echo ($active_page === 'dashboard') ? 'lw-active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        <a href="consultations.php" class="lw-sidebar-link <?php echo ($active_page === 'consultations') ? 'lw-active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Consultations</span>
        </a>
        <a href="availability.php" class="lw-sidebar-link <?php echo ($active_page === 'availability') ? 'lw-active' : ''; ?>">
            <i class="fas fa-clock"></i>
            <span>Availability</span>
        </a>
        <a href="edit_profile.php" class="lw-sidebar-link <?php echo ($active_page === 'profile') ? 'lw-active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="export.php" class="lw-sidebar-link <?php echo ($active_page === 'export') ? 'lw-active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span>Export</span>
        </a>
    </nav>
    
    <div class="lw-sidebar-footer">
        <a href="../logout.php" class="lw-sidebar-link lw-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<!-- Overlay for expanded sidebar -->
<div class="lw-sidebar-overlay" id="lwSidebarOverlay"></div>

<!-- Top Header Bar -->
<header class="lw-topbar">
    <div class="lw-topbar-content">
        <button class="lw-mobile-menu-toggle" id="lwMobileMenuToggle" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="lw-topbar-title">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($active_page === 'dashboard'): ?>
                <p>Manage consultations and system overview</p>
            <?php elseif ($active_page === 'consultations'): ?>
                <p>View and manage consultation requests</p>
            <?php elseif ($active_page === 'availability'): ?>
                <p>Manage your schedule and availability</p>
            <?php elseif ($active_page === 'profile'): ?>
                <p>Update your profile information and settings</p>
            <?php elseif ($active_page === 'export'): ?>
                <p>Export your availability schedule in various formats</p>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
// Lawyer Sidebar toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('lwSidebar');
    const sidebarToggle = document.getElementById('lwSidebarToggle');
    const sidebarOverlay = document.getElementById('lwSidebarOverlay');
    const mobileMenuToggle = document.getElementById('lwMobileMenuToggle');
    const topbar = document.querySelector('.lw-topbar');
    
    // Function to expand sidebar
    function expandSidebar() {
        sidebar.classList.remove('lw-collapsed');
        if (window.innerWidth > 768) {
            sidebarOverlay.classList.add('lw-active');
            if (topbar) topbar.classList.add('lw-dimmed');
        }
    }
    
    // Function to collapse sidebar
    function collapseSidebar() {
        sidebar.classList.add('lw-collapsed');
        sidebarOverlay.classList.remove('lw-active');
        sidebar.classList.remove('lw-mobile-open');
        if (topbar) topbar.classList.remove('lw-dimmed');
    }
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar.classList.contains('lw-collapsed')) {
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
            sidebar.classList.toggle('lw-mobile-open');
            sidebar.classList.remove('lw-collapsed');
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
                sidebar.classList.remove('lw-mobile-open');
            }
        }
    });
    
    // Prevent clicks inside sidebar from closing it
    sidebar.addEventListener('click', function(e) {
        e.stopPropagation();
    });
});
</script>