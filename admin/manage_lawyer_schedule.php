    <?php
/**
 * Admin - Manage Lawyer Schedule
 * Block/delete blocked schedules on behalf of lawyers
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/upload_config.php';

$message = '';
$error = '';
$lawyer_id = isset($_GET['lawyer_id']) ? (int)$_GET['lawyer_id'] : 0;

// Initialize variables to prevent undefined warnings
$blocked_total = 0;
$blocked_dates = [];
$blocked_total_pages = 0;
$upcoming_consultations = [];
$lawyer = null;
$consult_total = 0;
$consult_per_page = 10;
$consult_page = 1;
$consult_offset = 0;
$consult_total_pages = 0;
$status_filter = '';
$search_query = '';
$schedule_type_filter = isset($_GET['schedule_type']) ? $_GET['schedule_type'] : '';
$schedule_per_page = isset($_GET['schedule_per_page']) ? (int)$_GET['schedule_per_page'] : 10;
$schedule_page = isset($_GET['schedule_page']) ? max(1, (int)$_GET['schedule_page']) : 1;
$schedule_offset = 0;
$schedule_total = 0;
$schedule_total_pages = 0;

// Helper function to build redirect URL with filters
function buildRedirectUrl($lawyer_id, $params = []) {
    $url = "manage_lawyer_schedule.php?lawyer_id=" . $lawyer_id;
    
    // Preserve schedule filters
    if (!empty($_GET['schedule_search'])) {
        $url .= "&schedule_search=" . urlencode($_GET['schedule_search']);
    }
    if (!empty($_GET['schedule_type'])) {
        $url .= "&schedule_type=" . urlencode($_GET['schedule_type']);
    }
    if (!empty($_GET['schedule_page']) && $_GET['schedule_page'] > 1) {
        $url .= "&schedule_page=" . (int)$_GET['schedule_page'];
    }
    
    // Add any additional parameters
    foreach ($params as $key => $value) {
        $url .= "&" . urlencode($key) . "=" . urlencode($value);
    }
    
    return $url;
}

try {
    $pdo = getDBConnection();
    
    // Get lawyer details
    $lawyer_stmt = $pdo->prepare("
        SELECT u.user_id, u.username, u.email, u.phone, lp.lp_fullname, lp.profile
        FROM users u
        LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        WHERE u.user_id = ? AND u.role = 'lawyer'
    ");
    $lawyer_stmt->execute([$lawyer_id]);
    $lawyer = $lawyer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lawyer) {
        throw new Exception('Lawyer not found');
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $redirect_after_post = true; // Flag to redirect after successful POST
            
            switch ($_POST['action']) {
                case 'add_weekly':
                    $weekdays = $_POST['weekdays'] ?? [];
                    $start_time = $_POST['start_time'] ?? '';
                    $end_time = $_POST['end_time'] ?? '';
                    $max_appointments = (int)($_POST['max_appointments'] ?? 5);
                    $time_slot_duration = (int)($_POST['time_slot_duration'] ?? 60);
                    
                    // Validate
                    if (empty($weekdays)) {
                        throw new Exception('Please select at least one day');
                    }
                    
                    if (empty($start_time) || empty($end_time)) {
                        throw new Exception('Please provide start and end times');
                    }
                    
                    if (strtotime($start_time) >= strtotime($end_time)) {
                        throw new Exception('End time must be after start time');
                    }
                    
                    // Insert weekly schedules
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO lawyer_availability 
                        (lawyer_id, schedule_type, weekday, start_time, end_time, max_appointments, time_slot_duration, la_is_active)
                        VALUES (?, 'weekly', ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $added_count = 0;
                    foreach ($weekdays as $weekday) {
                        // Check if this weekday already has a schedule
                        $check_stmt = $pdo->prepare("
                            SELECT la_id FROM lawyer_availability 
                            WHERE lawyer_id = ? AND schedule_type = 'weekly' AND weekday = ?
                        ");
                        $check_stmt->execute([$lawyer_id, $weekday]);
                        
                        if (!$check_stmt->fetch()) {
                            $insert_stmt->execute([$lawyer_id, $weekday, $start_time, $end_time, $max_appointments, $time_slot_duration]);
                            $added_count++;
                        }
                    }
                    
                    if ($added_count === 0) {
                        throw new Exception('All selected days already have schedules');
                    }
                    
                    $message = "Successfully added weekly schedule for $added_count day(s)";
                    $_SESSION['schedule_message'] = $message;
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
                    
                case 'add_onetime':
                    $specific_date = $_POST['specific_date'] ?? '';
                    $start_time = $_POST['start_time_onetime'] ?? '';
                    $end_time = $_POST['end_time_onetime'] ?? '';
                    $max_appointments = (int)($_POST['max_appointments_onetime'] ?? 5);
                    $time_slot_duration = (int)($_POST['time_slot_duration_onetime'] ?? 60);
                    
                    // Validate
                    if (empty($specific_date)) {
                        throw new Exception('Please select a date');
                    }
                    
                    if (strtotime($specific_date) <= strtotime('today')) {
                        throw new Exception('Cannot create schedule for today or past dates');
                    }
                    
                    if (empty($start_time) || empty($end_time)) {
                        throw new Exception('Please provide start and end times');
                    }
                    
                    if (strtotime($start_time) >= strtotime($end_time)) {
                        throw new Exception('End time must be after start time');
                    }
                    
                    // Check if date already has a schedule
                    $check_stmt = $pdo->prepare("
                        SELECT la_id FROM lawyer_availability 
                        WHERE lawyer_id = ? AND specific_date = ? AND schedule_type IN ('one_time', 'blocked')
                    ");
                    $check_stmt->execute([$lawyer_id, $specific_date]);
                    
                    if ($check_stmt->fetch()) {
                        throw new Exception('This date already has a schedule or is blocked');
                    }
                    
                    // Insert one-time schedule
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO lawyer_availability 
                        (lawyer_id, schedule_type, specific_date, start_time, end_time, max_appointments, time_slot_duration, la_is_active)
                        VALUES (?, 'one_time', ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $insert_stmt->execute([$lawyer_id, $specific_date, $start_time, $end_time, $max_appointments, $time_slot_duration]);
                    
                    $message = "Successfully added one-time schedule for " . date('M d, Y', strtotime($specific_date));
                    $_SESSION['schedule_message'] = $message;
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
                    
                case 'block_dates':
                    $block_date = $_POST['block_date'] ?? '';
                    $end_date = $_POST['end_date'] ?? '';
                    $reason = trim($_POST['reason'] ?? '');
                    
                    // Validate reason is selected
                    if (empty($reason)) {
                        throw new Exception('Please select a reason for blocking');
                    }
                    
                    // Validate date
                    if (empty($block_date)) {
                        throw new Exception('Please select a date to block');
                    }
                    
                    if (strtotime($block_date) < strtotime('today')) {
                        throw new Exception('Cannot block past dates');
                    }
                    
                    // Check if end_date is provided (range blocking)
                    $is_range = !empty($end_date);
                    
                    if ($is_range) {
                        // Validate end date
                        if (strtotime($block_date) > strtotime($end_date)) {
                            throw new Exception('Start date must be before end date');
                        }
                        
                        if (strtotime($end_date) < strtotime('today')) {
                            throw new Exception('Cannot block past dates');
                        }
                        
                        // Start transaction for data integrity
                        $pdo->beginTransaction();
                        
                        try {
                            // Block each date in the range
                            $current_date = $block_date;
                            $blocked_count = 0;
                            $skipped_count = 0;
                            $total_cancelled = 0;
                            $notification_count = 0;
                            $all_affected_ids = [];
                            $weekdays_text = $reason . ' (Blocked by Admin)';
                            
                            require_once '../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
                            require_once '../includes/EmailNotification.php';
                            $emailNotification = new EmailNotification($pdo);
                        
                            // Prepare check statement
                            $check_stmt = $pdo->prepare("
                                SELECT la_id FROM lawyer_availability 
                                WHERE lawyer_id = ? AND specific_date = ? AND max_appointments = 0
                            ");
                            
                            // Prepare insert statement
                            $insert_stmt = $pdo->prepare("
                                INSERT INTO lawyer_availability 
                                (lawyer_id, schedule_type, specific_date, start_time, end_time, max_appointments, time_slot_duration, la_is_active, blocked_reason)
                                VALUES (?, 'blocked', ?, '00:00:00', '23:59:59', 0, 60, 1, ?)
                            ");
                            
                            while (strtotime($current_date) <= strtotime($end_date)) {
                                // Check if date is already blocked
                                $check_stmt->execute([$lawyer_id, $current_date]);
                                
                                if (!$check_stmt->fetch()) {
                                    // Date not blocked, insert it
                                    $insert_stmt->execute([$lawyer_id, $current_date, $weekdays_text]);
                                    $blocked_count++;
                                    
                                    // Check for affected appointments on this date
                                    $affected_appointments = $emailNotification->getAffectedAppointments($lawyer_id, $current_date);
                                    
                                    // Queue notifications BEFORE cancelling appointments
                                    if (!empty($affected_appointments)) {
                                        foreach ($affected_appointments as $appointment) {
                                            $queued = $emailNotification->notifyAppointmentCancelled($appointment['id'], $reason);
                                            if ($queued) {
                                                $notification_count++;
                                            }
                                            $all_affected_ids[] = $appointment['id'];
                                        }
                                    }
                                } else {
                                    // Date already blocked, skip it
                                    $skipped_count++;
                                }
                                
                                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                            }
                            
                            // Batch cancel all affected appointments (OPTIMIZED)
                            if (!empty($all_affected_ids)) {
                                $placeholders = str_repeat('?,', count($all_affected_ids) - 1) . '?';
                                $cancel_stmt = $pdo->prepare("
                                    UPDATE consultations 
                                    SET c_status = 'cancelled'
                                    WHERE c_id IN ($placeholders)
                                    AND lawyer_id = ?
                                ");
                                $params = array_merge($all_affected_ids, [$lawyer_id]);
                                $cancel_stmt->execute($params);
                                $total_cancelled = $cancel_stmt->rowCount();
                            }
                            
                            if ($blocked_count === 0 && $skipped_count === 0) {
                                throw new Exception('No dates to block in the selected range');
                            }
                            
                            $message = "Blocked $blocked_count date(s) successfully";
                            if ($skipped_count > 0) {
                                $message .= " ($skipped_count already blocked)";
                            }
                            
                            // Add email notification info
                            if ($notification_count > 0) {
                                $message .= ". $total_cancelled appointment(s) cancelled and $notification_count email notification(s) are being sent...";
                                
                                // Add async email script
                                $async_script = "
                                <script>
                                setTimeout(function() {
                                    fetch('../api/process_emails_async.php', {
                                        method: 'POST',
                                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                                    }).then(response => response.json())
                                    .then(data => {
                                        if (data.sent > 0) {
                                            console.log('Emails sent successfully: ' + data.sent);
                                        }
                                    }).catch(error => {
                                        console.log('Email processing error:', error);
                                    });
                                }, 100);
                                </script>";
                                
                                $_SESSION['async_email_script'] = $async_script;
                            }
                            
                            // Commit the transaction
                            $pdo->commit();
                            
                        } catch (Exception $e) {
                            // Rollback transaction on error
                            $pdo->rollBack();
                            throw $e;
                        }
                    } else {
                        // Single date blocking
                        // Check if date is already blocked
                        $check_stmt = $pdo->prepare("
                            SELECT la_id FROM lawyer_availability 
                            WHERE lawyer_id = ? AND specific_date = ? AND schedule_type = 'blocked'
                        ");
                        $check_stmt->execute([$lawyer_id, $block_date]);
                        
                        if ($check_stmt->fetch()) {
                            throw new Exception('This date is already blocked');
                        }
                        
                        // Insert blocked date with schedule_type = 'blocked'
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO lawyer_availability 
                            (lawyer_id, schedule_type, specific_date, start_time, end_time, max_appointments, la_is_active, blocked_reason)
                            VALUES (?, 'blocked', ?, '00:00:00', '23:59:59', 0, 1, ?)
                        ");
                        
                        $insert_stmt->execute([$lawyer_id, $block_date, $reason]);
                        
                        // Check for affected appointments and send notifications
                        require_once '../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
                        require_once '../includes/EmailNotification.php';
                        $emailNotification = new EmailNotification($pdo);
                        $affected_appointments = $emailNotification->getAffectedAppointments($lawyer_id, $block_date);
                        
                        // Queue notifications BEFORE cancelling appointments
                        $notification_count = 0;
                        foreach ($affected_appointments as $appointment) {
                            $queued = $emailNotification->notifyAppointmentCancelled($appointment['id'], $reason);
                            if ($queued) {
                                $notification_count++;
                            }
                        }
                        
                        // Cancel all affected appointments AFTER queuing notifications (BATCH OPERATION)
                        $cancelled_count = 0;
                        if (!empty($affected_appointments)) {
                            // Batch cancel all appointments in single query (FASTER)
                            $appointment_ids = array_column($affected_appointments, 'id');
                            $placeholders = str_repeat('?,', count($appointment_ids) - 1) . '?';
                            
                            $cancel_stmt = $pdo->prepare("
                                UPDATE consultations 
                                SET c_status = 'cancelled'
                                WHERE c_id IN ($placeholders)
                                AND lawyer_id = ?
                            ");
                            
                            $params = array_merge($appointment_ids, [$lawyer_id]);
                            $result = $cancel_stmt->execute($params);
                            $cancelled_count = $cancel_stmt->rowCount();
                        }
                        
                        // Queue emails for async processing
                        $message = "Date blocked successfully for " . $lawyer['first_name'] . " " . $lawyer['last_name'];
                        if ($notification_count > 0) {
                            $message .= ". $cancelled_count appointment(s) cancelled and $notification_count email notification(s) are being sent...";
                            
                            // Add JavaScript to trigger async email processing
                            $async_script = "
                            <script>
                            setTimeout(function() {
                                fetch('../api/process_emails_async.php', {
                                    method: 'POST',
                                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                                }).then(response => response.json())
                                .then(data => {
                                    if (data.sent > 0) {
                                        console.log('Emails sent successfully: ' + data.sent);
                                    }
                                }).catch(error => {
                                    console.log('Email processing error:', error);
                                });
                            }, 100);
                            </script>";
                            
                            // Store script in session to display on next page load
                            $_SESSION['async_email_script'] = $async_script;
                        }
                    }
                    
                    $_SESSION['schedule_message'] = $message;
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
                    
                case 'delete_blocked_date':
                    $availability_id = (int)($_POST['availability_id'] ?? 0);
                    
                    if ($availability_id <= 0) {
                        throw new Exception('Invalid availability ID');
                    }
                    
                    // Delete the blocked schedule
                    $delete_stmt = $pdo->prepare("
                        DELETE FROM lawyer_availability 
                        WHERE la_id = ? AND lawyer_id = ? AND schedule_type = 'blocked'
                    ");
                    $delete_stmt->execute([$availability_id, $lawyer_id]);
                    
                    if ($delete_stmt->rowCount() === 0) {
                        throw new Exception('Failed to delete blocked date. It may have already been removed.');
                    }
                    
                    $_SESSION['schedule_message'] = "Blocked date deleted successfully";
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
                    
                case 'bulk_delete_blocked':
                    $blocked_ids = $_POST['blocked_ids'] ?? '';
                    
                    if (empty($blocked_ids)) {
                        throw new Exception('No dates selected for deletion');
                    }
                    
                    $ids_array = explode(',', $blocked_ids);
                    $ids_array = array_map('intval', $ids_array);
                    $ids_array = array_filter($ids_array);
                    
                    if (empty($ids_array)) {
                        throw new Exception('Invalid date IDs');
                    }
                    
                    // Delete multiple blocked dates
                    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
                    $delete_stmt = $pdo->prepare("
                        DELETE FROM lawyer_availability 
                        WHERE la_id IN ($placeholders) AND lawyer_id = ? AND schedule_type = 'blocked'
                    ");
                    
                    $params = array_merge($ids_array, [$lawyer_id]);
                    $delete_stmt->execute($params);
                    
                    $deleted_count = $delete_stmt->rowCount();
                    
                    $_SESSION['schedule_message'] = "Successfully deleted $deleted_count blocked date(s)";
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
                    
                case 'delete_schedule':
                    $schedule_id = (int)($_POST['schedule_id'] ?? 0);
                    
                    if ($schedule_id <= 0) {
                        throw new Exception('Invalid schedule ID');
                    }
                    
                    // Delete the schedule
                    $delete_stmt = $pdo->prepare("
                        DELETE FROM lawyer_availability 
                        WHERE la_id = ? AND lawyer_id = ?
                    ");
                    $delete_stmt->execute([$schedule_id, $lawyer_id]);
                    
                    if ($delete_stmt->rowCount() === 0) {
                        throw new Exception('Failed to delete schedule. It may have already been removed.');
                    }
                    
                    $_SESSION['schedule_message'] = "Schedule deleted successfully";
                    header('Location: ' . buildRedirectUrl($lawyer_id));
                    exit;
            }
            
            // Redirect after successful POST to prevent form resubmission
            if (isset($redirect_after_post) && $redirect_after_post) {
                $_SESSION['schedule_message'] = $message;
                header("Location: " . buildRedirectUrl($lawyer_id));
                exit;
            }
        }
    }
    
    // Check for message from redirect
    if (isset($_SESSION['schedule_message'])) {
        $message = $_SESSION['schedule_message'];
        unset($_SESSION['schedule_message']);
    }
    
    // Check for async email script
    $async_email_script = '';
    if (isset($_SESSION['async_email_script'])) {
        $async_email_script = $_SESSION['async_email_script'];
        unset($_SESSION['async_email_script']);
    }
    
    // Pagination for blocked dates with search and filter
    $blocked_per_page = isset($_GET['blocked_per_page']) ? (int)$_GET['blocked_per_page'] : 10;
    $blocked_page = isset($_GET['blocked_page']) ? max(1, (int)$_GET['blocked_page']) : 1;
    $blocked_offset = ($blocked_page - 1) * $blocked_per_page;
    $blocked_search = isset($_GET['blocked_search']) ? trim($_GET['blocked_search']) : '';
    $blocked_reason_filter = isset($_GET['reason_filter']) ? $_GET['reason_filter'] : '';
    
    // Build WHERE clause for blocked dates
    $blocked_where = ["lawyer_id = ?", "schedule_type = 'blocked'", "specific_date >= CURDATE()"];
    $blocked_params = [$lawyer_id];
    
    if (!empty($blocked_search)) {
        $blocked_where[] = "(specific_date LIKE ? OR blocked_reason LIKE ?)";
        $search_param = "%$blocked_search%";
        $blocked_params[] = $search_param;
        $blocked_params[] = $search_param;
    }
    
    if (!empty($blocked_reason_filter)) {
        $blocked_where[] = "blocked_reason LIKE ?";
        $blocked_params[] = "%$blocked_reason_filter%";
    }
    
    $blocked_where_clause = implode(' AND ', $blocked_where);
    
    // Get total count of blocked dates (schedule_type = 'blocked')
    $blocked_count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lawyer_availability
        WHERE $blocked_where_clause
    ");
    $blocked_count_stmt->execute($blocked_params);
    $blocked_total = $blocked_count_stmt->fetchColumn();
    $blocked_total_pages = ceil($blocked_total / $blocked_per_page);
    
    // Get paginated blocked dates for this lawyer
    $blocked_stmt = $pdo->prepare("
        SELECT la_id, specific_date, blocked_reason, created_at
        FROM lawyer_availability
        WHERE $blocked_where_clause
        ORDER BY specific_date ASC
        LIMIT ? OFFSET ?
    ");
    $blocked_params[] = $blocked_per_page;
    $blocked_params[] = $blocked_offset;
    $blocked_stmt->execute($blocked_params);
    $blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lawyer's schedules (blocked, weekly, one-time)
    // Pagination and filtering for schedules
    $schedule_offset = ($schedule_page - 1) * $schedule_per_page;
    $schedule_search = isset($_GET['schedule_search']) ? trim($_GET['schedule_search']) : '';
    
    // Build WHERE clause
    $where_conditions = ["lawyer_id = ?"];
    $params = [$lawyer_id];
    
    if (!empty($schedule_search)) {
        $where_conditions[] = "(weekday LIKE ? OR specific_date LIKE ? OR blocked_reason LIKE ?)";
        $search_param = "%$schedule_search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($schedule_type_filter)) {
        $where_conditions[] = "schedule_type = ?";
        $params[] = $schedule_type_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lawyer_availability
        WHERE $where_clause
    ");
    $count_stmt->execute($params);
    $schedule_total = $count_stmt->fetchColumn();
    $schedule_total_pages = ceil($schedule_total / $schedule_per_page);
    
    // Get paginated schedules
    $schedules_stmt = $pdo->prepare("
        SELECT la_id, schedule_type, weekday, specific_date, start_time, end_time, 
               max_appointments, time_slot_duration, la_is_active, blocked_reason, created_at
        FROM lawyer_availability
        WHERE $where_clause
        ORDER BY 
            CASE 
                WHEN schedule_type = 'blocked' AND specific_date >= CURDATE() THEN 1
                WHEN schedule_type = 'one_time' AND specific_date >= CURDATE() THEN 2
                WHEN schedule_type = 'weekly' THEN 3
                ELSE 4
            END,
            specific_date ASC,
            FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
        LIMIT ? OFFSET ?
    ");
    $params[] = $schedule_per_page;
    $params[] = $schedule_offset;
    $schedules_stmt->execute($params);
    $all_schedules = $schedules_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get weekly availability schedule
    $weekly_schedule_stmt = $pdo->prepare("
        SELECT la_id, weekday, start_time, end_time, max_appointments, time_slot_duration, la_is_active
        FROM lawyer_availability
        WHERE lawyer_id = ? AND schedule_type = 'weekly'
        ORDER BY FIELD(weekday, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $weekly_schedule_stmt->execute([$lawyer_id]);
    $weekly_schedule = $weekly_schedule_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by weekday
    $schedule_by_day = [];
    foreach ($weekly_schedule as $schedule) {
        $schedule_by_day[$schedule['weekday']] = $schedule;
    }
    
    // Get consultations for this lawyer
    $consult_per_page = isset($_GET['consult_per_page']) ? (int)$_GET['consult_per_page'] : 10;
    $consult_page = isset($_GET['consult_page']) ? max(1, (int)$_GET['consult_page']) : 1;
    $consult_offset = ($consult_page - 1) * $consult_per_page;
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Build WHERE clause for consultations
    $consult_where = ["lawyer_id = ?"];
    $consult_params = [$lawyer_id];
    
    if (!empty($search_query)) {
        $consult_where[] = "(c_full_name LIKE ? OR c_email LIKE ? OR c_phone LIKE ?)";
        $search_param = "%$search_query%";
        $consult_params[] = $search_param;
        $consult_params[] = $search_param;
        $consult_params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $consult_where[] = "c_status = ?";
        $consult_params[] = $status_filter;
    }
    
    $consult_where_clause = implode(' AND ', $consult_where);
    
    // Get total count of consultations
    $consult_count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM consultations
        WHERE $consult_where_clause
    ");
    $consult_count_stmt->execute($consult_params);
    $consult_total = $consult_count_stmt->fetchColumn();
    $consult_total_pages = ceil($consult_total / $consult_per_page);
    
    // Get paginated consultations
    $consult_stmt = $pdo->prepare("
        SELECT c_id as id, c_full_name as full_name, c_email as email, c_phone as phone, 
               c_consultation_date as consultation_date, c_consultation_time as consultation_time, c_status as status
        FROM consultations
        WHERE $consult_where_clause
        ORDER BY c_consultation_date DESC, c_consultation_time DESC
        LIMIT ? OFFSET ?
    ");
    $consult_params[] = $consult_per_page;
    $consult_params[] = $consult_offset;
    $consult_stmt->execute($consult_params);
    $upcoming_consultations = $consult_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error in manage_lawyer_schedule.php: " . $e->getMessage());
    
    // Check if this is an AJAX request for update_schedule
    if (isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'A database error occurred. Please try again.'
        ]);
        exit;
    }
    
    $error = 'A database error occurred. Please try again or contact support.';
} catch (Exception $e) {
    error_log("Error in manage_lawyer_schedule.php: " . $e->getMessage());
    
    // Check if this is an AJAX request for update_schedule
    if (isset($_POST['action']) && $_POST['action'] === 'update_schedule') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
    
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule - <?php echo htmlspecialchars($lawyer['first_name'] ?? 'Lawyer'); ?> <?php echo htmlspecialchars($lawyer['last_name'] ?? ''); ?></title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // AJAX Pagination for Blocked Dates
        function loadBlockedDates(page) {
            const lawyerId = <?php echo $lawyer_id; ?>;
            const container = document.getElementById('blocked-dates-container');
            
            // Show loading state
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
            
            // Fetch new page
            fetch(`../api/admin/get_blocked_dates.php?lawyer_id=${lawyerId}&page=${page}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(html => {
                    container.innerHTML = html;
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                    
                    // Re-attach form submission handlers to reload after delete
                    attachFormHandlers();
                    
                    // Restore bulk mode state if active
                    if (bulkModeActive) {
                        const checkboxContainers = document.querySelectorAll('.bulk-checkbox-container');
                        const contentDivs = document.querySelectorAll('.blocked-date-content');
                        checkboxContainers.forEach(container => {
                            container.style.display = 'block';
                        });
                        contentDivs.forEach(div => {
                            div.style.marginLeft = '35px';
                        });
                    }
                    
                    // Smooth scroll to top of blocked dates section
                    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(error => {
                    console.error('Error loading blocked dates:', error);
                    container.innerHTML = '<p style="color: #dc3545; text-align: center; padding: 20px;">Error loading blocked dates. Please refresh the page.</p>';
                    container.style.opacity = '1';
                    container.style.pointerEvents = 'auto';
                });
        }
        
        // Attach form handlers for AJAX-loaded content
        function attachFormHandlers() {
            const forms = document.querySelectorAll('#blocked-dates-container form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    fetch('manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        // Show success notification
                        showNotification('Blocked date deleted successfully', 'success');
                        
                        // Get current page
                        const currentPageEl = document.querySelector('.pagination .current');
                        let currentPage = currentPageEl ? parseInt(currentPageEl.textContent) : 1;
                        
                        // Count remaining items on current page
                        const remainingItems = document.querySelectorAll('.blocked-date-item').length;
                        
                        // If this was the last item on the page and we're not on page 1, go to previous page
                        if (remainingItems === 1 && currentPage > 1) {
                            currentPage = currentPage - 1;
                        }
                        
                        // Reload the appropriate page
                        loadBlockedDates(currentPage);
                    })
                    .catch(error => {
                        console.error('Error deleting blocked date:', error);
                        showNotification('Error deleting blocked date. Please try again.', 'error');
                    });
                });
            });
        }
        
        // Initial attachment on page load
        document.addEventListener('DOMContentLoaded', attachFormHandlers);
        
        // Bulk operations functions
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.blocked-checkbox:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('bulk-actions-bar');
            const countSpan = document.getElementById('selected-count');
            
            if (count > 0) {
                bulkBar.style.display = 'block';
                countSpan.textContent = count;
            } else {
                bulkBar.style.display = 'none';
            }
        }
        
        function selectAllBlocked() {
            document.querySelectorAll('.blocked-checkbox').forEach(cb => cb.checked = true);
            updateBulkActions();
        }
        
        function deselectAllBlocked() {
            document.querySelectorAll('.blocked-checkbox').forEach(cb => cb.checked = false);
            updateBulkActions();
        }
        
        function bulkDeleteBlocked() {
            const checkboxes = document.querySelectorAll('.blocked-checkbox:checked');
            if (checkboxes.length === 0) {
                showNotification('Please select at least one blocked date to delete.', 'error');
                return;
            }
            
            const count = checkboxes.length;
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'bulk_delete_blocked');
            formData.append('blocked_ids', ids.join(','));
            
            // Submit via AJAX
            fetch('manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Show success notification
                showNotification(`Successfully deleted ${count} blocked date(s)`, 'success');
                
                // Get current page
                const currentPageEl = document.querySelector('.pagination .current');
                let currentPage = currentPageEl ? parseInt(currentPageEl.textContent) : 1;
                
                // Count remaining items on current page
                const totalItems = document.querySelectorAll('.blocked-date-item').length;
                
                // If we're deleting all items on the page and we're not on page 1, go to previous page
                if (count >= totalItems && currentPage > 1) {
                    currentPage = currentPage - 1;
                }
                
                // Reload the appropriate page
                loadBlockedDates(currentPage);
            })
            .catch(error => {
                console.error('Error deleting blocked dates:', error);
                showNotification('Error deleting blocked dates. Please try again.', 'error');
            });
        }
        
        // Toggle bulk delete mode
        let bulkModeActive = false;
        function toggleBulkMode() {
            bulkModeActive = !bulkModeActive;
            const checkboxContainers = document.querySelectorAll('.bulk-checkbox-container');
            const contentDivs = document.querySelectorAll('.blocked-date-content');
            const toggleBtn = document.getElementById('toggle-bulk-mode');
            const bulkActionsBar = document.getElementById('bulk-actions-bar');
            
            if (bulkModeActive) {
                // Show checkboxes and shift content
                checkboxContainers.forEach(container => {
                    container.style.display = 'block';
                });
                contentDivs.forEach(div => {
                    div.style.marginLeft = '35px';
                });
                toggleBtn.innerHTML = '<i class="fas fa-times"></i> Cancel';
                toggleBtn.style.background = '#dc3545';
                toggleBtn.style.color = 'white';
            } else {
                // Hide checkboxes and reset content
                checkboxContainers.forEach(container => {
                    container.style.display = 'none';
                });
                contentDivs.forEach(div => {
                    div.style.marginLeft = '0';
                });
                toggleBtn.innerHTML = '<i class="fas fa-check-square"></i> Multiple Delete';
                toggleBtn.style.background = '';
                toggleBtn.style.color = '';
                
                // Deselect all and hide bulk actions bar
                deselectAllBlocked();
                if (bulkActionsBar) {
                    bulkActionsBar.style.display = 'none';
                }
            }
        }
        
        // Show notification function
        function showNotification(message, type) {
            // Remove any existing notifications
            const existingNotification = document.querySelector('.ajax-notification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'error'} ajax-notification`;
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideIn 0.3s ease-out;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
            `;
            
            // Add animation keyframes if not already added
            if (!document.querySelector('#notification-animation')) {
                const style = document.createElement('style');
                style.id = 'notification-animation';
                style.textContent = `
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
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(400px);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }
    </script>
</head>
<body class="admin-page">
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php if ($lawyer): ?>


            <!-- Schedule Table -->
            <div class="action-card" style="max-width: 100%; margin: 0 auto; margin-bottom: 32px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 16px;">
                    <h3 style="margin: 0;">
                        <i class="fas fa-calendar-alt"></i> Schedule
                    </h3>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <!-- Search -->
                        <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                            <input type="hidden" name="lawyer_id" value="<?php echo $lawyer_id; ?>">
                            <?php if (!empty($schedule_type_filter)): ?>
                                <input type="hidden" name="schedule_type" value="<?php echo htmlspecialchars($schedule_type_filter); ?>">
                            <?php endif; ?>
                            
                            <div style="position: relative;">
                                <input type="text" name="schedule_search" placeholder="Search by date..." 
                                       value="<?php echo htmlspecialchars($schedule_search); ?>"
                                       style="padding: 10px 90px 10px 16px; min-width: 280px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px;">
                                <?php if (!empty($schedule_search)): ?>
                                    <a href="?lawyer_id=<?php echo $lawyer_id; ?><?php echo !empty($schedule_type_filter) ? '&schedule_type=' . urlencode($schedule_type_filter) : ''; ?>" 
                                       style="position: absolute; right: 48px; top: 50%; transform: translateY(-50%); padding: 0; width: 32px; height: 32px; border: none; background: transparent; color: #6c757d; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none;">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="submit" style="position: absolute; right: 2px; top: 50%; transform: translateY(-50%); padding: 0 12px; height: 38px; border: none; border-radius: 6px; background: #c5a253; color: white; cursor: pointer;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Type Filter -->
                        <form method="GET" style="display: inline;">
                            <input type="hidden" name="lawyer_id" value="<?php echo $lawyer_id; ?>">
                            <?php if (!empty($schedule_search)): ?>
                                <input type="hidden" name="schedule_search" value="<?php echo htmlspecialchars($schedule_search); ?>">
                            <?php endif; ?>
                            
                            <select name="schedule_type" onchange="this.form.submit()" 
                                    style="padding: 10px 16px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; background: white; cursor: pointer;">
                                <option value="">All Types</option>
                                <option value="weekly" <?php echo $schedule_type_filter === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="one_time" <?php echo $schedule_type_filter === 'one_time' ? 'selected' : ''; ?>>One-Time</option>
                                <option value="blocked" <?php echo $schedule_type_filter === 'blocked' ? 'selected' : ''; ?>>Blocked</option>
                            </select>
                        </form>
                        
                        <!-- Create Schedule Button -->
                        <button type="button" onclick="openScheduleModal()" class="btn btn-primary" style="background: #c5a253; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; white-space: nowrap; width: auto;">
                            <i class="fas fa-plus-circle"></i> Add New Schedule
                        </button>
                    </div>
                </div>
                
                <?php if (empty($all_schedules)): ?>
                    <p style="text-align: center; color: #6c757d; padding: 40px; background: white; border-radius: 8px;">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 16px; opacity: 0.3;"></i>
                        No schedules found for this lawyer.
                    </p>
                <?php else: ?>
                <div class="table-wrapper" style="overflow-x: auto;">
                    <table class="schedule-table" style="width: 100%; border-collapse: collapse; background: white; table-layout: auto;">
                        <thead>
                            <tr style="background: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #000;">Day / Date</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #000;">Status</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #000;">Type</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #000;">Start Time</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #000;">End Time</th>
                                <th style="padding: 12px; text-align: center; font-weight: 600; color: #000; width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_schedules as $schedule): ?>
                                <tr style="border-bottom: 1px solid #e9ecef;">
                                    <!-- Day/Date -->
                                    <td style="padding: 12px;">
                                        <?php 
                                        if ($schedule['schedule_type'] === 'weekly') {
                                            echo htmlspecialchars($schedule['weekday']);
                                        } else {
                                            echo date('D, M d, Y', strtotime($schedule['specific_date']));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Status -->
                                    <td style="padding: 12px;">
                                        <?php 
                                        if ($schedule['schedule_type'] === 'blocked') {
                                            echo 'Unavailable';
                                        } else {
                                            echo $schedule['la_is_active'] ? 'Active' : 'Inactive';
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Type -->
                                    <td style="padding: 12px;">
                                        <?php 
                                        if ($schedule['schedule_type'] === 'blocked') {
                                            echo 'Blocked date';
                                        } else {
                                            echo ucfirst(str_replace('_', '-', $schedule['schedule_type']));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Start Time -->
                                    <td style="padding: 12px;">
                                        <?php 
                                        if ($schedule['schedule_type'] === 'blocked') {
                                            echo '—';
                                        } else {
                                            echo date('g:i A', strtotime($schedule['start_time']));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- End Time -->
                                    <td style="padding: 12px;">
                                        <?php 
                                        if ($schedule['schedule_type'] === 'blocked') {
                                            echo '—';
                                        } else {
                                            echo date('g:i A', strtotime($schedule['end_time']));
                                        }
                                        ?>
                                    </td>
                                    
                                    <!-- Actions -->
                                    <td style="padding: 12px; text-align: center;">
                                        <button type="button" class="lawyer-btn btn-view-details" onclick="openDeleteModal(<?php echo $schedule['la_id']; ?>)"
                                                style="background: var(--gold); color: white; width: 88px; height: 48px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($schedule_total_pages > 1): ?>
                    <div class="pagination" style="display: flex; justify-content: center; align-items: center; gap: 8px; margin-top: 20px;">
                        <?php
                        $base_url = "?lawyer_id=$lawyer_id";
                        if (!empty($schedule_search)) $base_url .= "&schedule_search=" . urlencode($schedule_search);
                        if (!empty($schedule_type_filter)) $base_url .= "&schedule_type=" . urlencode($schedule_type_filter);
                        ?>
                        
                        <!-- Previous Button -->
                        <?php if ($schedule_page > 1): ?>
                            <a href="<?php echo $base_url; ?>&schedule_page=<?php echo $schedule_page - 1; ?>" 
                               style="padding: 8px 12px; background: white; border: 2px solid #e9ecef; border-radius: 6px; text-decoration: none; color: #0b1d3a; font-weight: 600; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span style="padding: 8px 12px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 6px; color: #ccc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <!-- Page Number -->
                        <span style="padding: 0 16px; color: #0b1d3a; font-weight: 600; font-size: 16px;">
                            <?php echo $schedule_page; ?>
                        </span>
                        
                        <!-- Next Button -->
                        <?php if ($schedule_page < $schedule_total_pages): ?>
                            <a href="<?php echo $base_url; ?>&schedule_page=<?php echo $schedule_page + 1; ?>" 
                               style="padding: 8px 12px; background: white; border: 2px solid #e9ecef; border-radius: 6px; text-decoration: none; color: #0b1d3a; font-weight: 600; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span style="padding: 8px 12px; background: #f8f9fa; border: 2px solid #e9ecef; border-radius: 6px; color: #ccc; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
        <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; padding: 32px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); animation: slideIn 0.3s ease-out;">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="width: 64px; height: 64px; background: #fee; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                    <i class="fas fa-exclamation-triangle" style="color: #dc3545; font-size: 32px;"></i>
                </div>
                <h3 style="margin: 0 0 8px 0; color: #0b1d3a; font-size: 24px;">Delete Schedule?</h3>
                <p style="margin: 0; color: #6c757d; font-size: 14px;">This action cannot be undone. The schedule will be permanently removed.</p>
            </div>
            
            <form id="deleteForm" method="POST" style="display: flex; gap: 12px; justify-content: center;">
                <input type="hidden" name="action" value="delete_schedule">
                <input type="hidden" name="schedule_id" id="deleteScheduleId">
                
                <button type="button" onclick="closeDeleteModal()" 
                        style="flex: 1; padding: 12px 24px; background: #f8f9fa; color: #6c757d; border: 2px solid #e9ecef; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    Cancel
                </button>
                <button type="submit" 
                        style="flex: 1; padding: 12px 24px; background: #dc3545; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </form>
        </div>
    </div>

    <style>
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Delete button hover effect */
        .lawyer-btn.btn-view-details:hover {
            background: #c82333 !important;
            transform: translateY(-2px);
            transition: all 0.2s ease;
        }
    </style>

    <script>
        // Sync end date with start date (if elements exist)
        const blockDateEl = document.getElementById('block_date');
        if (blockDateEl) {
            blockDateEl.addEventListener('change', function() {
                const endDate = document.getElementById('end_date');
                if (endDate) {
                    if (!endDate.value || new Date(endDate.value) < new Date(this.value)) {
                        endDate.value = '';
                    }
                    endDate.min = this.value;
                }
            });
        }
        
        // Delete modal functions
        function openDeleteModal(scheduleId) {
            document.getElementById('deleteScheduleId').value = scheduleId;
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
        
        // Schedule Modal Functions
        function openScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            if (!modal) return;
            modal.style.display = 'block';
            // Default to weekly tab
            switchScheduleTab('weekly');
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            if (!modal) return;
            modal.style.display = 'none';
        }
        
        function switchScheduleTab(tabName) {
            // Hide all form contents
            document.querySelectorAll('.schedule-form-content').forEach(form => {
                form.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.schedule-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected form
            const selectedForm = document.getElementById(tabName + 'Form');
            if (selectedForm) {
                selectedForm.classList.add('active');
            }
            
            // Add active class to selected tab
            const selectedTab = document.querySelector(`.schedule-tab[data-tab="${tabName}"]`);
            if (selectedTab) {
                selectedTab.classList.add('active');
            }
        }
        
        // Sync end date with block date
        document.addEventListener('DOMContentLoaded', function() {
            const blockDateInput = document.querySelector('input[name="block_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            
            if (blockDateInput && endDateInput) {
                blockDateInput.addEventListener('change', function() {
                    // Set minimum date for end date to match block date
                    endDateInput.min = this.value;
                    
                    // If end date is before block date, clear it
                    if (endDateInput.value && endDateInput.value < this.value) {
                        endDateInput.value = '';
                    }
                });
            }
        });
        
        // Toast Notification Function
        function showToast(title, message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const iconMap = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                info: 'fa-info-circle'
            };
            
            toast.innerHTML = `
                <div class="toast-icon">
                    <i class="fas ${iconMap[type] || iconMap.success}"></i>
                </div>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <span class="toast-close" onclick="this.parentElement.remove()">×</span>
            `;
            
            container.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }, 5000);
        }
        
        // Show toast if there's a message from PHP
        <?php if ($message): ?>
            showToast('Success', <?php echo json_encode($message); ?>, 'success');
        <?php endif; ?>
        
        <?php if ($error): ?>
            showToast('Error', <?php echo json_encode($error); ?>, 'error');
        <?php endif; ?>
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('scheduleModal');
            if (event.target === modal) {
                closeScheduleModal();
            }
        });
    </script>
    
    <!-- Create Schedule Modal -->
    <div id="scheduleModal" class="consultation-modal" style="display:none;">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 style=color:white;">Add New Schedule</h2>
                <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Tab Navigation -->
                <div class="schedule-tabs" style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e0e0e0; padding-bottom: 0;">
                    <button type="button" class="schedule-tab active" data-tab="weekly" onclick="switchScheduleTab('weekly')">
                        Weekly
                    </button>
                    <button type="button" class="schedule-tab" data-tab="onetime" onclick="switchScheduleTab('onetime')">
                        One Time
                    </button>
                    <button type="button" class="schedule-tab" data-tab="blockdate" onclick="switchScheduleTab('blockdate')">
                        Block Date(s)
                    </button>
                </div>

                <!-- Weekly Schedule Form -->
                <form method="POST" id="weeklyForm" class="schedule-form-content active">
                    <input type="hidden" name="action" value="add_weekly">
                    
                    <div class="form-section">
                        <label class="form-label-modern" style="font-weight: 600; margin-bottom: 15px; display: block;">Available Days</label>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-bottom: 20px;">
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Monday" style="margin-right: 10px;">
                                <span>Monday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Tuesday" style="margin-right: 10px;">
                                <span>Tuesday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Wednesday" style="margin-right: 10px;">
                                <span>Wednesday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Thursday" style="margin-right: 10px;">
                                <span>Thursday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Friday" style="margin-right: 10px;">
                                <span>Friday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Saturday" style="margin-right: 10px;">
                                <span>Saturday</span>
                            </label>
                            <label class="checkbox-label" style="display: flex; align-items: center; padding: 10px; border: 1px solid #ddd; border-radius: 6px; cursor: pointer;">
                                <input type="checkbox" name="weekdays[]" value="Sunday" style="margin-right: 10px;">
                                <span>Sunday</span>
                            </label>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Start Time</label>
                            <input type="time" name="start_time" value="09:00" class="form-input-modern time-input-clean" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">End Time</label>
                            <input type="time" name="end_time" value="17:00" class="form-input-modern time-input-clean" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Max Appointments/Day</label>
                            <select name="max_appointments" class="form-select-modern" required>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i === 5 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Time Slot Duration</label>
                            <select name="time_slot_duration" class="form-select-modern" required>
                                <option value="30">30 minutes</option>
                                <option value="60" selected>1 hour</option>
                                <option value="90">1.5 hours</option>
                                <option value="120">2 hours</option>
                                <option value="180">3 hours</option>
                                <option value="240">4 hours</option>
                                <option value="300">5 hours</option>
                                <option value="360">6 hours</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions-modern" style="margin-top: 20px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary-modern" onclick="closeScheduleModal()">Cancel</button>
                        <button type="submit" class="btn-primary-modern">
                            <i class="fas fa-save"></i> Add Weekly Schedule
                        </button>
                    </div>
                </form>

                <!-- One Time Schedule Form -->
                <form method="POST" id="onetimeForm" class="schedule-form-content">
                    <input type="hidden" name="action" value="add_onetime">
                    
                    <div class="form-section">
                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Specific Date</label>
                            <input type="date" name="specific_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="form-input-modern" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Start Time</label>
                            <input type="time" name="start_time_onetime" value="09:00" class="form-input-modern time-input-clean" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">End Time</label>
                            <input type="time" name="end_time_onetime" value="17:00" class="form-input-modern time-input-clean" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Max Appointments</label>
                            <select name="max_appointments_onetime" class="form-select-modern" required>
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo $i === 5 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Time Slot Duration</label>
                            <select name="time_slot_duration_onetime" class="form-select-modern" required>
                                <option value="30">30 minutes</option>
                                <option value="60" selected>1 hour</option>
                                <option value="90">1.5 hours</option>
                                <option value="120">2 hours</option>
                                <option value="180">3 hours</option>
                                <option value="240">4 hours</option>
                                <option value="300">5 hours</option>
                                <option value="360">6 hours</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions-modern" style="margin-top: 20px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary-modern" onclick="closeScheduleModal()">Cancel</button>
                        <button type="submit" class="btn-primary-modern">
                            <i class="fas fa-save"></i> Add One-Time Schedule
                        </button>
                    </div>
                </form>

                <!-- Block Date(s) Form -->
                <form method="POST" id="blockdateForm" class="schedule-form-content">
                    <input type="hidden" name="action" value="block_dates">
                    
                    <div class="form-section">
                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Date to Block</label>
                            <input type="date" name="block_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="form-input-modern" required>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">End Date (Optional)</label>
                            <input type="date" name="end_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" class="form-input-modern">
                            <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                <i class="fas fa-info-circle" style="color: #c5a253;"></i>
                                Only select when blocking multiple consecutive dates
                            </small>
                        </div>

                        <div class="form-group-modern" style="margin-bottom: 15px;">
                            <label class="form-label-modern">Reason</label>
                            <select name="reason" class="form-select-modern" required>
                                <option value="">Select a reason</option>
                                <option value="Unavailable">Unavailable</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal Leave">Personal Leave</option>
                                <option value="Vacation">Vacation</option>
                                <option value="Holiday">Holiday</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Out of Office">Out of Office</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions-modern" style="margin-top: 20px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary-modern" onclick="closeScheduleModal()">Cancel</button>
                        <button type="submit" class="btn-primary-modern">
                            <i class="fas fa-ban"></i> Block Date(s)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <style>
        /* Schedule Modal Tab Styles */
        .schedule-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 0;
            overflow-x: auto;
        }
        
        .schedule-tab {
            flex: 1;
            padding: 12px 20px;
            background: #f5f5f5;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .schedule-tab:hover {
            background: #e8e8e8;
            color: #333;
        }
        
        .schedule-tab.active {
            background: #fff;
            color: #c5a253;
            border-bottom-color: #c5a253;
            font-weight: 600;
        }
        
        .schedule-form-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        .schedule-form-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-label:hover {
            background: #f9f9f9;
            border-color: #c5a253;
        }
        
        .checkbox-label input[type="checkbox"]:checked + span {
            font-weight: 600;
            color: #c5a253;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }
        
        .form-label-modern {
            display: block;
            font-weight: 600;
            color: #0b1d3a;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-input-modern,
        .form-select-modern {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-input-modern:focus,
        .form-select-modern:focus {
            outline: none;
            border-color: #c5a253;
            box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1);
        }
        
        .btn-primary-modern {
            background: #c5a253;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-modern:hover {
            background: #b08f42;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 162, 83, 0.3);
        }
        
        .btn-secondary-modern {
            background: #f8f9fa;
            color: #6c757d;
            border: 2px solid #e9ecef;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-secondary-modern:hover {
            background: #e9ecef;
            border-color: #dee2e6;
        }
        
        .consultation-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .consultation-modal .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .consultation-modal .modal-header {
            padding: 24px 30px;
            background: #C5A253;
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .consultation-modal .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .consultation-modal .modal-close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1;
        }
        
        .consultation-modal .modal-close:hover {
            color: #c5a253;
            transform: rotate(90deg);
        }
        
        .consultation-modal .modal-body {
            padding: 30px;
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }
        
        /* Toast Notification Styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10001;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            padding: 16px 20px;
            min-width: 300px;
            max-width: 400px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            border-left: 4px solid #28a745;
        }
        
        .toast.success {
            border-left-color: #28a745;
        }
        
        .toast.error {
            border-left-color: #dc3545;
        }
        
        .toast.info {
            border-left-color: #17a2b8;
        }
        
        .toast-icon {
            font-size: 24px;
            flex-shrink: 0;
        }
        
        .toast.success .toast-icon {
            color: #28a745;
        }
        
        .toast.error .toast-icon {
            color: #dc3545;
        }
        
        .toast.info .toast-icon {
            color: #17a2b8;
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            color: #0b1d3a;
            margin: 0 0 4px 0;
            font-size: 14px;
        }
        
        .toast-message {
            color: #6c757d;
            margin: 0;
            font-size: 13px;
        }
        
        .toast-close {
            cursor: pointer;
            color: #6c757d;
            font-size: 20px;
            line-height: 1;
            transition: color 0.2s ease;
            flex-shrink: 0;
        }
        
        .toast-close:hover {
            color: #0b1d3a;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    </style>
    
    <?php 
    // Output async email script if present
    if ($async_email_script) {
        echo $async_email_script;
    }
    ?>
</body>
</html>
