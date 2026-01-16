<?php
/**
 * Admin - Manage Lawyer Schedule
 * Block/unblock schedules on behalf of lawyers
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

try {
    $pdo = getDBConnection();
    
    // Get lawyer details
    $lawyer_stmt = $pdo->prepare("
        SELECT id, username, first_name, last_name, email, phone, profile_picture
        FROM users
        WHERE id = ? AND role = 'lawyer'
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
                case 'block_date':
                    $block_date = $_POST['block_date'] ?? '';
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
                    
                    // Check if date is already blocked (max_appointments = 0 means blocked)
                    $check_stmt = $pdo->prepare("
                        SELECT id FROM lawyer_availability 
                        WHERE user_id = ? AND specific_date = ? AND max_appointments = 0
                    ");
                    $check_stmt->execute([$lawyer_id, $block_date]);
                    
                    if ($check_stmt->fetch()) {
                        throw new Exception('This date is already blocked');
                    }
                    
                    // Insert blocked date (one-time schedule with 0 max appointments)
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO lawyer_availability 
                        (user_id, schedule_type, specific_date, start_time, end_time, max_appointments, is_active, weekdays)
                        VALUES (?, 'one_time', ?, '00:00:00', '23:59:59', 0, 1, ?)
                    ");
                    
                    $weekdays = $reason . ' (Blocked by Admin)';
                    $insert_stmt->execute([$lawyer_id, $block_date, $weekdays]);
                    
                    // Check for affected appointments and send notifications
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
                            SET status = 'cancelled'
                            WHERE id IN ($placeholders)
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
                            fetch('../process_emails_async.php', {
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
                    
                    $_SESSION['schedule_message'] = $message;
                    header('Location: manage_lawyer_schedule.php?lawyer_id=' . $lawyer_id);
                    exit;
                    
                case 'unblock_date':
                    $availability_id = (int)($_POST['availability_id'] ?? 0);
                    
                    if ($availability_id <= 0) {
                        throw new Exception('Invalid availability ID');
                    }
                    
                    // Delete the blocked schedule (max_appointments = 0)
                    $delete_stmt = $pdo->prepare("
                        DELETE FROM lawyer_availability 
                        WHERE id = ? AND user_id = ? AND max_appointments = 0
                    ");
                    $delete_stmt->execute([$availability_id, $lawyer_id]);
                    
                    if ($delete_stmt->rowCount() === 0) {
                        throw new Exception('Failed to unblock date. It may have already been removed.');
                    }
                    
                    $_SESSION['schedule_message'] = "Date unblocked successfully";
                    header('Location: manage_lawyer_schedule.php?lawyer_id=' . $lawyer_id);
                    exit;
                    
                case 'block_multiple':
                    $start_date = $_POST['start_date'] ?? '';
                    $end_date = $_POST['end_date'] ?? '';
                    $reason = trim($_POST['reason'] ?? '');
                    
                    // Validate reason is selected
                    if (empty($reason)) {
                        throw new Exception('Please select a reason for blocking');
                    }
                    
                    if (empty($start_date) || empty($end_date)) {
                        throw new Exception('Please select start and end dates');
                    }
                    
                    if (strtotime($start_date) > strtotime($end_date)) {
                        throw new Exception('Start date must be before end date');
                    }
                    
                    if (strtotime($start_date) < strtotime('today')) {
                        throw new Exception('Cannot block past dates');
                    }
                    
                    // Start transaction for data integrity
                    $pdo->beginTransaction();
                    
                    try {
                        // Block each date in the range
                        $current_date = $start_date;
                        $blocked_count = 0;
                        $skipped_count = 0;
                        $total_cancelled = 0;
                        $notification_count = 0;
                        $all_affected_ids = [];
                        $weekdays_text = $reason . ' (Blocked by Admin)';
                        
                        require_once '../includes/EmailNotification.php';
                        $emailNotification = new EmailNotification($pdo);
                    
                    // Prepare check statement
                    $check_stmt = $pdo->prepare("
                        SELECT id FROM lawyer_availability 
                        WHERE user_id = ? AND specific_date = ? AND max_appointments = 0
                    ");
                    
                    // Prepare insert statement
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO lawyer_availability 
                        (user_id, schedule_type, specific_date, start_time, end_time, max_appointments, is_active, weekdays)
                        VALUES (?, 'one_time', ?, '00:00:00', '23:59:59', 0, 1, ?)
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
                            SET status = 'cancelled'
                            WHERE id IN ($placeholders)
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
                            fetch('../process_emails_async.php', {
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
                    $_SESSION['schedule_message'] = $message;
                    
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                    
                case 'bulk_unblock':
                    $blocked_ids = $_POST['blocked_ids'] ?? '';
                    
                    if (empty($blocked_ids)) {
                        throw new Exception('No dates selected for unblocking');
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
                        WHERE id IN ($placeholders) AND user_id = ? AND max_appointments = 0
                    ");
                    
                    $params = array_merge($ids_array, [$lawyer_id]);
                    $delete_stmt->execute($params);
                    
                    $deleted_count = $delete_stmt->rowCount();
                    
                    $_SESSION['schedule_message'] = "Successfully unblocked $deleted_count date(s)";
                    header('Location: manage_lawyer_schedule.php?lawyer_id=' . $lawyer_id);
                    exit;
            }
            
            // Redirect after successful POST to prevent form resubmission
            if (isset($redirect_after_post) && $redirect_after_post) {
                $_SESSION['schedule_message'] = $message;
                header("Location: manage_lawyer_schedule.php?lawyer_id=" . $lawyer_id);
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
    
    // Pagination for blocked dates
    $blocked_per_page = 5;
    $blocked_page = isset($_GET['blocked_page']) ? max(1, (int)$_GET['blocked_page']) : 1;
    $blocked_offset = ($blocked_page - 1) * $blocked_per_page;
    
    // Get total count of blocked dates (max_appointments = 0 means blocked)
    $blocked_count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lawyer_availability
        WHERE user_id = ? 
        AND max_appointments = 0
        AND specific_date >= CURDATE()
    ");
    $blocked_count_stmt->execute([$lawyer_id]);
    $blocked_total = $blocked_count_stmt->fetchColumn();
    $blocked_total_pages = ceil($blocked_total / $blocked_per_page);
    
    // Get paginated blocked dates for this lawyer
    $blocked_stmt = $pdo->prepare("
        SELECT id, specific_date, start_date, end_date, weekdays as blocked_reason, created_at
        FROM lawyer_availability
        WHERE user_id = ? 
        AND max_appointments = 0
        AND specific_date >= CURDATE()
        ORDER BY specific_date ASC
        LIMIT ? OFFSET ?
    ");
    $blocked_stmt->execute([$lawyer_id, $blocked_per_page, $blocked_offset]);
    $blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get upcoming consultations that might be affected
    $consultations_stmt = $pdo->prepare("
        SELECT id, consultation_date, consultation_time, status,
               full_name, email, phone
        FROM consultations
        WHERE lawyer_id = ? 
        AND consultation_date >= CURDATE()
        AND status IN ('pending', 'confirmed')
        ORDER BY consultation_date ASC, consultation_time ASC
        LIMIT 10
    ");
    $consultations_stmt->execute([$lawyer_id]);
    $upcoming_consultations = $consultations_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Database error in manage_lawyer_schedule.php: " . $e->getMessage());
    $error = 'A database error occurred. Please try again or contact support.';
} catch (Exception $e) {
    error_log("Error in manage_lawyer_schedule.php: " . $e->getMessage());
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
                    
                    // Re-attach form submission handlers to reload after unblock
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
                        showNotification('Date unblocked successfully', 'success');
                        
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
                        console.error('Error unblocking date:', error);
                        showNotification('Error unblocking date. Please try again.', 'error');
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
        
        function bulkUnblock() {
            const checkboxes = document.querySelectorAll('.blocked-checkbox:checked');
            if (checkboxes.length === 0) {
                showNotification('Please select at least one blocked date to unblock.', 'error');
                return;
            }
            
            const count = checkboxes.length;
            const ids = Array.from(checkboxes).map(cb => cb.value);
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'bulk_unblock');
            formData.append('blocked_ids', ids.join(','));
            
            // Submit via AJAX
            fetch('manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                // Show success notification
                showNotification(`Successfully unblocked ${count} date(s)`, 'success');
                
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
                console.error('Error unblocking dates:', error);
                showNotification('Error unblocking dates. Please try again.', 'error');
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
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($lawyer): ?>
            <!-- Lawyer Info -->
            <div class="lawyer-info-card">
                <div>
                    <?php 
                    $profile_picture_url = getProfilePictureUrl($lawyer['profile_picture']);
                    $lawyer_initials = strtoupper(substr($lawyer['first_name'], 0, 1) . substr($lawyer['last_name'], 0, 1));
                    ?>
                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" 
                         alt="<?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?>"
                         style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); box-shadow: 0 4px 8px rgba(0,0,0,0.2);"
                         onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: none; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: bold; border: 3px solid var(--primary-color); box-shadow: 0 4px 8px rgba(0,0,0,0.2);">
                        <?php echo $lawyer_initials; ?>
                    </div>
                </div>
                <div style="flex: 1;">
                    <h2><?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($lawyer['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($lawyer['phone']); ?></p>
                    <p><i class="fas fa-user"></i> Username: <?php echo htmlspecialchars($lawyer['username']); ?></p>
                </div>
            </div>

            <!-- Warning Box -->
            <?php if (!empty($upcoming_consultations)): ?>
                <div class="warning-box">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <p><strong>Important:</strong> This lawyer has <?php echo count($upcoming_consultations); ?> upcoming consultation(s). Blocking dates may affect these appointments. Please review them below before blocking.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Action Cards -->
            <div class="action-cards">
                <!-- Block Single Date -->
                <div class="action-card">
                    <h3><i class="fas fa-ban"></i> Block Single Date</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="block_date">
                        
                        <div class="form-group">
                            <label for="block_date">Select Date:</label>
                            <input type="date" id="block_date" name="block_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason">Reason:</label>
                            <select id="reason" name="reason" required>
                                <option value="">Select reason...</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal Leave">Personal Leave</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Vacation">Vacation</option>
                                <option value="Court Appearance">Court Appearance</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-ban"></i> Block Date
                        </button>
                    </form>
                </div>

                <!-- Block Date Range -->
                <div class="action-card">
                    <h3><i class="fas fa-calendar-times"></i> Block Date Range</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="block_multiple">
                        
                        <div class="form-group">
                            <label for="start_date">Start Date:</label>
                            <input type="date" id="start_date" name="start_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date:</label>
                            <input type="date" id="end_date" name="end_date" 
                                   min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="reason_range">Reason:</label>
                            <select id="reason_range" name="reason" required>
                                <option value="">Select reason...</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal Leave">Personal Leave</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Vacation">Vacation</option>
                                <option value="Court Appearance">Court Appearance</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-calendar-times"></i> Block Range
                        </button>
                    </form>
                </div>
            </div>

            <!-- Blocked Dates List -->
            <div class="blocked-dates-list">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="margin: 0;">
                        <i class="fas fa-list"></i> Currently Blocked Dates
                        <?php if ($blocked_total > 0): ?>
                            <span style="font-size: 0.85rem; color: #6c757d; font-weight: normal;">(<?php echo $blocked_total; ?> total)</span>
                        <?php endif; ?>
                    </h3>
                    <?php if ($blocked_total > 0): ?>
                        <button type="button" id="toggle-bulk-mode" class="btn btn-secondary" onclick="toggleBulkMode()" style="padding: 10px 18px; font-size: 0.9rem;">
                            <i class="fas fa-check-square"></i> Multiple Delete
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="blocked-dates-container">
                <?php if ($blocked_total === 0): ?>
                    <p style="text-align: center; color: #6c757d; padding: 20px;">
                        No blocked dates for this lawyer.
                    </p>
                <?php else: ?>
                    <!-- Bulk Actions Bar -->
                    <div id="bulk-actions-bar" style="display: none; background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #daa520;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <strong style="color: #0b1d3a;">
                                    <span id="selected-count">0</span> date(s) selected
                                </strong>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="btn btn-secondary" onclick="selectAllBlocked()">Select All</button>
                                <button type="button" class="btn btn-secondary" onclick="deselectAllBlocked()">Deselect All</button>
                                <button type="button" class="btn btn-danger" onclick="bulkUnblock()">
                                    <i class="fas fa-trash"></i> Unblock Selected
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <form id="bulk-unblock-form" method="POST">
                        <input type="hidden" name="action" value="bulk_unblock">
                        <input type="hidden" name="blocked_ids" id="blocked-ids-input">
                    </form>
                    
                    <?php foreach ($blocked_dates as $blocked): ?>
                        <div class="blocked-date-item" style="position: relative;">
                            <!-- Checkbox for multi-select -->
                            <div class="bulk-checkbox-container" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); display: none;">
                                <input type="checkbox" class="blocked-checkbox" value="<?php echo $blocked['id']; ?>" 
                                       onchange="updateBulkActions()" 
                                       style="width: 20px; height: 20px; cursor: pointer;">
                            </div>
                            <div class="blocked-date-content" style="display: flex; justify-content: space-between; align-items: center; transition: margin-left 0.3s ease; margin-left: 0;">
                                <div class="date-info">
                                    <?php if (!empty($blocked['start_date']) && !empty($blocked['end_date'])): ?>
                                        <span style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; display: inline-block; margin-bottom: 8px;">
                                            <i class="fas fa-calendar-times"></i> BLOCKED RANGE
                                        </span>
                                        <br>
                                        <strong><?php echo date('M d, Y', strtotime($blocked['start_date'])); ?> - <?php echo date('M d, Y', strtotime($blocked['end_date'])); ?></strong>
                                    <?php else: ?>
                                        <span style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; display: inline-block; margin-bottom: 8px;">
                                            <i class="fas fa-ban"></i> BLOCKED DATE
                                        </span>
                                        <br>
                                        <strong><?php echo date('l, F j, Y', strtotime($blocked['specific_date'])); ?></strong>
                                    <?php endif; ?>
                                    <small style="display: block; margin-top: 4px;">
                                        <?php echo $blocked['blocked_reason'] ? htmlspecialchars($blocked['blocked_reason']) : 'Unavailable'; ?>
                                    </small>
                                    <small style="color: #999;">Blocked on: <?php echo date('M j, Y g:i A', strtotime($blocked['created_at'])); ?></small>
                                </div>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unblock_date">
                                    <input type="hidden" name="availability_id" value="<?php echo $blocked['id']; ?>">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Unblock
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination Controls -->
                    <?php if ($blocked_total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($blocked_page > 1): ?>
                                <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $blocked_page - 1; ?>)" title="Previous">
                                    ← Previous
                                </a>
                            <?php else: ?>
                                <span class="disabled">← Previous</span>
                            <?php endif; ?>
                            
                            <?php
                            $range = 2;
                            for ($i = 1; $i <= $blocked_total_pages; $i++):
                                if ($i == 1 || $i == $blocked_total_pages || abs($i - $blocked_page) <= $range):
                            ?>
                                <?php if ($i == $blocked_page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $i; ?>)"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php
                                elseif (abs($i - $blocked_page) == $range + 1):
                                    echo '<span class="disabled">...</span>';
                                endif;
                            endfor;
                            ?>
                            
                            <?php if ($blocked_page < $blocked_total_pages): ?>
                                <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $blocked_page + 1; ?>)" title="Next">
                                    Next →
                                </a>
                            <?php else: ?>
                                <span class="disabled">Next →</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="pagination-info">
                            Showing <?php echo $blocked_offset + 1; ?>-<?php echo min($blocked_offset + $blocked_per_page, $blocked_total); ?> of <?php echo $blocked_total; ?> blocked dates
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Consultations -->
            <?php if (!empty($upcoming_consultations)): ?>
                <div class="blocked-dates-list">
                    <h3><i class="fas fa-calendar-check"></i> Upcoming Consultations</h3>
                    <p style="color: #6c757d; margin-bottom: 15px;">
                        Review these consultations before blocking dates:
                    </p>
                    <?php foreach ($upcoming_consultations as $consult): ?>
                        <div class="consultation-item">
                            <strong><?php echo date('l, F j, Y', strtotime($consult['consultation_date'])); ?></strong>
                            <?php if (!empty($consult['consultation_time'])): ?>
                                at <?php echo date('g:i A', strtotime($consult['consultation_time'])); ?>
                            <?php endif; ?>
                            <br>
                            <small>
                                Client: <?php echo htmlspecialchars($consult['full_name']); ?> 
                                | <?php echo htmlspecialchars($consult['email']); ?>
                                | Status: <span class="status-badge status-<?php echo $consult['status']; ?>">
                                    <?php echo ucfirst($consult['status']); ?>
                                </span>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </main>

    <script>
        // Sync end date with start date
        document.getElementById('start_date').addEventListener('change', function() {
            const endDate = document.getElementById('end_date');
            if (!endDate.value || new Date(endDate.value) < new Date(this.value)) {
                endDate.value = this.value;
            }
            endDate.min = this.value;
        });
    </script>
    
    <?php 
    // Output async email script if present
    if ($async_email_script) {
        echo $async_email_script;
    }
    ?>
</body>
</html>
