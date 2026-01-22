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
    
    // Get weekly availability - fetch only active schedules
    $availability_stmt = $pdo->prepare("
        SELECT * FROM lawyer_availability 
        WHERE user_id = ? AND is_active = 1
        ORDER BY schedule_type, specific_date, created_at DESC
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
    <link rel="stylesheet" href="../src/lawyer/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

</head>
<body class="lawyer-page">
        <?php include 'partials/sidebar.php'; ?>
        
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
                            <?php 
                            // Get current day name
                            $today = date('l'); // e.g., "Monday"
                            $today_date = date('Y-m-d');
                            $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
                            
                            // Find today's schedule
                            $today_schedule = null;
                            foreach ($all_availabilities as $availability) {
                                if ($availability['schedule_type'] === 'weekly' && $availability['weekdays'] === $today) {
                                    $today_schedule = $availability;
                                    break;
                                }
                            }
                            
                            // Find next available day (tomorrow or later)
                            $next_schedule = null;
                            $next_date = null;
                            $day_order = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            
                            // Check for one-time schedules first (tomorrow onwards)
                            foreach ($all_availabilities as $availability) {
                                if ($availability['schedule_type'] === 'one_time' && 
                                    $availability['specific_date'] >= $tomorrow_date) {
                                    if (!$next_schedule || $availability['specific_date'] < $next_date) {
                                        $next_schedule = $availability;
                                        $next_date = $availability['specific_date'];
                                    }
                                }
                            }
                            
                            // If no one-time schedule, find next weekly schedule
                            if (!$next_schedule) {
                                $current_day_index = array_search($today, $day_order);
                                for ($i = 1; $i <= 7; $i++) {
                                    $check_day_index = ($current_day_index + $i) % 7;
                                    $check_day = $day_order[$check_day_index];
                                    
                                    foreach ($all_availabilities as $availability) {
                                        if ($availability['schedule_type'] === 'weekly' && 
                                            $availability['weekdays'] === $check_day) {
                                            $next_schedule = $availability;
                                            $next_date = date('Y-m-d', strtotime("+$i days"));
                                            break 2;
                                        }
                                    }
                                }
                            }
                            ?>
                            
                            <!-- Today's Schedule -->
                            <div class="availability-item" style="background: #f8f9fa; border-left: 4px solid #C5A253; padding: 20px; border-radius: 8px; margin-bottom: 16px;">
                                <div class="availability-header" style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                                    <i class="fas fa-calendar-day" style="color: #C5A253; font-size: 22px;"></i>
                                    <strong style="font-size: 17px; color: #3a3a3a;">Today's Schedule</strong>
                                </div>
                                
                                <?php if ($today_schedule): ?>
                                    <div style="display: grid; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-calendar" style="color: #666; width: 22px; font-size: 18px;"></i>
                                            <span style="font-size: 18px; line-height: 1.3;"><strong style="font-size: 20px;"><?php echo $today; ?></strong>, <?php echo date('F j, Y'); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-clock" style="color: #666; width: 22px; font-size: 16px;"></i>
                                            <span style="font-size: 15px;"><?php echo date('g:i A', strtotime($today_schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($today_schedule['end_time'])); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-users" style="color: #666; width: 22px; font-size: 16px;"></i>
                                            <span style="font-size: 15px;">Max Appointments: <strong><?php echo $today_schedule['max_appointments']; ?></strong></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #666; font-style: italic; padding: 8px 0; font-size: 15px;">
                                        <i class="fas fa-info-circle" style="margin-right: 6px;"></i>
                                        No availability scheduled for today
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Next Consultation -->
                            <div class="availability-item" style="background: #fff; border: 1px solid #e0e0e0; padding: 20px; border-radius: 8px;">
                                <div class="availability-header" style="display: flex; align-items: center; gap: 8px; margin-bottom: 16px;">
                                    <i class="fas fa-calendar-check" style="color: #28a745; font-size: 22px;"></i>
                                    <strong style="font-size: 17px; color: #3a3a3a;">Next Available Day</strong>
                                </div>
                                
                                <?php if ($next_schedule): ?>
                                    <div style="display: grid; gap: 10px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-calendar" style="color: #666; width: 22px; font-size: 18px;"></i>
                                            <span style="font-size: 18px; line-height: 1.3;"><strong style="font-size: 20px;"><?php echo date('l', strtotime($next_date)); ?></strong>, <?php echo date('F j, Y', strtotime($next_date)); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-clock" style="color: #666; width: 22px; font-size: 16px;"></i>
                                            <span style="font-size: 15px;"><?php echo date('g:i A', strtotime($next_schedule['start_time'])); ?> - <?php echo date('g:i A', strtotime($next_schedule['end_time'])); ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <i class="fas fa-users" style="color: #666; width: 22px; font-size: 16px;"></i>
                                            <span style="font-size: 15px;">Max Appointments: <strong><?php echo $next_schedule['max_appointments']; ?></strong></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="color: #666; font-style: italic; padding: 8px 0; font-size: 15px;">
                                        <i class="fas fa-info-circle" style="margin-right: 6px;"></i>
                                        No upcoming availability scheduled
                                    </div>
                                <?php endif; ?>
                            </div>
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
                                                 title="<?php echo htmlspecialchars($description); ?>">
                                                <?php echo htmlspecialchars($description); ?>
                                            </div>
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
                        <div style="display:flex; gap:8px; justify-content:center; align-items:center; margin-top:16px;padding-bottom:32px;">
                            <?php if ($current_page > 1): ?>
                                <a href="?page=<?php echo $current_page - 1; ?>#recent-consultations" class="pagination-btn pagination-prev"><i class="fas fa-chevron-left"></i></a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-prev pagination-disabled"><i class="fas fa-chevron-left"></i></span>
                            <?php endif; ?>

                            <span style="font-size:14px; color:#666; font-weight:500;">
                                <?php echo $current_page; ?>
                            </span>

                            <?php if ($current_page < $total_pages): ?>
                                <a href="?page=<?php echo $current_page + 1; ?>#recent-consultations" class="pagination-btn pagination-next"><i class="fas fa-chevron-right"></i></a>
                            <?php else: ?>
                                <span class="pagination-btn pagination-next pagination-disabled"><i class="fas fa-chevron-right"></i></span>
                            <?php endif; ?>
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


    </script>

    <!-- Include Password Change Modal -->
    <?php include '../includes/password_modal.php'; ?>
</body>
</html>
