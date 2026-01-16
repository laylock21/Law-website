<?php
/**
 * Admin Dashboard
 * Overview of consultation statistics
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Get statistics
try {
    $pdo = getDBConnection();
    
    // Total consultations
    $total_stmt = $pdo->query("SELECT COUNT(*) FROM consultations");
    $total_consultations = $total_stmt->fetchColumn();
    
    // Status counts
    $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'pending'");
    $pending_count = $pending_stmt->fetchColumn();
    
    $confirmed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'confirmed'");
    $confirmed_count = $confirmed_stmt->fetchColumn();
    
    $completed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'completed'");
    $completed_count = $completed_stmt->fetchColumn();
    
    $cancelled_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'cancelled'");
    $cancelled_count = $cancelled_stmt->fetchColumn();
    
    // Recent consultations
    $recent_stmt = $pdo->query("SELECT * FROM consultations ORDER BY created_at DESC LIMIT 5");
    $recent_consultations = $recent_stmt->fetchAll();
    
    // Practice area distribution
    $area_stmt = $pdo->query("SELECT practice_area, COUNT(*) as count FROM consultations GROUP BY practice_area ORDER BY count DESC");
    $practice_areas = $area_stmt->fetchAll();
    
    // Lawyer statistics
    $total_lawyers_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer'");
    $total_lawyers = $total_lawyers_stmt->fetchColumn();
    
    $active_lawyers_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer' AND is_active = 1");
    $active_lawyers = $active_lawyers_stmt->fetchColumn();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Admin Panel";
$active_page = "dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | MD Law Firm</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <div class="admin-welcome-section">
                <div class="admin-card-header">
                    <h2><i class="fas fa-chart-line"></i> Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h2>
                </div>
                <div class="admin-card-body">
                    <p>Here's an overview of your consultation system and key metrics</p>
                </div>
            </div>

            <div class="admin-stats-grid">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_consultations ?? 0; ?></div>
                    <div class="admin-stat-label">Total Consultations</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $pending_count ?? 0; ?></div>
                    <div class="admin-stat-label">Pending</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $confirmed_count ?? 0; ?></div>
                    <div class="admin-stat-label">Confirmed</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $completed_count ?? 0; ?></div>
                    <div class="admin-stat-label">Completed</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $cancelled_count ?? 0; ?></div>
                    <div class="admin-stat-label">Cancelled</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_lawyers ?? 0; ?></div>
                    <div class="admin-stat-label">Total Lawyers</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $active_lawyers ?? 0; ?></div>
                    <div class="admin-stat-label">Active Lawyers</div>
                </div>
            </div>

            <div class="admin-practice-areas">
                        <div class="admin-section-header">
                            <h3>Practice Areas</h3>
                        </div>
                        
                        <?php if (empty($practice_areas)): ?>
                            <div style="padding: 2rem; text-align: center; color: #6c757d;">
                                No data available
                            </div>
                        <?php else: ?>
                            <?php foreach ($practice_areas as $area): ?>
                                <div class="admin-area-item">
                                    <span class="admin-area-name"><?php echo htmlspecialchars($area['practice_area']); ?></span>
                                    <span class="admin-area-count"><?php echo $area['count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
            </div>

            <div class="admin-dashboard-grid">
                <div class="admin-recent-consultations">
                    <div class="admin-section-header">
                        <h3>Recent Consultation Requests</h3>
                    </div>
                    
                    <?php if (empty($recent_consultations)): ?>
                        <div style="padding: 2rem; text-align: center; color: #6c757d;">
                            No consultations yet. Submit a form to see data here.
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_consultations as $consultation): ?>
                            <div class="admin-consultation-item">
                                <div class="admin-consultation-info">
                                    <h4><?php echo htmlspecialchars($consultation['full_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($consultation['practice_area']); ?> • <?php echo date('M d, Y', strtotime($consultation['created_at'])); ?></p>
                                </div>
                                <span class="admin-status-badge admin-status-<?php echo $consultation['status']; ?>">
                                    <?php echo ucfirst($consultation['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="admin-quick-actions-card">
                    <div class="admin-section-header">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="admin-quick-actions-vertical">
                        <a href="consultations.php" class="admin-quick-action-btn">
                            <div class="action-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="action-content">
                                <h4>Manage Consultations</h4>
                                <p>View and manage all consultation requests</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                        <a href="process_emails.php" class="admin-quick-action-btn">
                            <div class="action-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="action-content">
                                <h4>Email System</h4>
                                <p>Send and manage email notifications</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                        <a href="manage_lawyers.php" class="admin-quick-action-btn">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="action-content">
                                <h4>Manage Lawyers</h4>
                                <p>Add, edit, and manage lawyer profiles</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            
        </div>
    </main>
</body>
</html>
