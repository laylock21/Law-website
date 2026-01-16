<?php
/**
 * Lawyer Availability Management - COMPLETE VERSION
 * Copy this content to replace availability.php
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
$success_message = '';
$error_message = '';


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $pdo = getDBConnection();
        
        if ($action === 'add_weekly') {
            $weekdays = $_POST['weekdays'] ?? [];
            $start_time = $_POST['start_time'] ?? '';
            $end_time = $_POST['end_time'] ?? '';
            $max_appointments = (int)($_POST['max_appointments'] ?? 0);
            
            // Validate that at least one weekday is selected
            if (empty($weekdays)) {
                throw new Exception('Please select at least one available day.');
            }
            
            // Validate time inputs
            if (empty($start_time) || empty($end_time)) {
                throw new Exception('Please provide start and end times.');
            }
            
            // Validate time range
            if (strtotime($start_time) >= strtotime($end_time)) {
                throw new Exception('End time must be after start time.');
            }
            
            // Validate max appointments
            if ($max_appointments <= 0) {
                throw new Exception('Maximum appointments must be at least 1.');
            }
            
            // Map numeric weekday values to day names for ENUM
            $weekday_map = [
                '0' => 'Sunday',
                '1' => 'Monday',
                '2' => 'Tuesday',
                '3' => 'Wednesday',
                '4' => 'Thursday',
                '5' => 'Friday',
                '6' => 'Saturday'
            ];
            
            // Insert one row per selected weekday
            $insert_stmt = $pdo->prepare("
                INSERT INTO lawyer_availability (user_id, schedule_type, weekdays, start_time, end_time, max_appointments, is_active) 
                VALUES (?, 'weekly', ?, ?, ?, ?, 1)
            ");
            
            $inserted_count = 0;
            foreach ($weekdays as $weekday) {
                $day_name = $weekday_map[$weekday] ?? null;
                if ($day_name) {
                    $insert_stmt->execute([$lawyer_id, $day_name, $start_time, $end_time, $max_appointments]);
                    $inserted_count++;
                }
            }
            
            $success_message = "Weekly schedule added successfully! ($inserted_count day(s) configured)";
            
        } elseif ($action === 'add_onetime') {
            $specific_date = $_POST['specific_date'] ?? '';
            $start_time = $_POST['start_time_onetime'] ?? '';
            $end_time = $_POST['end_time_onetime'] ?? '';
            $max_appointments = (int)($_POST['max_appointments_onetime'] ?? 0);
            
            // Validate date
            if (empty($specific_date)) {
                throw new Exception('Please select a specific date.');
            }
            
            // Validate time inputs
            if (empty($start_time) || empty($end_time)) {
                throw new Exception('Please provide start and end times.');
            }
            
            // Validate time range
            if (strtotime($start_time) >= strtotime($end_time)) {
                throw new Exception('End time must be after start time.');
            }
            
            // Validate max appointments
            if ($max_appointments <= 0) {
                throw new Exception('Maximum appointments must be at least 1.');
            }
            
            // Check if date is in the future or today
            if (strtotime($specific_date) < strtotime('today')) {
                throw new Exception('Cannot set availability for past dates.');
            }
            
            // Check for duplicate one-time schedule on same date
            $check_stmt = $pdo->prepare("
                SELECT id FROM lawyer_availability 
                WHERE user_id = ? AND specific_date = ? AND schedule_type = 'one_time' AND max_appointments > 0
            ");
            $check_stmt->execute([$lawyer_id, $specific_date]);
            if ($check_stmt->fetch()) {
                throw new Exception('You already have a schedule for this date. Please delete it first or choose a different date.');
            }
            
            // Insert one-time availability
            // Also populate weekdays field with the day name for consistency
            $day_name = date('l', strtotime($specific_date)); // Get day name (Monday, Tuesday, etc.)
            
            $insert_stmt = $pdo->prepare("
                INSERT INTO lawyer_availability (user_id, schedule_type, specific_date, weekdays, start_time, end_time, max_appointments, is_active) 
                VALUES (?, 'one_time', ?, ?, ?, ?, ?, 1)
            ");
            $insert_stmt->execute([$lawyer_id, $specific_date, $day_name, $start_time, $end_time, $max_appointments]);
            
            $success_message = 'One-time schedule added successfully for ' . date('M d, Y', strtotime($specific_date)) . '!';
            
        } elseif ($action === 'delete') {
            $availability_id = (int)$_POST['availability_id'];
            
            // Verify ownership
            $verify_stmt = $pdo->prepare("SELECT id FROM lawyer_availability WHERE id = ? AND user_id = ?");
            $verify_stmt->execute([$availability_id, $lawyer_id]);
            
            if (!$verify_stmt->fetch()) {
                throw new Exception('Invalid availability record.');
            }
            
            // Soft delete (set is_active = 0)
            $delete_stmt = $pdo->prepare("UPDATE lawyer_availability SET is_active = 0 WHERE id = ? AND user_id = ?");
            $delete_stmt->execute([$availability_id, $lawyer_id]);
            
            if ($delete_stmt->rowCount() === 0) {
                throw new Exception('Failed to deactivate schedule. It may have already been removed.');
            }
            
            $success_message = 'Schedule deactivated successfully!';
            
        } elseif ($action === 'activate') {
            $availability_id = (int)$_POST['availability_id'];
            
            // Verify ownership
            $verify_stmt = $pdo->prepare("SELECT id FROM lawyer_availability WHERE id = ? AND user_id = ?");
            $verify_stmt->execute([$availability_id, $lawyer_id]);
            
            if (!$verify_stmt->fetch()) {
                throw new Exception('Invalid availability record.');
            }
            
            // Activate schedule
            $activate_stmt = $pdo->prepare("UPDATE lawyer_availability SET is_active = 1 WHERE id = ? AND user_id = ?");
            $activate_stmt->execute([$availability_id, $lawyer_id]);
            
            if ($activate_stmt->rowCount() === 0) {
                throw new Exception('Failed to activate schedule. It may have already been activated.');
            }
            
            $success_message = 'Schedule activated successfully!';
            
        } elseif ($action === 'permanent_delete') {
            $availability_id = (int)$_POST['availability_id'];
            
            // Verify ownership
            $verify_stmt = $pdo->prepare("SELECT id FROM lawyer_availability WHERE id = ? AND user_id = ?");
            $verify_stmt->execute([$availability_id, $lawyer_id]);
            
            if (!$verify_stmt->fetch()) {
                throw new Exception('Invalid availability record.');
            }
            
            // Permanently delete from database
            $delete_stmt = $pdo->prepare("DELETE FROM lawyer_availability WHERE id = ? AND user_id = ?");
            $delete_stmt->execute([$availability_id, $lawyer_id]);
            
            if ($delete_stmt->rowCount() === 0) {
                throw new Exception('Failed to delete schedule. It may have already been removed.');
            }
            
            $success_message = 'Schedule permanently deleted!';
            
        } elseif ($action === 'bulk_unblock') {
            $blocked_ids = $_POST['blocked_ids'] ?? '';
            
            if (empty($blocked_ids)) {
                throw new Exception('No dates selected for unblocking.');
            }
            
            $ids = explode(',', $blocked_ids);
            $ids = array_map('intval', $ids); // Sanitize IDs
            $ids = array_filter($ids); // Remove empty/zero values
            
            if (empty($ids)) {
                throw new Exception('No valid dates selected for unblocking.');
            }
            
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Verify all IDs belong to this lawyer
            $verify_stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM lawyer_availability 
                WHERE id IN ($placeholders) AND user_id = ?
            ");
            $verify_stmt->execute(array_merge($ids, [$lawyer_id]));
            $verified_count = (int)$verify_stmt->fetch()['count'];
            
            if ($verified_count !== count($ids)) {
                throw new Exception('Invalid selection. Some dates do not belong to you.');
            }
            
            // Delete (unblock) selected dates
            $delete_stmt = $pdo->prepare("
                DELETE FROM lawyer_availability 
                WHERE id IN ($placeholders) AND user_id = ?
            ");
            $delete_stmt->execute(array_merge($ids, [$lawyer_id]));
            
            $unblocked_count = $delete_stmt->rowCount();
            
            if ($unblocked_count === 0) {
                throw new Exception('Failed to unblock dates. They may have already been removed.');
            }
            
            $success_message = "Successfully unblocked $unblocked_count date(s)!";
            
        } elseif ($action === 'block_date') {
            $block_date = $_POST['block_date'] ?? '';
            $reason = $_POST['reason'] ?? 'Unavailable';
            
            // Validate date
            if (empty($block_date)) {
                throw new Exception('Please select a date to block.');
            }
            
            // Check if date is not in the past
            if (strtotime($block_date) < strtotime('today')) {
                throw new Exception('Cannot block past dates.');
            }
            
            // Check if already blocked
            $check_stmt = $pdo->prepare("
                SELECT id FROM lawyer_availability 
                WHERE user_id = ? 
                AND schedule_type = 'blocked' 
                AND specific_date = ?
            ");
            $check_stmt->execute([$lawyer_id, $block_date]);
            
            if ($check_stmt->fetch()) {
                throw new Exception('This date is already blocked.');
            }
            
            // Check for affected appointments
            require_once '../includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            $affected_appointments = $emailNotification->getAffectedAppointments($lawyer_id, $block_date);
            
            // Insert blocked date
            $insert_stmt = $pdo->prepare("
                INSERT INTO lawyer_availability (user_id, schedule_type, specific_date, blocked_reason) 
                VALUES (?, 'blocked', ?, ?)
            ");
            $insert_stmt->execute([$lawyer_id, $block_date, $reason]);
            
            // Queue notifications BEFORE cancelling appointments
            $notification_count = 0;
            if (!empty($affected_appointments)) {
                error_log("Blocking date: Found " . count($affected_appointments) . " appointments to cancel");
                
                foreach ($affected_appointments as $appointment) {
                    error_log("Queuing notification for appointment ID: " . $appointment['id']);
                    $queued = $emailNotification->notifyAppointmentCancelled($appointment['id'], $reason);
                    if ($queued) {
                        $notification_count++;
                        error_log("Notification queued successfully for appointment ID: " . $appointment['id']);
                    } else {
                        error_log("Failed to queue notification for appointment ID: " . $appointment['id']);
                    }
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
                
                error_log("Batch cancelled $cancelled_count appointments for lawyer ID: $lawyer_id");
            } else {
                error_log("Blocking date: No appointments found to cancel");
            }
            
            // Queue emails and process them
            if ($notification_count > 0) {
                $success_message = "Date blocked successfully! $notification_count email notification(s) are being sent...";
                
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
                }, 100); // Small delay to ensure page loads first
                </script>";
                
                // Store script in session to display on next page load
                $_SESSION['async_email_script'] = $async_script;
            } else {
                $success_message = "Date blocked successfully! No appointments were affected.";
            }
            
        } elseif ($action === 'block_multiple') {
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $reason = trim($_POST['reason'] ?? 'Unavailable');
            
            // Validate dates
            if (empty($start_date) || empty($end_date)) {
                throw new Exception('Please select start and end dates');
            }
            
            if (strtotime($start_date) > strtotime($end_date)) {
                throw new Exception('Start date must be before end date');
            }
            
            if (strtotime($start_date) < strtotime('today')) {
                throw new Exception('Cannot block past dates');
            }
            
            // Check for overlapping blocked ranges
            $check_stmt = $pdo->prepare("
                SELECT id FROM lawyer_availability 
                WHERE user_id = ? 
                AND schedule_type = 'blocked'
                AND start_date IS NOT NULL
                AND end_date IS NOT NULL
                AND (
                    (start_date <= ? AND end_date >= ?)
                    OR (start_date <= ? AND end_date >= ?)
                    OR (start_date >= ? AND end_date <= ?)
                )
            ");
            $check_stmt->execute([
                $lawyer_id, 
                $start_date, $start_date,  // Check if new start overlaps
                $end_date, $end_date,      // Check if new end overlaps
                $start_date, $end_date     // Check if new range contains existing
            ]);
            
            if ($check_stmt->fetch()) {
                throw new Exception('This date range overlaps with an existing blocked period.');
            }
            
            // Check for affected appointments in the date range
            require_once '../includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            
            $notification_count = 0;
            $all_affected_ids = [];
            
            // Get all affected appointments in the date range
            $current_date = $start_date;
            while (strtotime($current_date) <= strtotime($end_date)) {
                $affected_appointments = $emailNotification->getAffectedAppointments($lawyer_id, $current_date);
                
                if (!empty($affected_appointments)) {
                    foreach ($affected_appointments as $appointment) {
                        if (!in_array($appointment['id'], $all_affected_ids)) {
                            // Queue notification
                            $queued = $emailNotification->notifyAppointmentCancelled($appointment['id'], $reason);
                            if ($queued) {
                                $notification_count++;
                            }
                            $all_affected_ids[] = $appointment['id'];
                        }
                    }
                }
                
                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
            }
            
            // Insert single blocked range
            $insert_stmt = $pdo->prepare("
                INSERT INTO lawyer_availability 
                (user_id, schedule_type, start_date, end_date, blocked_reason)
                VALUES (?, 'blocked', ?, ?, ?)
            ");
            $insert_stmt->execute([$lawyer_id, $start_date, $end_date, $reason]);
            
            // Batch cancel all affected appointments
            $total_cancelled = 0;
            if (!empty($all_affected_ids)) {
                $placeholders = str_repeat('?,', count($all_affected_ids) - 1) . '?';
                $cancel_stmt = $pdo->prepare("
                    UPDATE consultations 
                    SET status = 'cancelled'
                    WHERE id IN ($placeholders)
                    AND lawyer_id = ?
                    AND status IN ('pending', 'confirmed')
                ");
                $params = array_merge($all_affected_ids, [$lawyer_id]);
                $cancel_stmt->execute($params);
                $total_cancelled = $cancel_stmt->rowCount();
            }
            
            // Calculate number of days blocked
            $days_blocked = ceil((strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24)) + 1;
            
            // Build success message
            $success_message = "Blocked date range successfully ($days_blocked day(s) from " . date('M d', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . ")";
            
            // Queue emails for async processing
            if ($notification_count > 0) {
                $success_message .= ". ⚠️ {$total_cancelled} appointment(s) cancelled and $notification_count email notification(s) are being sent...";
                
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
            
        } elseif ($action === 'update_date_preferences') {
            $default_weeks = (int)($_POST['default_booking_weeks'] ?? 52);
            $max_weeks = (int)($_POST['max_booking_weeks'] ?? 104);
            $enabled = isset($_POST['booking_window_enabled']) ? 1 : 0;
            
            // Validation
            if ($default_weeks < 1 || $default_weeks > 208) { // 1 week to 4 years
                throw new Exception('Default booking weeks must be between 1 and 208 weeks (4 years).');
            }
            
            if ($max_weeks < $default_weeks || $max_weeks > 208) {
                throw new Exception('Maximum booking weeks must be at least equal to default weeks and not exceed 208 weeks (4 years).');
            }
            
            // Update lawyer's date preferences
            $update_stmt = $pdo->prepare("
                UPDATE users 
                SET default_booking_weeks = ?, max_booking_weeks = ?, booking_window_enabled = ?
                WHERE id = ? AND role = 'lawyer'
            ");
            $update_stmt->execute([$default_weeks, $max_weeks, $enabled, $lawyer_id]);
            
            if ($update_stmt->rowCount() === 0) {
                throw new Exception('Failed to update date preferences. Please try again.');
            }
            
            $success_message = "Date range preferences updated successfully! Default: {$default_weeks} weeks, Maximum: {$max_weeks} weeks.";
        }
        
        // Redirect after successful POST to prevent form resubmission
        if (!empty($success_message)) {
            $_SESSION['availability_success'] = $success_message;
            header('Location: availability.php');
            exit;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        // Store error in session and redirect
        $_SESSION['availability_error'] = $error_message;
        header('Location: availability.php');
        exit;
    }
}

// Check for messages from redirect
if (isset($_SESSION['availability_success'])) {
    $success_message = $_SESSION['availability_success'];
    unset($_SESSION['availability_success']);
}

// Check for async email script
$async_email_script = '';
if (isset($_SESSION['async_email_script'])) {
    $async_email_script = $_SESSION['async_email_script'];
    unset($_SESSION['async_email_script']);
}

if (isset($_SESSION['availability_error'])) {
    $error_message = $_SESSION['availability_error'];
    unset($_SESSION['availability_error']);
}

// Get lawyer's ALL schedules (weekly and one-time) AND date preferences
// Auto-hide past one-time dates
try {
    $pdo = getDBConnection();
    
    // Get lawyer's date preferences
    $preferences_stmt = $pdo->prepare("
        SELECT default_booking_weeks, max_booking_weeks, booking_window_enabled 
        FROM users 
        WHERE id = ? AND role = 'lawyer'
    ");
    $preferences_stmt->execute([$lawyer_id]);
    $lawyer_preferences = $preferences_stmt->fetch();
    
    // Set defaults if not found
    $default_booking_weeks = (int)($lawyer_preferences['default_booking_weeks'] ?? 52);
    $max_booking_weeks = (int)($lawyer_preferences['max_booking_weeks'] ?? 104);
    $booking_window_enabled = (bool)($lawyer_preferences['booking_window_enabled'] ?? true);
    
    $availability_stmt = $pdo->prepare("
        SELECT * FROM lawyer_availability 
        WHERE user_id = ?
        AND is_active = 1
        AND (
            schedule_type = 'weekly' 
            OR (schedule_type = 'one_time' AND specific_date >= CURDATE())
            OR (schedule_type = 'blocked' AND specific_date >= CURDATE())
            OR (schedule_type = 'blocked' AND start_date IS NOT NULL AND end_date >= CURDATE())
        )
        ORDER BY schedule_type, COALESCE(specific_date, start_date)
    ");
    $availability_stmt->execute([$lawyer_id]);
    $all_schedules = $availability_stmt->fetchAll();
    
    // Separate by type
    $weekly_schedules = [];
    $onetime_schedules = [];
    $blocked_dates = [];
    
    foreach ($all_schedules as $schedule) {
        if ($schedule['schedule_type'] === 'weekly') {
            $weekly_schedules[] = $schedule;
        } elseif ($schedule['schedule_type'] === 'blocked') {
            // Blocked date or date range
            // For single blocked dates, check specific_date
            // For blocked ranges, check start_date/end_date
            $is_future = false;
            
            if (!empty($schedule['specific_date'])) {
                // Single blocked date
                $is_future = strtotime($schedule['specific_date']) >= strtotime('today');
            } elseif (!empty($schedule['start_date']) && !empty($schedule['end_date'])) {
                // Blocked range - show if end_date is today or future
                $is_future = strtotime($schedule['end_date']) >= strtotime('today');
            }
            
            if ($is_future) {
                $blocked_dates[] = $schedule;
            }
        } else {
            $onetime_schedules[] = $schedule;
        }
    }
    
    // Pagination for blocked dates
    $blocked_per_page = 4;
    $blocked_page = isset($_GET['blocked_page']) ? max(1, (int)$_GET['blocked_page']) : 1;
    $blocked_total = count($blocked_dates);
    $blocked_total_pages = ceil($blocked_total / $blocked_per_page);
    $blocked_offset = ($blocked_page - 1) * $blocked_per_page;
    $blocked_dates_paginated = array_slice($blocked_dates, $blocked_offset, $blocked_per_page);
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Manage Availability";
$active_page = "availability";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Availability - <?php echo htmlspecialchars($lawyer_name); ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function switchTab(tab) {
            console.log('Switching to tab:', tab); // Debug log
            
            // Hide ALL tab contents first (both old and new classes)
            document.querySelectorAll('.tab-content-modern, .tab-content').forEach(t => {
                t.classList.remove('active');
                t.style.display = 'none'; // Force hide
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button-modern').forEach(b => {
                b.classList.remove('active');
            });
            
            // Show selected tab
            const targetTab = document.getElementById(tab + '-tab');
            const targetButton = document.querySelector(`[data-tab="${tab}"]`);
            
            console.log('Target tab:', targetTab); // Debug log
            console.log('Target button:', targetButton); // Debug log
            
            if (targetTab && targetButton) {
                targetTab.classList.add('active');
                targetTab.style.display = 'block'; // Force show
                targetButton.classList.add('active');
                
                // Scroll the button into view
                scrollTabIntoView(targetButton);
            }
            
            // Update navigation button states
            updateTabNavigation();
        }
        
        function navigateTabs(direction) {
            console.log('Navigating:', direction); // Debug log
            
            const tabs = ['weekly', 'onetime', 'block', 'blockrange', 'daterange']; // Added daterange
            const activeTab = document.querySelector('.tab-button-modern.active');
            
            console.log('Active tab:', activeTab); // Debug log
            
            if (!activeTab) {
                console.log('No active tab found, defaulting to first tab');
                switchTab('weekly');
                return;
            }
            
            const currentIndex = tabs.indexOf(activeTab.getAttribute('data-tab'));
            console.log('Current index:', currentIndex); // Debug log
            
            let newIndex;
            if (direction === 'next') {
                newIndex = (currentIndex + 1) % tabs.length;
            } else {
                newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
            }
            
            const newTab = tabs[newIndex];
            console.log('New tab:', newTab); // Debug log
            
            switchTab(newTab);
        }
        
        function scrollTabIntoView(button) {
            const tabsContainer = document.querySelector('.schedule-tabs-modern');
            if (!tabsContainer || !button) return;
            
            const containerRect = tabsContainer.getBoundingClientRect();
            const buttonRect = button.getBoundingClientRect();
            
            // Check if button is fully visible
            const isVisible = buttonRect.left >= containerRect.left && 
                             buttonRect.right <= containerRect.right;
            
            if (!isVisible) {
                // Calculate scroll position to center the button
                const scrollLeft = button.offsetLeft - (tabsContainer.offsetWidth / 2) + (button.offsetWidth / 2);
                
                // Smooth scroll to position
                tabsContainer.scrollTo({
                    left: scrollLeft,
                    behavior: 'smooth'
                });
            }
        }
        
        function updateTabNavigation() {
            const tabs = ['weekly', 'onetime', 'block', 'blockrange', 'daterange']; // Added daterange
            const activeTab = document.querySelector('.tab-button-modern.active');
            
            if (!activeTab) return;
            
            const currentIndex = tabs.indexOf(activeTab.getAttribute('data-tab'));
            
            const prevBtn = document.querySelector('.tab-nav-prev');
            const nextBtn = document.querySelector('.tab-nav-next');
            
            if (prevBtn && nextBtn) {
                // Always enable both buttons for circular navigation
                prevBtn.style.opacity = '1';
                nextBtn.style.opacity = '1';
                prevBtn.style.pointerEvents = 'auto';
                nextBtn.style.pointerEvents = 'auto';
                
                // Add visual indication of current position
                const prevTab = tabs[(currentIndex - 1 + tabs.length) % tabs.length];
                const nextTab = tabs[(currentIndex + 1) % tabs.length];
                
                prevBtn.title = `Previous: ${prevTab}`;
                nextBtn.title = `Next: ${nextTab}`;
            }
        }
        
        // Initialize navigation on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing navigation');
            
            // Force hide all tabs first (both old and new classes)
            document.querySelectorAll('.tab-content-modern, .tab-content').forEach(t => {
                t.classList.remove('active');
                t.style.display = 'none';
            });
            
            // Remove active from all buttons
            document.querySelectorAll('.tab-button-modern').forEach(b => {
                b.classList.remove('active');
            });
            
            // Activate first tab
            switchTab('weekly');
            
            updateTabNavigation();
        });
        
        // Sync end date with start date for block range
        document.addEventListener('DOMContentLoaded', function() {
            const startDateBlock = document.getElementById('start_date_block');
            const endDateBlock = document.getElementById('end_date_block');
            
            if (startDateBlock && endDateBlock) {
                startDateBlock.addEventListener('change', function() {
                    if (!endDateBlock.value || new Date(endDateBlock.value) < new Date(this.value)) {
                        endDateBlock.value = this.value;
                    }
                    endDateBlock.min = this.value;
                });
            }
        });
        
        // Bulk unblock functions
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
                alert('Please select at least one blocked date to unblock.');
                return;
            }
            
            const count = checkboxes.length;
            if (!confirm(`Are you sure you want to unblock ${count} date(s)?`)) {
                return;
            }
            
            const ids = Array.from(checkboxes).map(cb => cb.value);
            document.getElementById('blocked-ids-input').value = ids.join(',');
            document.getElementById('bulk-unblock-form').submit();
        }
        
        // AJAX Pagination for Blocked Dates
        function loadBlockedDates(page) {
            const container = document.getElementById('blocked-dates-container');
            
            // Show loading state
            container.style.opacity = '0.5';
            container.style.pointerEvents = 'none';
            
            // Fetch new page
            fetch(`get_blocked_dates_lawyer.php?page=${page}`)
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
                    
                    // Re-attach form submission handlers
                    attachBlockedFormHandlers();
                    
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
        
        // Attach form handlers for AJAX-loaded blocked dates
        function attachBlockedFormHandlers() {
            const forms = document.querySelectorAll('#blocked-dates-container form[action*="availability.php"]');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    if (!confirm('Unblock this date?')) {
                        return;
                    }
                    
                    const formData = new FormData(this);
                    
                    fetch('availability.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(() => {
                        // Reload the current page of blocked dates
                        const currentPage = document.querySelector('.pagination .current');
                        const page = currentPage ? parseInt(currentPage.textContent) : 1;
                        loadBlockedDates(page);
                    })
                    .catch(error => {
                        console.error('Error unblocking date:', error);
                        alert('Error unblocking date. Please try again.');
                    });
                });
            });
        }
        
        // Initial attachment on page load
        document.addEventListener('DOMContentLoaded', attachBlockedFormHandlers);
        
        
        
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
        // Helper for new schedule modal status handling
        function onScheduleStatusChange(value) {
            const actionInput = document.getElementById('new_schedule_action');
            const dateInput = document.getElementById('schedule_date');
            const blockDateInput = document.getElementById('schedule_block_date');
            const startInput = document.getElementById('schedule_start');
            const endInput = document.getElementById('schedule_end');
            const maxInput = document.getElementById('schedule_max');
            const reasonGroup = document.getElementById('schedule_reason_group');

            if (!actionInput) return;

            if (value === 'unavailable') {
                actionInput.value = 'block_date';
                if (dateInput && blockDateInput) {
                    blockDateInput.value = dateInput.value;
                }
                if (startInput) startInput.disabled = true;
                if (endInput) endInput.disabled = true;
                if (maxInput) maxInput.disabled = true;
                if (reasonGroup) reasonGroup.style.display = 'block';
            } else {
                actionInput.value = 'add_onetime';
                if (blockDateInput) blockDateInput.value = '';
                if (startInput) startInput.disabled = false;
                if (endInput) endInput.disabled = false;
                if (maxInput) maxInput.disabled = false;
                if (reasonGroup) reasonGroup.style.display = 'none';

                if (value === 'today' && dateInput) {
                    const today = new Date().toISOString().slice(0, 10);
                    dateInput.value = today;
                }
            }
        }

        function openScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            if (!modal) return;
            modal.style.display = 'block';
            const statusSelect = document.getElementById('schedule_status');
            if (statusSelect) {
                onScheduleStatusChange(statusSelect.value);
            }
        }

        function closeScheduleModal() {
            const modal = document.getElementById('scheduleModal');
            if (!modal) return;
            modal.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const statusSelect = document.getElementById('schedule_status');
            if (statusSelect) {
                onScheduleStatusChange(statusSelect.value);
                statusSelect.addEventListener('change', function () {
                    onScheduleStatusChange(this.value);
                });
            }
        });
    </script>
</head>
<body class="lawyer-page">
    <?php include 'partials/sidebar.php'; ?>
        
    <main class="page-content">
        <?php if ($success_message): ?>
            <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Top summary stats bar -->
        <div class="schedule-stats-bar" style="margin-bottom: 20px;">
            <div class="stat-item">
                <i class="fas fa-calendar-week"></i>
                <div class="stat-content">
                    <span class="stat-number"><?php echo count($weekly_schedules); ?></span>
                    <span class="stat-label">Weekly Schedules</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-calendar-day"></i>
                <div class="stat-content">
                    <span class="stat-number"><?php echo count($onetime_schedules); ?></span>
                    <span class="stat-label">One-Time Schedules</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-ban"></i>
                <div class="stat-content">
                    <span class="stat-number"><?php echo $blocked_total; ?></span>
                    <span class="stat-label">Blocked Dates</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-calendar-times"></i>
                <div class="stat-content">
                    <span class="stat-number"><?php echo $blocked_total; ?></span>
                    <span class="stat-label">Unavailable</span>
                </div>
            </div>
            <div class="stat-item">
                <i class="fas fa-users"></i>
                <div class="stat-content">
                    <span class="stat-number">
                        <?php
                        $weekly_capacity = 0;
                        foreach ($weekly_schedules as $s) {
                            if ($s['is_active']) {
                                $weekly_capacity += (int)$s['max_appointments'];
                            }
                        }
                        $onetime_capacity = 0;
                        foreach ($onetime_schedules as $s) {
                            if ($s['is_active'] && strtotime($s['specific_date']) >= strtotime('today')) {
                                $onetime_capacity += (int)$s['max_appointments'];
                            }
                        }
                        echo $weekly_capacity + $onetime_capacity;
                        ?>
                    </span>
                    <span class="stat-label">Total Potential Slots</span>
                </div>
            </div>
        </div>
        </div>

        <div class="lawyer-availability-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3>Schedule</h3>
                <div style="display:flex; gap: 10px; align-items: center;">
                    <select id="status-filter" class="status-filter-dropdown" onchange="filterSchedules()">
                        <option value="all">All Status</option>
                        <option value="weekly">Weekly</option>
                        <option value="onetime">One Time</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                    <button type="button" class="lawyer-btn btn-create-custom" onclick="openScheduleModal()">
                        <i class="fas fa-plus-circle"></i> Create
                    </button>
                </div>
            </div>

            <script>
            function filterSchedules() {
                const filter = document.getElementById('status-filter').value;
                
                // Filter Modern Sections
                const weeklySection = document.getElementById('weekly-schedules-section');
                const onetimeSection = document.getElementById('onetime-schedules-section');
                const blockedSection = document.getElementById('blocked-dates-section');
                
                if (weeklySection) weeklySection.style.display = (filter === 'all' || filter === 'weekly') ? 'block' : 'none';
                if (onetimeSection) onetimeSection.style.display = (filter === 'all' || filter === 'onetime') ? 'block' : 'none';
                if (blockedSection) blockedSection.style.display = (filter === 'all' || filter === 'unavailable') ? 'block' : 'none';
                
                // Filter Table Rows
                const rows = document.querySelectorAll('.admin-consultations-table tbody tr');
                rows.forEach(row => {
                    const typeCell = row.cells[1]; // 2nd column is Type
                    if (typeCell) {
                        const typeText = typeCell.textContent.trim().toLowerCase();
                        let show = false;
                        
                        if (filter === 'all') {
                            show = true;
                        } else if (filter === 'weekly' && typeText.includes('weekly')) {
                            show = true;
                        } else if (filter === 'onetime' && typeText.includes('one-time')) {
                            show = true;
                        } else if (filter === 'unavailable' && (typeText.includes('blocked') || typeText.includes('unavailable'))) {
                            show = true;
                        }
                        
                        row.style.display = show ? '' : 'none';
                    }
                });
            }
            </script>

            <div style="overflow-x: auto;">
                <table class="admin-consultations-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding: 12px;">Day / Date</th>
                            <th style="text-align:left; padding: 12px;">Status</th>
                            <th style="text-align:left; padding: 12px;">Type</th>
                            <th style="text-align:left; padding: 12px;">Start Time</th>
                            <th style="text-align:left; padding: 12px;">End Time</th>
                            <th style="text-align:left; padding: 12px;">Max Appt</th>
                            <th style="text-align:center; padding: 12px; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($weekly_schedules as $s): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo htmlspecialchars($s['weekdays']); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    Active
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    Weekly
                                </td>

                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo date('g:i A', strtotime($s['start_time'])); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo date('g:i A', strtotime($s['end_time'])); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo (int)$s['max_appointments']; ?> / day
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef; text-align: center;">
                                    <form method="POST" style="display:inline; width: 88px;">
                                        <input type="hidden" name="availability_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="permanent_delete">
                                        <button type="submit" class="lawyer-btn btn-delete-custom" onclick="return confirm('Permanently delete this schedule?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($onetime_schedules as $s): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo date('D, M d, Y', strtotime($s['specific_date'])); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo strtotime($s['specific_date']) < strtotime('today') ? 'Past' : 'One time available'; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    One-time
                                </td>
                                
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo date('g:i A', strtotime($s['start_time'])); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo date('g:i A', strtotime($s['end_time'])); ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo (int)$s['max_appointments']; ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef; text-align: center;">
                                    <form method="POST" style="display:inline; width: 88px;">
                                        <input type="hidden" name="availability_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="permanent_delete">
                                        <button type="submit" class="lawyer-btn btn-delete-custom" onclick="return confirm('Permanently delete this schedule?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php foreach ($blocked_dates_paginated as $s): ?>
                            <tr>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php
                                    if (!empty($s['specific_date'])) {
                                        echo date('D, M d, Y', strtotime($s['specific_date']));
                                    } elseif (!empty($s['start_date']) && !empty($s['end_date'])) {
                                        echo date('M d, Y', strtotime($s['start_date'])) . ' - ' . date('M d, Y', strtotime($s['end_date']));
                                    }
                                    ?>
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef; color:#dc3545;">
                                    Unavailable
                                </td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
                                    <?php echo (!empty($s['start_date']) && !empty($s['end_date'])) ? 'Blocked range' : 'Blocked date'; ?>
                                </td>
                                
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">—</td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">—</td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef;">—</td>
                                <td style="padding: 12px; border-bottom: 1px solid #e9ecef; text-align: center;">
                                    <form method="POST" style="display:inline; width: 88px;">
                                        <input type="hidden" name="availability_id" value="<?php echo $s['id']; ?>">
                                        <input type="hidden" name="action" value="permanent_delete">
                                        <button type="submit" class="lawyer-btn btn-unblock-custom" onclick="return confirm('Unblock this date/range?')">Unblock</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($blocked_total_pages > 1): ?>
                <div style="display:flex; gap:8px; justify-content:center; margin-top:16px;">
                    <?php if ($blocked_page > 1): ?>
                        <a href="?blocked_page=<?php echo $blocked_page - 1; ?>" class="lawyer-btn">Prev</a>
                    <?php else: ?>
                        <span class="lawyer-btn" style="opacity:0.5; pointer-events:none;">Prev</span>
                    <?php endif; ?>

                    <?php if ($blocked_page < $blocked_total_pages): ?>
                        <a href="?blocked_page=<?php echo $blocked_page + 1; ?>" class="lawyer-btn">Next</a>
                    <?php else: ?>
                        <span class="lawyer-btn" style="opacity:0.5; pointer-events:none;">Next</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="legacy-availability-cards" style="display:none;">
                <!-- Add Schedule Form -->
                <div class="card modern-card">
                    <div class="card-header-modern">
                        <h3 class="card-title-modern">
                            <i class="fas fa-plus-circle"></i>
                            Add New Schedule
                        </h3>
                        <p class="card-subtitle">Create weekly schedules, one-time appointments, or block unavailable dates</p>
                    </div>
                    
                    
                    <div class="schedule-tabs-container">
                        <button class="tab-nav-btn tab-nav-prev" onclick="navigateTabs('prev')" title="Previous Tab">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        
                        <div class="schedule-tabs-modern" id="schedule-tabs">
                            <button class="tab-button-modern" onclick="switchTab('weekly')" data-tab="weekly">
                                <i class="fas fa-calendar-week"></i>
                                <span>Weekly</span>
                            </button>
                            <button class="tab-button-modern" onclick="switchTab('onetime')" data-tab="onetime">
                                <i class="fas fa-calendar-day"></i>
                                <span>One Time</span>
                            </button>
                            <button class="tab-button-modern" onclick="switchTab('block')" data-tab="block">
                                <i class="fas fa-ban"></i>
                                <span>Block Date</span>
                            </button>
                            <button class="tab-button-modern" onclick="switchTab('blockrange')" data-tab="blockrange">
                                <i class="fas fa-calendar-times"></i>
                                <span>Block Range</span>
                            </button>
                            <button class="tab-button-modern" onclick="switchTab('daterange')" data-tab="daterange">
                                <i class="fas fa-cog"></i>
                                <span>Date Settings</span>
                            </button>
                        </div>
                        
                        <button class="tab-nav-btn tab-nav-next" onclick="navigateTabs('next')" title="Next Tab">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    
                    <!-- Weekly Form -->
                    <div id="weekly-tab" class="tab-content-modern">
                        <form method="POST" class="modern-form">
                            <input type="hidden" name="action" value="add_weekly">
                            
                            <div class="form-section">
                                <div class="form-group-modern">
                                    <label class="form-label-modern">
                                        <i class="fas fa-calendar-check"></i>
                                        Available Days
                                    </label>
                                    <div class="weekday-grid">
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="1">
                                            <span class="weekday-label">Mon</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="2">
                                            <span class="weekday-label">Tue</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="3">
                                            <span class="weekday-label">Wed</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="4">
                                            <span class="weekday-label">Thu</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="5">
                                            <span class="weekday-label">Fri</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="6">
                                            <span class="weekday-label">Sat</span>
                                        </label>
                                        <label class="weekday-item">
                                            <input type="checkbox" name="weekdays[]" value="0">
                                            <span class="weekday-label">Sun</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group-modern">
                                        <label for="start_time" class="form-label-modern">
                                            <i class="fas fa-clock"></i>
                                            Start Time
                                        </label>
                                        <input type="time" id="start_time" name="start_time" value="09:00" class="form-input-modern" required>
                                    </div>
                                    
                                    <div class="form-group-modern">
                                        <label for="end_time" class="form-label-modern">
                                            <i class="fas fa-clock"></i>
                                            End Time
                                        </label>
                                        <input type="time" id="end_time" name="end_time" value="17:00" class="form-input-modern" required>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label for="max_appointments" class="form-label-modern">
                                        <i class="fas fa-users"></i>
                                        Max Appointments/Day
                                    </label>
                                    <select id="max_appointments" name="max_appointments" class="form-select-modern" required>
                                        <option value="1">1 appointment</option>
                                        <option value="2">2 appointments</option>
                                        <option value="3">3 appointments</option>
                                        <option value="4">4 appointments</option>
                                        <option value="5" selected>5 appointments</option>
                                        <option value="6">6 appointments</option>
                                        <option value="7">7 appointments</option>
                                        <option value="8">8 appointments</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions-modern">
                                <button type="submit" class="btn-primary-modern">
                                    <i class="fas fa-plus"></i>
                                    Add Weekly Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- One-Time Form -->
                    <div id="onetime-tab" class="tab-content-modern">
                        <form method="POST" class="modern-form">
                            <input type="hidden" name="action" value="add_onetime">
                            
                            <div class="form-section">
                                <div class="form-group-modern">
                                    <label for="specific_date" class="form-label-modern">
                                        <i class="fas fa-calendar-day"></i>
                                        Specific Date
                                    </label>
                                    <input type="date" id="specific_date" name="specific_date" 
                                           min="<?php echo date('Y-m-d'); ?>" class="form-input-modern" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group-modern">
                                        <label for="start_time_onetime" class="form-label-modern">
                                            <i class="fas fa-clock"></i>
                                            Start Time
                                        </label>
                                        <input type="time" id="start_time_onetime" name="start_time_onetime" value="09:00" class="form-input-modern" required>
                                    </div>
                                    
                                    <div class="form-group-modern">
                                        <label for="end_time_onetime" class="form-label-modern">
                                            <i class="fas fa-clock"></i>
                                            End Time
                                        </label>
                                        <input type="time" id="end_time_onetime" name="end_time_onetime" value="17:00" class="form-input-modern" required>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label for="max_appointments_onetime" class="form-label-modern">
                                        <i class="fas fa-users"></i>
                                        Max Appointments
                                    </label>
                                    <select id="max_appointments_onetime" name="max_appointments_onetime" class="form-select-modern" required>
                                        <option value="1">1 appointment</option>
                                        <option value="2">2 appointments</option>
                                        <option value="3">3 appointments</option>
                                        <option value="4">4 appointments</option>
                                        <option value="5" selected>5 appointments</option>
                                        <option value="6">6 appointments</option>
                                        <option value="7">7 appointments</option>
                                        <option value="8">8 appointments</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-actions-modern">
                                <button type="submit" class="btn-primary-modern">
                                    <i class="fas fa-plus"></i>
                                    Add One-Time Schedule
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Block Date Form -->
                    <div id="block-tab" class="tab-content-modern">
                        <form method="POST" class="modern-form">
                            <input type="hidden" name="action" value="block_date">
                            
                            <div class="form-section">
                                <div class="form-group-modern">
                                    <label for="block_date" class="form-label-modern">
                                        <i class="fas fa-ban"></i>
                                        Date to Block
                                    </label>
                                    <input type="date" id="block_date" name="block_date" 
                                           min="<?php echo date('Y-m-d'); ?>" class="form-input-modern" required>
                                    <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                        <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                        Block a specific date (e.g., sick leave, emergency, holiday)
                                    </small>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label for="reason" class="form-label-modern">
                                        <i class="fas fa-comment"></i>
                                        Reason (Optional)
                                    </label>
                                    <select id="reason" name="reason" class="form-select-modern">
                                        <option value="Unavailable">Unavailable</option>
                                        <option value="Sick Leave">Sick Leave</option>
                                        <option value="Personal Leave">Personal Leave</option>
                                        <option value="Holiday">Holiday</option>
                                        <option value="Emergency">Emergency</option>
                                        <option value="Out of Office">Out of Office</option>
                                    </select>
                                    <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                        <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                        This is for your reference only
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-actions-modern">
                                <button type="submit" class="btn-primary-modern" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); box-shadow: 0 4px 16px rgba(220, 53, 69, 0.3);">
                                    <i class="fas fa-ban"></i>
                                    Block This Date
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Block Date Range Form -->
                    <div id="blockrange-tab" class="tab-content-modern">
                        <form method="POST" class="modern-form">
                            <input type="hidden" name="action" value="block_multiple">
                            
                            <div class="form-section">
                                <div class="form-row">
                                    <div class="form-group-modern">
                                        <label for="start_date_block" class="form-label-modern">
                                            <i class="fas fa-calendar-alt"></i>
                                            Start Date
                                        </label>
                                        <input type="date" id="start_date_block" name="start_date" 
                                               min="<?php echo date('Y-m-d'); ?>" class="form-input-modern" required>
                                        <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                            <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                            First date to block
                                        </small>
                                    </div>
                                    
                                    <div class="form-group-modern">
                                        <label for="end_date_block" class="form-label-modern">
                                            <i class="fas fa-calendar-alt"></i>
                                            End Date
                                        </label>
                                        <input type="date" id="end_date_block" name="end_date" 
                                               min="<?php echo date('Y-m-d'); ?>" class="form-input-modern" required>
                                        <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                            <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                            Last date to block (inclusive)
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label for="reason_range" class="form-label-modern">
                                        <i class="fas fa-comment"></i>
                                        Reason (Optional)
                                    </label>
                                    <select id="reason_range" name="reason" class="form-select-modern">
                                        <option value="Unavailable">Unavailable</option>
                                        <option value="Sick Leave">Sick Leave</option>
                                        <option value="Personal Leave">Personal Leave</option>
                                        <option value="Vacation">Vacation</option>
                                        <option value="Holiday">Holiday</option>
                                        <option value="Emergency">Emergency</option>
                                        <option value="Out of Office">Out of Office</option>
                                    </select>
                                    <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                        <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                        This is for your reference only
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-actions-modern">
                                <button type="submit" class="btn-primary-modern" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); box-shadow: 0 4px 16px rgba(220, 53, 69, 0.3);">
                                    <i class="fas fa-calendar-times"></i>
                                    Block Date Range
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Date Range Settings Tab -->
                    <div id="daterange-tab" class="tab-content-modern">
                        <form method="POST" class="modern-form">
                            <input type="hidden" name="action" value="update_date_preferences">
                            
                            <div class="form-section">
                                <div class="settings-grid">
                                    <div class="form-group-modern">
                                        <label class="form-label-modern">
                                            <i class="fas fa-calendar-week"></i>
                                            Default Booking Window
                                        </label>
                                        <select id="default_booking_weeks" name="default_booking_weeks" class="form-select-modern" required>
                                            <option value="4" <?php echo $default_booking_weeks == 4 ? 'selected' : ''; ?>>4 weeks (1 month)</option>
                                            <option value="8" <?php echo $default_booking_weeks == 8 ? 'selected' : ''; ?>>8 weeks (2 months)</option>
                                            <option value="12" <?php echo $default_booking_weeks == 12 ? 'selected' : ''; ?>>12 weeks (3 months)</option>
                                            <option value="26" <?php echo $default_booking_weeks == 26 ? 'selected' : ''; ?>>26 weeks (6 months)</option>
                                            <option value="52" <?php echo $default_booking_weeks == 52 ? 'selected' : ''; ?>>52 weeks (1 year)</option>
                                            <option value="78" <?php echo $default_booking_weeks == 78 ? 'selected' : ''; ?>>78 weeks (1.5 years)</option>
                                            <option value="104" <?php echo $default_booking_weeks == 104 ? 'selected' : ''; ?>>104 weeks (2 years)</option>
                                        </select>
                                        <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                            <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                            Default time range shown to clients
                                        </small>
                                    </div>
                                    
                                    <div class="form-group-modern">
                                        <label class="form-label-modern">
                                            <i class="fas fa-calendar-alt"></i>
                                            Maximum Booking Window
                                        </label>
                                        <select id="max_booking_weeks" name="max_booking_weeks" class="form-select-modern" required>
                                            <option value="26" <?php echo $max_booking_weeks == 26 ? 'selected' : ''; ?>>26 weeks (6 months)</option>
                                            <option value="52" <?php echo $max_booking_weeks == 52 ? 'selected' : ''; ?>>52 weeks (1 year)</option>
                                            <option value="78" <?php echo $max_booking_weeks == 78 ? 'selected' : ''; ?>>78 weeks (1.5 years)</option>
                                            <option value="104" <?php echo $max_booking_weeks == 104 ? 'selected' : ''; ?>>104 weeks (2 years)</option>
                                            <option value="156" <?php echo $max_booking_weeks == 156 ? 'selected' : ''; ?>>156 weeks (3 years)</option>
                                            <option value="208" <?php echo $max_booking_weeks == 208 ? 'selected' : ''; ?>>208 weeks (4 years)</option>
                                        </select>
                                        <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                            <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                            Maximum time clients can book in advance
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="form-group-modern">
                                    <label class="checkbox-item">
                                        <input type="checkbox" name="booking_window_enabled" value="1" <?php echo $booking_window_enabled ? 'checked' : ''; ?>>
                                        Enable individual date range settings for this lawyer
                                    </label>
                                    <small style="color: #6c757d; display: block; margin-top: 8px; font-size: 0.85rem;">
                                        <i class="fas fa-info-circle" style="color: var(--gold);"></i>
                                        When disabled, system defaults will be used
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-actions-modern">
                                <button type="submit" class="btn-primary-modern">
                                    <i class="fas fa-save"></i>
                                    Save Date Range Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Weekly Schedules -->
                <div id="weekly-schedules-section" class="card modern-card weekly-schedules-card">
                    <div class="card-header-modern">
                        <h3 class="card-title-modern">
                            <i class="fas fa-calendar-week"></i>
                            Weekly Schedules
                        </h3>
                        <p class="card-subtitle">Manage your recurring weekly availability</p>
                    </div>
                    
                    <!-- Quick Stats Bar -->
                    <div class="schedule-stats-bar">
                        <div class="stat-item">
                            <i class="fas fa-calendar-check"></i>
                            <span class="stat-number"><?php echo count($weekly_schedules); ?></span>
                            <span class="stat-label">Active Schedules</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-clock"></i>
                            <span class="stat-number">
                                <?php 
                                $total_hours = 0;
                                foreach ($weekly_schedules as $schedule) {
                                    if ($schedule['is_active']) {
                                        $start = strtotime($schedule['start_time']);
                                        $end = strtotime($schedule['end_time']);
                                        $hours = ($end - $start) / 3600;
                                        // Each schedule is for one day only
                                        $total_hours += $hours;
                                    }
                                }
                                echo $total_hours;
                                ?>
                            </span>
                            <span class="stat-label">Hours/Week</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span class="stat-number">
                                <?php 
                                $total_appointments = 0;
                                foreach ($weekly_schedules as $schedule) {
                                    if ($schedule['is_active']) {
                                        // Each schedule is for one day only
                                        $total_appointments += $schedule['max_appointments'];
                                    }
                                }
                                echo $total_appointments;
                                ?>
                            </span>
                            <span class="stat-label">Max Clients/Week</span>
                        </div>
                    </div>
                    
                    <!-- Weekly Schedules -->
                    <div class="schedule-section enhanced">
                        <?php if (empty($weekly_schedules)): ?>
                            <div class="empty-state enhanced">
                                <div class="empty-icon">
                                    <i class="fas fa-calendar-week"></i>
                                </div>
                                <p class="empty-message">No weekly schedules set</p>
                                <p class="empty-hint">Create a weekly schedule to set your regular availability</p>
                            </div>
                        <?php else: ?>
                            <div class="schedules-grid enhanced">
                                <?php foreach ($weekly_schedules as $schedule): ?>
                                    <div class="schedule-card weekly-schedule enhanced">
                                        <div class="schedule-header">
                                            <div class="schedule-badges">
                                                <span class="schedule-badge badge-weekly" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); font-weight: 600;">
                                                    <i class="fas fa-calendar-week"></i>
                                                    WEEKLY SCHEDULE
                                                </span>
                                                <span class="schedule-badge <?php echo $schedule['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                    <i class="fas <?php echo $schedule['is_active'] ? 'fa-check-circle' : 'fa-pause-circle'; ?>"></i>
                                                    <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </div>
                                            <div class="schedule-actions">
                                                <?php if (!$schedule['is_active']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="submit" class="btn-action btn-activate" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                                                    <button type="submit" class="btn-action btn-delete" 
                                                            onclick="return confirm('⚠️ PERMANENTLY DELETE this schedule?\n\nThis cannot be undone!')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="schedule-details enhanced">
                                            <div class="detail-item">
                                                <i class="fas fa-calendar-day"></i>
                                                <span class="detail-label">Day:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    // weekdays is an ENUM storing a single day name
                                                    echo htmlspecialchars($schedule['weekdays']);
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-clock"></i>
                                                <span class="detail-label">Time:</span>
                                                <span class="detail-value">
                                                    <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-users"></i>
                                                <span class="detail-label">Max Appointments:</span>
                                                <span class="detail-value"><?php echo $schedule['max_appointments']; ?>/day</span>
                                            </div>
                                            <!-- Enhanced Details -->
                                            <div class="detail-item enhanced-detail">
                                                <i class="fas fa-hourglass-half"></i>
                                                <span class="detail-label">Duration:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    $start = strtotime($schedule['start_time']);
                                                    $end = strtotime($schedule['end_time']);
                                                    $hours = ($end - $start) / 3600;
                                                    echo $hours . ' hours/day';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="detail-item enhanced-detail">
                                                <i class="fas fa-chart-line"></i>
                                                <span class="detail-label">Daily Capacity:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    echo $schedule['max_appointments'] . ' clients';
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- One-Time Schedules -->
                <div id="onetime-schedules-section" class="card modern-card">
                    <div class="card-header-modern">
                        <h3 class="card-title-modern">
                            <i class="fas fa-calendar-day"></i>
                            One-Time Schedules
                        </h3>
                        <p class="card-subtitle">Manage your specific date appointments</p>
                    </div>
                    
                    <!-- Quick Stats Bar -->
                    <div class="schedule-stats-bar">
                        <div class="stat-item">
                            <i class="fas fa-calendar-plus"></i>
                            <span class="stat-number"><?php echo count($onetime_schedules); ?></span>
                            <span class="stat-label">Scheduled Dates</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar-check"></i>
                            <span class="stat-number">
                                <?php 
                                $upcoming = 0;
                                foreach ($onetime_schedules as $schedule) {
                                    if (strtotime($schedule['specific_date']) >= strtotime('today')) {
                                        $upcoming++;
                                    }
                                }
                                echo $upcoming;
                                ?>
                            </span>
                            <span class="stat-label">Upcoming</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-users"></i>
                            <span class="stat-number">
                                <?php 
                                $total_capacity = 0;
                                foreach ($onetime_schedules as $schedule) {
                                    if ($schedule['is_active'] && strtotime($schedule['specific_date']) >= strtotime('today')) {
                                        $total_capacity += $schedule['max_appointments'];
                                    }
                                }
                                echo $total_capacity;
                                ?>
                            </span>
                            <span class="stat-label">Available Slots</span>
                        </div>
                    </div>
                    
                    <!-- One-Time Schedules -->
                    <div class="schedule-section enhanced">
                        <?php if (empty($onetime_schedules)): ?>
                            <div class="empty-state enhanced">
                                <div class="empty-icon">
                                    <i class="fas fa-calendar-day"></i>
                                </div>
                                <p class="empty-message">No one-time schedules set</p>
                                <p class="empty-hint">Create one-time schedules for specific dates</p>
                            </div>
                        <?php else: ?>
                            <div class="schedules-grid enhanced">
                                <?php foreach ($onetime_schedules as $schedule): ?>
                                    <div class="schedule-card onetime-schedule enhanced">
                                        <div class="schedule-header">
                                            <div class="schedule-badges">
                                                <span class="schedule-badge badge-onetime" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); font-weight: 600;">
                                                    <i class="fas fa-calendar-day"></i>
                                                    ONE TIME AVAILABLE
                                                </span>
                                                <span class="schedule-badge <?php echo $schedule['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                    <i class="fas <?php echo $schedule['is_active'] ? 'fa-check-circle' : 'fa-pause-circle'; ?>"></i>
                                                    <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                                <?php if (strtotime($schedule['specific_date']) >= strtotime('today')): ?>
                                                    <span class="schedule-badge badge-upcoming">
                                                        <i class="fas fa-arrow-up"></i>
                                                        Upcoming
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="schedule-actions">
                                                <?php if (!$schedule['is_active']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="activate">
                                                        <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                                                        <button type="submit" class="btn-action btn-activate" title="Activate">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                                                    <button type="submit" class="btn-action btn-delete" 
                                                            onclick="return confirm('⚠️ PERMANENTLY DELETE this schedule?\n\nThis cannot be undone!')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="schedule-details enhanced">
                                            <div class="detail-item">
                                                <i class="fas fa-calendar-day"></i>
                                                <span class="detail-label">Day:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    // Show the weekday name
                                                    echo $schedule['weekdays'] ? htmlspecialchars($schedule['weekdays']) : date('l', strtotime($schedule['specific_date']));
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <span class="detail-label">Date:</span>
                                                <span class="detail-value"><?php echo date('M d, Y', strtotime($schedule['specific_date'])); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-clock"></i>
                                                <span class="detail-label">Time:</span>
                                                <span class="detail-value">
                                                    <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-users"></i>
                                                <span class="detail-label">Max Appointments:</span>
                                                <span class="detail-value"><?php echo $schedule['max_appointments']; ?></span>
                                            </div>
                                            <!-- Enhanced Details -->
                                            <div class="detail-item enhanced-detail">
                                                <i class="fas fa-hourglass-half"></i>
                                                <span class="detail-label">Duration:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    $start = strtotime($schedule['start_time']);
                                                    $end = strtotime($schedule['end_time']);
                                                    $hours = ($end - $start) / 3600;
                                                    echo $hours . ' hours';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="detail-item enhanced-detail">
                                                <i class="fas fa-calendar-alt"></i>
                                                <span class="detail-label">Days Until:</span>
                                                <span class="detail-value">
                                                    <?php 
                                                    $days_until = ceil((strtotime($schedule['specific_date']) - strtotime('today')) / (60 * 60 * 24));
                                                    if ($days_until < 0) {
                                                        echo 'Past';
                                                    } elseif ($days_until == 0) {
                                                        echo 'Today';
                                                    } elseif ($days_until == 1) {
                                                        echo 'Tomorrow';
                                                    } else {
                                                        echo $days_until . ' days';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Blocked Dates -->
                <div id="blocked-dates-section" class="card modern-card">
                    <div class="card-header-modern">
                        <h3 class="card-title-modern">
                            <i class="fas fa-ban"></i>
                            Blocked Dates
                            <?php if ($blocked_total > 0): ?>
                                <span style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.8); font-weight: normal; margin-left: 8px;">(<?php echo $blocked_total; ?> total)</span>
                            <?php endif; ?>
                        </h3>
                        <p class="card-subtitle">Manage your unavailable dates and blocked periods</p>
                        <?php if (!empty($blocked_dates)): ?>
                            <div style="margin-top: 15px;">
                                <button type="button" id="toggle-bulk-mode" class="btn-secondary-modern" onclick="toggleBulkMode()">
                                    <i class="fas fa-check-square"></i>
                                    Multiple Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Quick Stats Bar -->
                    <div class="schedule-stats-bar">
                        <div class="stat-item">
                            <i class="fas fa-ban"></i>
                            <span class="stat-number"><?php echo $blocked_total; ?></span>
                            <span class="stat-label">Blocked Dates</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar-times"></i>
                            <span class="stat-number">
                                <?php 
                                $upcoming_blocked = 0;
                                foreach ($blocked_dates as $blocked) {
                                    if (strtotime($blocked['specific_date']) >= strtotime('today')) {
                                        $upcoming_blocked++;
                                    }
                                }
                                echo $upcoming_blocked;
                                ?>
                            </span>
                            <span class="stat-label">Upcoming</span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-calendar-check"></i>
                            <span class="stat-number">
                                <?php 
                                $next_available = null;
                                $today = strtotime('today');
                                for ($i = 1; $i <= 30; $i++) {
                                    $check_date = date('Y-m-d', strtotime("+$i days"));
                                    $is_blocked = false;
                                    foreach ($blocked_dates as $blocked) {
                                        if ($blocked['specific_date'] == $check_date) {
                                            $is_blocked = true;
                                            break;
                                        }
                                    }
                                    if (!$is_blocked) {
                                        $next_available = $i;
                                        break;
                                    }
                                }
                                echo $next_available ? $next_available . ' days' : 'N/A';
                                ?>
                            </span>
                            <span class="stat-label">Next Available</span>
                        </div>
                    </div>
                    
                    <div class="schedule-section enhanced">
                        <div id="blocked-dates-container">
                        <?php if (empty($blocked_dates)): ?>
                            <div class="empty-state enhanced">
                                <div class="empty-icon">
                                    <i class="fas fa-ban"></i>
                                </div>
                                <p class="empty-message">No blocked dates</p>
                                <p class="empty-hint">Block specific dates when you're unavailable</p>
                            </div>
                        <?php else: ?>
                            <!-- Bulk Actions Bar -->
                            <div id="bulk-actions-bar" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #721c24;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <strong style="color: var(--navy);">
                                            <span id="selected-count">0</span> date(s) selected
                                        </strong>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="button" class="btn-secondary-modern" onclick="selectAllBlocked()">Select All</button>
                                        <button type="button" class="btn-secondary-modern" onclick="deselectAllBlocked()">Deselect All</button>
                                        <button type="button" class="btn-delete-modern" onclick="bulkUnblock()">
                                            Unblock Selected
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <form id="bulk-unblock-form" method="POST">
                                <input type="hidden" name="action" value="bulk_unblock">
                                <input type="hidden" name="blocked_ids" id="blocked-ids-input">
                            </form>
                            
                            <div class="schedules-grid enhanced">
                                <?php foreach ($blocked_dates_paginated as $schedule): ?>
                                    <div class="schedule-card blocked-schedule enhanced" style="position: relative;">
                                        <!-- Checkbox for multi-select -->
                                        <div class="bulk-checkbox-container" style="position: absolute; top: 15px; left: 15px; display: none; z-index: 10;">
                                            <input type="checkbox" class="blocked-checkbox" value="<?php echo $schedule['id']; ?>" 
                                                   onchange="updateBulkActions()" 
                                                   style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--gold);">
                                        </div>
                                        <div class="schedule-header blocked-date-content" style="transition: margin-left 0.3s ease;">
                                            <div class="schedule-badges">
                                                <?php if (!empty($schedule['start_date']) && !empty($schedule['end_date'])): ?>
                                                    <span class="schedule-badge badge-blocked" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); font-weight: 600;">
                                                        <i class="fas fa-calendar-times"></i>
                                                        BLOCKED RANGE
                                                    </span>
                                                <?php else: ?>
                                                    <span class="schedule-badge badge-blocked" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); font-weight: 600;">
                                                        <i class="fas fa-ban"></i>
                                                        BLOCKED DATE
                                                    </span>
                                                <?php endif; ?>
                                                <span class="blocked-reason">
                                                    <?php echo $schedule['blocked_reason'] ? htmlspecialchars($schedule['blocked_reason']) : 'Unavailable'; ?>
                                                </span>
                                                <?php 
                                                $check_date = !empty($schedule['specific_date']) ? $schedule['specific_date'] : $schedule['start_date'];
                                                if (!empty($check_date) && strtotime($check_date) >= strtotime('today')): 
                                                ?>
                                                    <span class="schedule-badge badge-upcoming">
                                                        <i class="fas fa-arrow-up"></i>
                                                        Upcoming
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="schedule-actions">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="permanent_delete">
                                                    <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                                                    <button type="submit" class="btn-action btn-unblock" 
                                                            onclick="return confirm('Unblock this <?php echo (!empty($schedule['start_date']) && !empty($schedule['end_date'])) ? 'date range' : 'date'; ?>?')" title="Unblock">
                                                        <i class="fas fa-unlock"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="schedule-details enhanced">
                                            <div class="detail-item">
                                                <i class="fas fa-calendar-day"></i>
                                                <span class="detail-label">
                                                    <?php echo (!empty($schedule['start_date']) && !empty($schedule['end_date'])) ? 'Date Range:' : 'Date:'; ?>
                                                </span>
                                                <span class="detail-value">
                                                    <?php 
                                                    if (!empty($schedule['specific_date'])) {
                                                        echo date('l, M d, Y', strtotime($schedule['specific_date']));
                                                    } elseif (!empty($schedule['start_date']) && !empty($schedule['end_date'])) {
                                                        echo date('M d, Y', strtotime($schedule['start_date'])) . ' - ' . date('M d, Y', strtotime($schedule['end_date']));
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-ban"></i>
                                                <span class="detail-label">Status:</span>
                                                <span class="detail-value" style="color: #dc3545; font-weight: 600;">UNAVAILABLE</span>
                                            </div>
                                            <!-- Enhanced Details -->
                                            <?php if (!empty($schedule['start_date']) && !empty($schedule['end_date'])): ?>
                                                <div class="detail-item enhanced-detail">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span class="detail-label">Duration:</span>
                                                    <span class="detail-value">
                                                        <?php 
                                                        $days = ceil((strtotime($schedule['end_date']) - strtotime($schedule['start_date'])) / (60 * 60 * 24)) + 1;
                                                        echo $days . ' day' . ($days > 1 ? 's' : '');
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="detail-item enhanced-detail">
                                                    <i class="fas fa-hourglass-start"></i>
                                                    <span class="detail-label">Starts In:</span>
                                                    <span class="detail-value">
                                                        <?php 
                                                        $days_until = ceil((strtotime($schedule['start_date']) - strtotime('today')) / (60 * 60 * 24));
                                                        if ($days_until < 0) {
                                                            echo 'Started ' . abs($days_until) . ' days ago';
                                                        } elseif ($days_until == 0) {
                                                            echo 'Today';
                                                        } elseif ($days_until == 1) {
                                                            echo 'Tomorrow';
                                                        } else {
                                                            echo $days_until . ' days';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php else: ?>
                                                <div class="detail-item enhanced-detail">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <span class="detail-label">Days Until:</span>
                                                    <span class="detail-value">
                                                        <?php 
                                                        $days_until = ceil((strtotime($schedule['specific_date']) - strtotime('today')) / (60 * 60 * 24));
                                                        if ($days_until < 0) {
                                                            echo 'Past (' . abs($days_until) . ' days ago)';
                                                        } elseif ($days_until == 0) {
                                                            echo 'Today';
                                                        } elseif ($days_until == 1) {
                                                            echo 'Tomorrow';
                                                        } else {
                                                            echo $days_until . ' days';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="detail-item enhanced-detail">
                                                <i class="fas fa-info-circle"></i>
                                                <span class="detail-label">Reason:</span>
                                                <span class="detail-value">
                                                    <?php echo $schedule['blocked_reason'] ? htmlspecialchars($schedule['blocked_reason']) : 'Not specified'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination Controls -->
                            <?php if ($blocked_total_pages > 1): ?>
                                <div class="pagination">
                                    <?php if ($blocked_page > 1): ?>
                                        <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $blocked_page - 1; ?>)" title="Previous">
                                            ← Prev
                                        </a>
                                    <?php else: ?>
                                        <span class="disabled">← Prev</span>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Show page numbers with smart ellipsis
                                    $range = 2; // Show 2 pages on each side of current
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
                </div>
            </div>
            
    </main>
    
    <!-- Create Schedule Modal -->
    <div id="scheduleModal" class="consultation-modal" style="display:none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Schedule</h2>
                <span class="modal-close" onclick="closeScheduleModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" class="modern-form">
                    <input type="hidden" name="action" id="new_schedule_action" value="add_onetime">
                    <input type="hidden" name="block_date" id="schedule_block_date" value="">

                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Status</label>
                                <select name="schedule_status" id="schedule_status" class="form-select-modern">
                                    <option value="today">Weekly</option>
                                    <option value="one_time">One time available</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>
                            </div>
                            <div class="form-group-modern">
                                <label class="form-label-modern">Date</label>
                                <input type="date" name="specific_date" id="schedule_date" min="<?php echo date('Y-m-d'); ?>" class="form-input-modern" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Start Time</label>
                                <input type="time" name="start_time_onetime" id="schedule_start" value="09:00" class="form-input-modern">
                            </div>
                            <div class="form-group-modern">
                                <label class="form-label-modern">End Time</label>
                                <input type="time" name="end_time_onetime" id="schedule_end" value="17:00" class="form-input-modern">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group-modern">
                                <label class="form-label-modern">Max Appointment</label>
                                <select name="max_appointments_onetime" id="schedule_max" class="form-select-modern">
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i === 5 ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group-modern" id="schedule_reason_group" style="display:none;">
                                <label class="form-label-modern">Reason (for Unavailable)</label>
                                <select name="reason" class="form-select-modern">
                                    <option value="Unavailable">Unavailable</option>
                                    <option value="Sick Leave">Sick Leave</option>
                                    <option value="Personal Leave">Personal Leave</option>
                                    <option value="Holiday">Holiday</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Out of Office">Out of Office</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions-modern" style="margin-top: 10px; display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="btn-secondary-modern" onclick="closeScheduleModal()">Cancel</button>
                        <button type="submit" class="btn-primary-modern">
                            <i class="fas fa-save"></i> Save Schedule
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php 
    // Output async email script if present
    if ($async_email_script) {
        echo $async_email_script;
    }
    ?>
    
    <script>
    function updatePanelInfo(title, description) {
        document.getElementById('panel-title').textContent = 'MD Law - ' + title;
        document.getElementById('panel-description').textContent = description;
    }
    </script>
</body>
</html>
