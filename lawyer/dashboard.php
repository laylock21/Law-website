<?php
/**
 * Lawyer Dashboard
 * Main interface for lawyers to manage their availability and view consultations
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];
$lawyer_name = $_SESSION['lawyer_name'];

// Pagination settings
$consultations_per_page = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $consultations_per_page;

// Get lawyer's consultations and profile info
try {
    $pdo = getDBConnection();
    
    // Get lawyer's profile picture and temporary password status
    $profile_stmt = $pdo->prepare("SELECT profile_picture, temporary_password FROM users WHERE id = ? AND role = 'lawyer'");
    $profile_stmt->execute([$lawyer_id]);
    $lawyer_profile = $profile_stmt->fetch();
    $profile_picture = $lawyer_profile['profile_picture'] ?? null;
    $has_temporary_password = ($lawyer_profile['temporary_password'] === 'temporary');
    
    // Get total count of consultations for pagination
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM consultations c 
        WHERE c.lawyer_id = ? OR c.lawyer_id IS NULL
    ");
    $count_stmt->execute([$lawyer_id]);
    $total_consultations = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_consultations / $consultations_per_page);
    
    // Get lawyer's consultations with pagination - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
    $consultations_stmt = $pdo->prepare("
        SELECT c.*, pa.area_name as practice_area_name 
        FROM consultations c 
        LEFT JOIN practice_areas pa ON c.practice_area = pa.area_name
        WHERE c.lawyer_id = ? OR c.lawyer_id IS NULL
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $consultations_stmt->execute([$lawyer_id, $consultations_per_page, $offset]);
    $consultations = $consultations_stmt->fetchAll();
    
    // Get lawyer's specializations
    $specializations_stmt = $pdo->prepare("
        SELECT pa.area_name 
        FROM lawyer_specializations ls 
        JOIN practice_areas pa ON ls.practice_area_id = pa.id 
        WHERE ls.user_id = ?
    ");
    $specializations_stmt->execute([$lawyer_id]);
    $specializations = $specializations_stmt->fetchAll();
    
    // Get weekly availability - fetch ALL availabilities
    $availability_stmt = $pdo->prepare("
        SELECT * FROM lawyer_availability 
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    $availability_stmt->execute([$lawyer_id]);
    $all_availabilities = $availability_stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Lawyer dashboard error: " . $e->getMessage());
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Lawyer Dashboard";
$active_page = "dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale-1.0">
    <title>Lawyer Dashboard - <?php echo htmlspecialchars($lawyer_name); ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>
<body class="lawyer-page">
        <?php include 'partials/header.php'; ?> 
        <header class="lawyer-dashboard-header">
            <div class="lawyer-dashboard-nav">
                <div class="lawyer-info">
                    <div class="lawyer-profile-section">
                        <?php if ($profile_picture && file_exists("../uploads/profile_pictures/" . $profile_picture)): ?>
                            <div class="lawyer-profile-picture">
                                <img src="../uploads/profile_pictures/<?php echo htmlspecialchars($profile_picture); ?>" 
                                     alt="<?php echo htmlspecialchars($lawyer_name); ?>'s Profile Picture" 
                                     class="profile-img">
                                <a href="edit_profile.php" class="profile-edit-overlay" title="Edit Profile">
                                    <img src="../src/img/Edit Icon.png" alt="Edit" class="edit-icon">
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="lawyer-profile-picture">
                                <div class="profile-placeholder">
                                    <?php echo strtoupper(substr($lawyer_name, 0, 1)); ?>
                                </div>
                                <a href="edit_profile.php" class="profile-edit-overlay" title="Edit Profile">
                                    <img src="../src/img/Edit Icon.png" alt="Edit" class="edit-icon">
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="lawyer-details">
                            <h1>Welcome, <?php echo htmlspecialchars($lawyer_name); ?></h1>
                            <p>Lawyer Portal Dashboard</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <main class="lawyer-main-content">
            <?php if (isset($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <?php if ($has_temporary_password): ?>
                <div class="temporary-password-notice" style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); border: 2px solid #ffc107; border-radius: 10px; padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 24px;">🔐</div>
                    <div>
                        <strong style="color: #856404;">Temporary Password Detected</strong>
                        <p style="margin: 4px 0 0 0; color: #856404;">You are using a temporary password. Please <button onclick="showPasswordModal()" style="background: none; border: none; color: #856404; text-decoration: underline; cursor: pointer; font-weight: bold;">change your password</button> for security.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-grid">
                <!-- Specializations Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">My Specializations</h3>
                    </div>
                    <div class="specializations-list">
                        <?php if (empty($specializations)): ?>
                            <p style="color: var(--text-light); text-align: center; padding: 20px; width: 100%;">
                                No specializations set. Contact admin to add your areas of expertise.
                            </p>
                        <?php else: ?>
                            <?php foreach ($specializations as $spec): ?>
                                <span class="specialization-tag"><?php echo htmlspecialchars($spec['area_name']); ?></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($specializations)): ?>
                        <div class="specialization-info">
                            <div class="info-icon">
                                <img src="../src/img/Expertise LOgo.png" alt="Expertise" class="expertise-logo">
                            </div>
                            <div class="info-text">
                                <strong>Your Areas of Expertise</strong>
                                <p>You are qualified to handle consultations in <?php echo count($specializations); ?> legal <?php echo count($specializations) === 1 ? 'area' : 'areas'; ?>: 
                                <strong style="color: var(--gold);">
                                    <?php 
                                    $spec_names = array_map(function($spec) {
                                        return $spec['area_name'];
                                    }, $specializations);
                                    echo implode(', ', $spec_names);
                                    ?>
                                </strong>. Clients can book consultations with you for these specializations.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Weekly Availability Card -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3 class="card-title">Weekly Availability</h3>
                        <a href="availability.php" class="btn-nav">Manage</a>
                    </div>
                    <div class="availability-container">
                        <?php if (empty($all_availabilities)): ?>
                            <div class="empty-availability-state">
                                <div class="empty-icon">📅</div>
                                <h4>No Schedule Set Yet</h4>
                                <p>Set your weekly availability to start accepting consultation bookings from clients.</p>
                                <a href="availability.php" class="btn-set-schedule">Set Your Schedule</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($all_availabilities as $availability): ?>
                                <div class="availability-item">
                                    <div class="availability-days">
                                        <strong>Available Days:</strong>
                                        <?php 
                                        $weekdays = explode(',', $availability['weekdays']);
                                        $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                        $available_days = [];
                                        foreach ($weekdays as $day) {
                                            $available_days[] = $day_names[$day];
                                        }
                                        echo implode(', ', $available_days);
                                        ?>
                                        <div style="margin-top: 12px;">
                                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($availability['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($availability['end_time'])); ?>
                                        </div>
                                        <div style="margin-top: 8px;">
                                            <strong>Max Appointments per Day:</strong> <?php echo $availability['max_appointments']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Consultations Card -->
                <div class="dashboard-card full-width" id="recent-consultations">
                    <div class="card-header">
                        <h3 class="card-title">Recent Consultations</h3>
                        <a href="consultations.php" class="btn-nav">View All</a>
                    </div>
                    <?php if (empty($consultations)): ?>
                        <div class="empty-consultations-state">
                            <div class="empty-icon">📋</div>
                            <h4>No Consultations Yet</h4>
                            <p>You haven't been assigned any consultations. New client requests will appear here once assigned to you.</p>
                        </div>
                    <?php else: ?>
                        <div class="consultations-table">
                            <div class="consultations-header">
                                <div class="col-client">Client</div>
                                <div class="col-practice">Practice Area</div>
                                <div class="col-contact">Contact Info</div>
                                <div class="col-date">Date & Time</div>
                                <div class="col-status">Status</div>
                                <div class="col-action">Action</div>
                            </div>
                            <?php foreach ($consultations as $consultation): ?>
                                <div class="consultation-row">
                                    <div class="col-client">
                                        <div class="client-name"><?php echo htmlspecialchars($consultation['full_name']); ?></div>
                                        <?php 
                                        $description = $consultation['case_description'];
                                        $shortDesc = substr($description, 0, 25);
                                        $isLong = strlen($description) > 25;
                                        ?>
                                        <div class="client-description-container">
                                            <div class="client-description" 
                                                 title="<?php echo htmlspecialchars($description); ?>"
                                                 data-full-description="<?php echo htmlspecialchars($description); ?>">
                                                <span class="desc-text"><?php echo htmlspecialchars($shortDesc); ?></span><?php if ($isLong): ?><span class="desc-ellipsis">...</span>
                                                <button type="button" class="desc-toggle" onclick="toggleDescription(this)">
                                                    <i class="fas fa-eye"></i>
                                                </button><?php endif; ?>
                                            </div>
                                            <?php if ($isLong): ?>
                                            <div class="full-description" style="display: none;">
                                                <?php echo htmlspecialchars($description); ?>
                                                <button type="button" class="desc-toggle-close" onclick="toggleDescription(this.parentElement.previousElementSibling.querySelector('.desc-toggle'))">
                                                    <i class="fas fa-eye-slash"></i>
                                                </button>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-practice">
                                        <div class="practice-area"><?php echo htmlspecialchars($consultation['practice_area_name'] ?? $consultation['practice_area']); ?></div>
                                    </div>
                                    <div class="col-contact">
                                        <div class="contact-email"><?php echo htmlspecialchars($consultation['email']); ?></div>
                                        <div class="contact-phone"><?php echo htmlspecialchars($consultation['phone']); ?></div>
                                    </div>
                                    <div class="col-date">
                                        <?php if ($consultation['consultation_date']): ?>
                                            <div class="consultation-date"><?php echo date('M j, Y', strtotime($consultation['consultation_date'])); ?></div>
                                            <?php if (!empty($consultation['consultation_time'])): ?>
                                                <div class="consultation-time"><?php echo date('g:i A', strtotime($consultation['consultation_time'])); ?></div>
                                            <?php endif; ?>
                                        <?php elseif ($consultation['selected_date']): ?>
                                            <div class="consultation-date"><?php echo date('M j, Y', strtotime($consultation['selected_date'])); ?></div>
                                            <?php if (!empty($consultation['consultation_time'])): ?>
                                                <div class="consultation-time"><?php echo date('g:i A', strtotime($consultation['consultation_time'])); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="consultation-date">Not scheduled</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-status">
                                        <span class="status-badge status-<?php echo $consultation['status']; ?>">
                                            <?php echo ucfirst($consultation['status']); ?>
                                        </span>
                                    </div>
                                    <div class="col-action">
                                        <a href="view_consultation.php?id=<?php echo (int)$consultation['id']; ?>" class="btn-open">Open</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination Navigation -->
                        <?php if ($total_pages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo (($current_page - 1) * $consultations_per_page) + 1; ?> to 
                                <?php echo min($current_page * $consultations_per_page, $total_consultations); ?> 
                                of <?php echo $total_consultations; ?> consultations
                            </div>
                            <nav class="pagination-nav">
                                <ul class="pagination">
                                    <!-- Previous Button -->
                                    <?php if ($current_page > 1): ?>
                                        <li class="page-item">
                                            <a href="?page=<?php echo $current_page - 1; ?>#recent-consultations" class="page-link">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">
                                                <i class="fas fa-chevron-left"></i> Previous
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Page Numbers -->
                                    <?php
                                    $start_page = max(1, $current_page - 2);
                                    $end_page = min($total_pages, $current_page + 2);
                                    
                                    // Show first page if not in range
                                    if ($start_page > 1): ?>
                                        <li class="page-item">
                                            <a href="?page=1#recent-consultations" class="page-link">1</a>
                                        </li>
                                        <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Current range of pages -->
                                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                        <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                            <?php if ($i == $current_page): ?>
                                                <span class="page-link current"><?php echo $i; ?></span>
                                            <?php else: ?>
                                                <a href="?page=<?php echo $i; ?>#recent-consultations" class="page-link"><?php echo $i; ?></a>
                                            <?php endif; ?>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <!-- Show last page if not in range -->
                                    <?php if ($end_page < $total_pages): ?>
                                        <?php if ($end_page < $total_pages - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a href="?page=<?php echo $total_pages; ?>#recent-consultations" class="page-link"><?php echo $total_pages; ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Next Button -->
                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item">
                                            <a href="?page=<?php echo $current_page + 1; ?>#recent-consultations" class="page-link">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">
                                                Next <i class="fas fa-chevron-right"></i>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Set flag for automatic password modal display
    <?php if ($has_temporary_password): ?>
    window.showPasswordModalOnLoad = true;
    <?php else: ?>
    window.showPasswordModalOnLoad = false;
    <?php endif; ?>

    function toggleDescription(button) {
        const container = button.closest('.client-description-container');
        const fullDesc = container.querySelector('.full-description');
        const shortDesc = container.querySelector('.client-description');
        
        if (fullDesc.style.display === 'none' || !fullDesc.style.display) {
            fullDesc.style.display = 'block';
            button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            button.title = 'Hide full description';
        } else {
            fullDesc.style.display = 'none';
            button.innerHTML = '<i class="fas fa-eye"></i>';
            button.title = 'Show full description';
        }
    }
    </script>

    <!-- Include Password Change Modal -->
    <?php include '../includes/password_modal.php'; ?>
</body>
</html>
