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
    $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'pending'");
    $pending_count = (int)$pending_stmt->fetchColumn();
    
    $confirmed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'confirmed'");
    $confirmed_count = (int)$confirmed_stmt->fetchColumn();
    
    $completed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'completed'");
    $completed_count = (int)$completed_stmt->fetchColumn();
    
    $cancelled_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'cancelled'");
    $cancelled_count = (int)$cancelled_stmt->fetchColumn();
    
    // Initialize variables to ensure they're never null (already cast to int above)
    $pending_count ??= 0;
    $confirmed_count ??= 0;
    $completed_count ??= 0;
    $cancelled_count ??= 0;
    
    // Recent consultations
    $recent_stmt = $pdo->query("SELECT c_id, c_full_name, c_status, c_consultation_date, created_at, lawyer_id FROM consultations ORDER BY created_at DESC LIMIT 5");
    $recent_consultations = $recent_stmt->fetchAll();
    
    // Get lawyer names for recent consultations
    $lawyer_names = [];
    if (!empty($recent_consultations)) {
        $lawyer_ids = array_values(array_filter(array_unique(array_column($recent_consultations, 'lawyer_id'))));
        if (!empty($lawyer_ids)) {
            $placeholders = implode(',', array_fill(0, count($lawyer_ids), '?'));
            $lawyer_stmt = $pdo->prepare("SELECT lawyer_id, lp_fullname as full_name FROM lawyer_profile WHERE lawyer_id IN ($placeholders)");
            $lawyer_stmt->execute($lawyer_ids);
            $lawyer_names = $lawyer_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
    
    // Practice area distribution - get from lawyer_specializations
    $area_stmt = $pdo->query("
        SELECT pa.area_name, COUNT(DISTINCT ls.lawyer_id) as count 
        FROM practice_areas pa
        LEFT JOIN lawyer_specializations ls ON pa.pa_id = ls.pa_id
        GROUP BY pa.pa_id, pa.area_name 
        ORDER BY count DESC
    ");
    $practice_areas = $area_stmt->fetchAll();
    
    // Lawyer statistics
    $total_lawyers_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer'");
    $total_lawyers = (int)$total_lawyers_stmt->fetchColumn();
    
    $active_lawyers_stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'lawyer' AND is_active = 1");
    $active_lawyers = (int)$active_lawyers_stmt->fetchColumn();
    
    // Debug logging
    error_log("Admin Dashboard - Total Lawyers: $total_lawyers, Active Lawyers: $active_lawyers");
    
    // Additional debug - check if queries are working
    $debug_users = $pdo->query("SELECT user_id, username, role, is_active FROM users")->fetchAll();
    error_log("All users in database: " . print_r($debug_users, true));
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Admin Dashboard Error: " . $e->getMessage());
    
    // Set default values so page doesn't break
    $total_lawyers = 0;
    $active_lawyers = 0;
    $total_consultations = 0;
    $pending_count = 0;
    $confirmed_count = 0;
    $completed_count = 0;
    $cancelled_count = 0;
    $recent_consultations = [];
    $practice_areas = [];
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
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: #c5a253;
            color: white !important;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 300px;
            animation: slideIn 0.3s ease-out, fadeOut 0.3s ease-in 2.7s;
            opacity: 0;
            animation-fill-mode: forwards;
        }
        
        .toast i {
            font-size: 24px;
            color: white !important;
        }
        
        .toast-content h3 {
            margin: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: white !important;
        }
        
        .toast-content p {
            margin: 4px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
            color: white !important;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
        
        /* Welcome Overview Card */
        .welcome-overview-card {
            background: white;
            padding: 20px 24px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
            text-align: left;
        }
        
        .welcome-overview-card p {
            margin: 0;
            font-size: 1.2rem;
            color: #6c757d;
        }
        
        /* Recent Consultation Requests Font Sizes */
        .admin-consultation-item .admin-consultation-info h4 {
            font-size: 1.2rem;
        }
        
        .admin-consultation-item .admin-consultation-info p {
            font-size: 0.9rem;
        }
        
        /* Unified Font Sizes */
        .admin-area-name {
            font-size: 1.2rem;
        }
        
        .action-content h4 {
            font-size: 1.2rem;
        }
        
        /* Add yellow border bottom to section headers */
        .admin-section-header {
            border-bottom: 3px solid #c5a253 !important;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <!-- Toast Notification -->
    <div class="toast-container">
        <div class="toast" id="welcomeToast">
            <i class="fas fa-chart-line"></i>
            <div class="toast-content">
                <h3>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</h3>
            </div>
        </div>
    </div>

    <main class="admin-main-content">
        <div class="container">

            <!-- Welcome Overview Card -->
            <div class="welcome-overview-card">
                <p>Here's an overview of your consultation system and key metrics</p>
            </div>

            <div class="admin-stats-grid dashboard-stats">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_lawyers ?? 0; ?></div>
                    <div class="admin-stat-label">Total Lawyers</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $active_lawyers ?? 0; ?></div>
                    <div class="admin-stat-label">Active Lawyers</div>
                </div>
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
            </div>

            <div class="admin-horizontal-layout">
                <div class="admin-practice-areas">
                    <div class="admin-section-header">
                        <h3>Lawyer Practice Areas</h3>
                    </div>
                    
                    <?php if (empty($practice_areas)): ?>
                        <div style="padding: 2rem; text-align: center; color: #6c757d;">
                            No data available
                        </div>
                    <?php else: ?>
                        <?php foreach ($practice_areas as $area): ?>
                            <div class="admin-area-item">
                                <span class="admin-area-name"><?php echo htmlspecialchars($area['area_name']); ?></span>
                                <span class="admin-area-count"><?php echo $area['count']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

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
                                    <h4><?php echo htmlspecialchars($consultation['c_full_name']); ?></h4>
                                    <p><?php echo isset($lawyer_names[$consultation['lawyer_id']]) ? htmlspecialchars($lawyer_names[$consultation['lawyer_id']]) : 'No lawyer assigned'; ?> • <?php echo date('M d, Y', strtotime($consultation['created_at'])); ?></p>
                                </div>
                                <span class="admin-status-badge admin-status-<?php echo $consultation['c_status']; ?>">
                                    <?php echo ucfirst($consultation['c_status']); ?>
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
                        <a href="create_consultation.php" class="admin-quick-action-btn">
                            <div class="action-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="action-content">
                                <h4>Create Consultation</h4>
                                <p>Manually create a new consultation request</p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </a>
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

    <script>
        // Auto-hide toast after 3 seconds
        setTimeout(() => {
            const toast = document.getElementById('welcomeToast');
            if (toast) {
                toast.style.display = 'none';
            }
        }, 3000);
    </script>
</body>
</html>
