<?php
/**
 * View Individual Consultation Details
 * Shows complete information for a specific consultation
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Get consultation ID from URL
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$consultation_id) {
    header('Location: consultations.php');
    exit;
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $new_status = $_POST['new_status'];
        $cancellation_reason = $_POST['cancellation_reason'] ?? 'Administrative decision';
        
        try {
            $pdo = getDBConnection();
            
            // Get current status before updating
            $check_stmt = $pdo->prepare("SELECT status FROM consultations WHERE id = ?");
            $check_stmt->execute([$consultation_id]);
            $current = $check_stmt->fetch();
            $old_status = $current ? $current['status'] : null;
            
            // Update status and cancellation reason if applicable
            if ($new_status === 'cancelled') {
                $stmt = $pdo->prepare("UPDATE consultations SET status = ?, cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$new_status, $cancellation_reason, $consultation_id]);
            } else {
                // Clear cancellation reason if status is not cancelled
                $stmt = $pdo->prepare("UPDATE consultations SET status = ?, cancellation_reason = NULL WHERE id = ?");
                $stmt->execute([$new_status, $consultation_id]);
            }
            
            if ($stmt->rowCount() > 0) {
                // Send email notifications for status changes
                require_once '../includes/EmailNotification.php';
                $emailNotification = new EmailNotification($pdo);
                $queued = false;
                
                if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
                    $queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
                } elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
                    $queued = $emailNotification->notifyAppointmentCancelled($consultation_id, $cancellation_reason);
                } elseif ($new_status === 'completed' && $old_status !== 'completed') {
                    $queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
                }
                
                if ($queued) {
                    // Trigger async email processing
                    $async_script = "
                    <script>
                    setTimeout(function() {
                        fetch('../process_emails_async.php', {
                            method: 'POST',
                            headers: {'X-Requested-With': 'XMLHttpRequest'}
                        }).then(response => response.json())
                        .then(data => {
                            if (data.sent > 0) {
                                console.log('Email sent successfully');
                            }
                        }).catch(error => {
                            console.log('Email processing error:', error);
                        });
                    }, 100);
                    </script>";
                    
                    $_SESSION['async_email_script'] = $async_script;
                    $email_type = ($new_status === 'confirmed') ? 'Confirmation' : 
                                 (($new_status === 'cancelled') ? 'Cancellation' : 'Completion');
                    $_SESSION['consultation_message'] = "Status updated successfully! {$email_type} email sent to client.";
                } else {
                    $_SESSION['consultation_message'] = "Status updated successfully!";
                }
            }
        } catch (Exception $e) {
            $_SESSION['consultation_error'] = "Error updating status: " . $e->getMessage();
        }
        
        // Redirect to prevent form resubmission
        header('Location: view_consultation.php?id=' . $consultation_id);
        exit;
    }
}

// Check for session messages
if (isset($_SESSION['consultation_message'])) {
    $success_message = $_SESSION['consultation_message'];
    unset($_SESSION['consultation_message']);
}
if (isset($_SESSION['consultation_error'])) {
    $error_message = $_SESSION['consultation_error'];
    unset($_SESSION['consultation_error']);
}

// Get consultation details
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE id = ?");
    $stmt->execute([$consultation_id]);
    $consultation = $stmt->fetch();
    
    if (!$consultation) {
        header('Location: consultations.php');
        exit;
    }
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $consultation = null;
}
?>

<?php
// Set page-specific variables for the header
$page_title = "View Consultations";
$active_page = "consultations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - View Consultation | Lex & Co.</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="admin-page">
    <?php include 'partials/header.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <div class="admin-action-buttons" style="justify-content: flex-start; margin-bottom: 1rem;">
                <a href="consultations.php" class="admin-btn admin-btn-outline">← Back to Consultations</a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="admin-alert admin-alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($consultation): ?>
                <div class="admin-welcome-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem;">
                        <div>
                            <h2 style="margin-bottom: 0.25rem;">Consultation #<?php echo $consultation['id']; ?></h2>
                            <p style="margin: 0; color: #6c757d;">
                                <?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?> • <?php echo htmlspecialchars($consultation['practice_area']); ?>
                            </p>
                        </div>
                        <span class="admin-status-badge admin-status-<?php echo $consultation['status']; ?>"><?php echo ucfirst($consultation['status']); ?></span>
                    </div>
                </div>

                <div class="admin-consultations-table" style="margin-top: 1.5rem;">
                    <div class="admin-section-header">
                        <h3>Client & Case Details</h3>
                    </div>
                    <table>
                        <tbody>
                            <tr>
                                <th>Client Name</th>
                                <td><?php echo htmlspecialchars($consultation['full_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Email Address</th>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($consultation['email']); ?>"><?php echo htmlspecialchars($consultation['email']); ?></a>
                                </td>
                            </tr>
                            <tr>
                                <th>Phone Number</th>
                                <td>
                                    <a href="tel:<?php echo htmlspecialchars($consultation['phone']); ?>"><?php echo htmlspecialchars($consultation['phone']); ?></a>
                                </td>
                            </tr>
                            <tr>
                                <th>Practice Area</th>
                                <td><?php echo htmlspecialchars($consultation['practice_area']); ?></td>
                            </tr>
                            <tr>
                                <th>Preferred Lawyer</th>
                                <td><?php echo htmlspecialchars($consultation['selected_lawyer']); ?></td>
                            </tr>
                            <tr>
                                <th>Consultation Date</th>
                                <td>
                                    <?php 
                                    if ($consultation['consultation_date']) {
                                        echo date('l, F d, Y', strtotime($consultation['consultation_date']));
                                    } elseif ($consultation['selected_date']) {
                                        echo date('l, F d, Y', strtotime($consultation['selected_date']));
                                    } else {
                                        echo 'No specific date requested';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Consultation Time</th>
                                <td>
                                    <?php 
                                    if (!empty($consultation['consultation_time'])) {
                                        echo '<i class="fas fa-clock" style="color: var(--gold);"></i> ' . date('g:i A', strtotime($consultation['consultation_time']));
                                    } else {
                                        echo '<span style="color: #999;">No specific time selected</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Case Description</th>
                                <td>
                                    <div class="case-description"><?php echo nl2br(htmlspecialchars($consultation['case_description'])); ?></div>
                                </td>
                            </tr>
                            <tr>
                                <th>Submission Date</th>
                                <td><?php echo date('l, F d, Y \\a\\t g:i A', strtotime($consultation['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th>Last Updated</th>
                                <td><?php echo date('l, F d, Y \\a\\t g:i A', strtotime($consultation['updated_at'])); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="admin-quick-actions" style="margin-top: 1.5rem; text-align: left;">
                    <h3>Actions</h3>
                    <div class="admin-action-buttons" style="justify-content: flex-start;">
                        <a href="mailto:<?php echo htmlspecialchars($consultation['email']); ?>" class="admin-btn admin-btn-primary">📧 Email Client</a>
                        <a href="tel:<?php echo htmlspecialchars($consultation['phone']); ?>" class="admin-btn admin-btn-success">📞 Call Client</a>
                        <a href="consultations.php" class="admin-btn admin-btn-outline">← Back to List</a>
                    </div>
                </div>

                <div class="admin-welcome-section" style="margin-top: 1.5rem;">
                    <div class="admin-section-header">
                        <h3>Update Status</h3>
                    </div>
                    <form method="POST" style="padding-top: 1rem;">
                        <input type="hidden" name="action" value="update_status">
                        <div class="admin-form-group">
                            <label for="new_status">Status</label>
                            <select name="new_status" id="new_status" onchange="toggleCancellationReason()">
                                <option value="pending" <?php echo $consultation['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $consultation['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo $consultation['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="completed" <?php echo $consultation['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="admin-form-group" id="cancellation_reason_group" style="display: none;">
                            <label for="cancellation_reason">Cancellation Reason</label>
                            <select name="cancellation_reason" id="cancellation_reason">
                                <?php $saved_reason = $consultation['cancellation_reason'] ?? ''; ?>
                                <option value="Administrative decision" <?php echo ($saved_reason === 'Administrative decision') ? 'selected' : ''; ?>>Administrative decision</option>
                                <option value="Lawyer unavailable" <?php echo ($saved_reason === 'Lawyer unavailable') ? 'selected' : ''; ?>>Lawyer unavailable</option>
                                <option value="Client request" <?php echo ($saved_reason === 'Client request') ? 'selected' : ''; ?>>Client request</option>
                                <option value="Scheduling conflict" <?php echo ($saved_reason === 'Scheduling conflict') ? 'selected' : ''; ?>>Scheduling conflict</option>
                                <option value="Emergency situation" <?php echo ($saved_reason === 'Emergency situation') ? 'selected' : ''; ?>>Emergency situation</option>
                                <option value="Technical issues" <?php echo ($saved_reason === 'Technical issues') ? 'selected' : ''; ?>>Technical issues</option>
                                <option value="Other circumstances" <?php echo ($saved_reason === 'Other circumstances') ? 'selected' : ''; ?>>Other circumstances</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="admin-btn admin-btn-primary">Update Status</button>
                        
                        <script>
                        function toggleCancellationReason() {
                            const statusSelect = document.getElementById('new_status');
                            const reasonGroup = document.getElementById('cancellation_reason_group');
                            
                            if (statusSelect.value === 'cancelled') {
                                reasonGroup.style.display = 'block';
                            } else {
                                reasonGroup.style.display = 'none';
                            }
                        }
                        
                        // Show reason field if cancelled is already selected
                        document.addEventListener('DOMContentLoaded', function() {
                            toggleCancellationReason();
                            
                            // If status is cancelled, show the reason field immediately
                            const statusSelect = document.getElementById('new_status');
                            if (statusSelect.value === 'cancelled') {
                                document.getElementById('cancellation_reason_group').style.display = 'block';
                            }
                        });
                        </script>
                    </form>
                </div>
            <?php else: ?>
                <div class="admin-alert admin-alert-error">
                    Consultation not found or error loading data.
                </div>
                <a href="consultations.php" class="admin-btn admin-btn-outline">← Back to Consultations</a>
            <?php endif; ?>
        </div>
        </div>
    </main>
    
    <?php 
    // Output async email script if present
    if (isset($_SESSION['async_email_script'])) {
        echo $_SESSION['async_email_script'];
        unset($_SESSION['async_email_script']);
    }
    ?>
</body>
</html>
