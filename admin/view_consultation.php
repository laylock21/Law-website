<?php
/**
 * View Individual Consultation Details
 * Shows complete information for a specific consultation with edit functionality
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

// Handle status updates (legacy support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_status') {
        $new_status = $_POST['new_status'];
        $cancellation_reason = $_POST['cancellation_reason'] ?? 'Administrative decision';
        
        try {
            $pdo = getDBConnection();
            
            // Get current status before updating
            $check_stmt = $pdo->prepare("SELECT c_status FROM consultations WHERE c_id = ?");
            $check_stmt->execute([$consultation_id]);
            $current = $check_stmt->fetch();
            $old_status = $current ? $current['c_status'] : null;
            
            // Update status and cancellation reason if applicable
            if ($new_status === 'cancelled') {
                $stmt = $pdo->prepare("UPDATE consultations SET c_status = ?, c_cancellation_reason = ? WHERE c_id = ?");
                $stmt->execute([$new_status, $cancellation_reason, $consultation_id]);
            } else {
                // Clear cancellation reason if status is not cancelled
                $stmt = $pdo->prepare("UPDATE consultations SET c_status = ?, c_cancellation_reason = NULL WHERE c_id = ?");
                $stmt->execute([$new_status, $consultation_id]);
            }
            
            if ($stmt->rowCount() > 0) {
                // Send email notifications for status changes
                require_once '../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
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
        header("Location: view_consultation.php?id={$consultation_id}");
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
    $stmt = $pdo->prepare("SELECT * FROM consultations WHERE c_id = ?");
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
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .consultation-section {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.25rem;
        }
        
        .edit-btn {
            background: #3a3a3a;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s;
        }
        
        .edit-btn:hover {
            background: #2a2a2a;
        }
        
        .edit-btn i {
            margin-right: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            padding: 0.75rem 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #7f8c8d;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            color: #2c3e50;
            font-size: 1rem;
        }
        
        .info-value a {
            color: #3a3a3a;
            text-decoration: none;
        }
        
        .info-value a:hover {
            text-decoration: underline;
        }
        
        .case-description-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid var(--gold);
            margin-top: 0.5rem;
            line-height: 1.6;
        }
        
        .status-badge-large {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d4edda; color: #155724; }
        
        .edit-form {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .edit-form.active {
            display: block;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-save {
            background: #27ae60;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .btn-save:hover {
            background: #229954;
        }
        
        .btn-cancel {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .btn-cancel:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            z-index: 9999;
            animation: slideIn 0.3s ease-out;
            min-width: 250px;
        }
        
        .toast.success {
            background: #27ae60;
        }
        
        .toast.error {
            background: #e74c3c;
        }
        
        .toast.info {
            background: #3a3a3a;
        }
        
        .toast .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
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
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .toast {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
            }
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <div class="admin-action-buttons" style="justify-content: flex-start; margin-bottom: 1rem;">
                <a href="consultations.php" class="admin-btn admin-btn-outline">← Back to Consultations</a>
            </div>
            
            <div id="alert-container"></div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($consultation): ?>
                <?php
                // Get all lawyers for dropdown
                $lawyers_stmt = $pdo->query("SELECT lawyer_id, lp_fullname FROM lawyer_profile ORDER BY lp_fullname");
                $all_lawyers = $lawyers_stmt->fetchAll();
                
                // Get all practice areas
                $practice_areas_stmt = $pdo->query("SELECT pa_id, area_name FROM practice_areas WHERE is_active = 1 ORDER BY area_name");
                $all_practice_areas = $practice_areas_stmt->fetchAll();
                
                // Get current lawyer name
                $current_lawyer_name = 'Not assigned';
                if ($consultation['lawyer_id']) {
                    $lawyer_stmt = $pdo->prepare("SELECT lp_fullname FROM lawyer_profile WHERE lawyer_id = ?");
                    $lawyer_stmt->execute([$consultation['lawyer_id']]);
                    $lawyer = $lawyer_stmt->fetch();
                    if ($lawyer) {
                        $current_lawyer_name = $lawyer['lp_fullname'];
                    }
                }
                ?>
                
                <!-- Header Section -->
                <div class="consultation-section">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <h2 style="margin: 0 0 0.5rem 0;">Consultation #<?php echo $consultation['c_id']; ?></h2>
                            <p style="margin: 0; color: #7f8c8d;">
                                <i class="fas fa-calendar"></i> Created: <?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?>
                                <?php if ($current_lawyer_name !== 'Not assigned'): ?>
                                    • <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($current_lawyer_name); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <span class="status-badge-large status-<?php echo $consultation['c_status']; ?>">
                            <?php echo ucfirst($consultation['c_status']); ?>
                        </span>
                    </div>
                </div>

                <!-- General Information Section -->
                <div class="consultation-section">
                    <div class="section-header">
                        <h3><i class="fas fa-info-circle"></i> General Information</h3>
                        <button class="edit-btn" onclick="toggleEdit('general-info')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <div id="general-info-view">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($consultation['c_full_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value">
                                    <a href="mailto:<?php echo htmlspecialchars($consultation['c_email']); ?>">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($consultation['c_email']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value">
                                    <a href="tel:<?php echo htmlspecialchars($consultation['c_phone']); ?>">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($consultation['c_phone']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem;">
                            <div class="info-label">Case Description</div>
                            <div class="case-description-box">
                                <?php echo nl2br(htmlspecialchars($consultation['c_case_description'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="general-info-edit" class="edit-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" id="edit_full_name" value="<?php echo htmlspecialchars($consultation['c_full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" id="edit_email" value="<?php echo htmlspecialchars($consultation['c_email']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="tel" id="edit_phone" value="<?php echo htmlspecialchars($consultation['c_phone']); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Case Description</label>
                            <textarea id="edit_case_description"><?php echo htmlspecialchars($consultation['c_case_description']); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button class="btn-save" onclick="saveSection('general-info')">
                                <i class="fas fa-check"></i> Save Changes
                            </button>
                            <button class="btn-cancel" onclick="toggleEdit('general-info')">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Consultation Schedule Section -->
                <div class="consultation-section">
                    <div class="section-header">
                        <h3><i class="fas fa-calendar-check"></i> Consultation Schedule</h3>
                        <button class="edit-btn" onclick="toggleEdit('schedule')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <div id="schedule-view" class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Practice Area</div>
                            <div class="info-value">
                                <i class="fas fa-balance-scale"></i> <?php echo htmlspecialchars($consultation['c_practice_area'] ?? 'Not specified'); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Assigned Lawyer</div>
                            <div class="info-value">
                                <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($current_lawyer_name); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultation Date</div>
                            <div class="info-value">
                                <?php 
                                if ($consultation['c_consultation_date']) {
                                    echo '<i class="fas fa-calendar"></i> ' . date('l, F d, Y', strtotime($consultation['c_consultation_date']));
                                } else {
                                    echo '<span style="color: #999;">No date set</span>';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Consultation Time</div>
                            <div class="info-value">
                                <?php 
                                if (!empty($consultation['c_consultation_time'])) {
                                    echo '<i class="fas fa-clock"></i> ' . date('g:i A', strtotime($consultation['c_consultation_time']));
                                } else {
                                    echo '<span style="color: #999;">No time set</span>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="schedule-edit" class="edit-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Practice Area</label>
                                <select id="edit_practice_area">
                                    <?php foreach ($all_practice_areas as $pa): ?>
                                        <option value="<?php echo htmlspecialchars($pa['area_name']); ?>" 
                                            <?php echo ($consultation['c_practice_area'] === $pa['area_name']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pa['area_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Assigned Lawyer</label>
                                <select id="edit_lawyer">
                                    <option value="">Not assigned</option>
                                    <?php foreach ($all_lawyers as $lawyer): ?>
                                        <option value="<?php echo $lawyer['lawyer_id']; ?>" 
                                            <?php echo ($consultation['lawyer_id'] == $lawyer['lawyer_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lawyer['lp_fullname']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Consultation Date</label>
                                <input type="date" id="edit_date" value="<?php echo $consultation['c_consultation_date']; ?>">
                            </div>
                            <div class="form-group">
                                <label>Consultation Time</label>
                                <input type="time" id="edit_time" value="<?php echo $consultation['c_consultation_time']; ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="btn-save" onclick="saveSection('schedule')">
                                <i class="fas fa-check"></i> Save Changes
                            </button>
                            <button class="btn-cancel" onclick="toggleEdit('schedule')">Cancel</button>
                        </div>
                    </div>
                </div>

                <!-- Status Section -->
                <div class="consultation-section">
                    <div class="section-header">
                        <h3><i class="fas fa-tasks"></i> Consultation Status</h3>
                        <button class="edit-btn" onclick="toggleEdit('status')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    
                    <div id="status-view" class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Current Status</div>
                            <div class="info-value">
                                <span class="status-badge-large status-<?php echo $consultation['c_status']; ?>">
                                    <?php echo ucfirst($consultation['c_status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($consultation['c_status'] === 'cancelled' && $consultation['c_cancellation_reason']): ?>
                        <div class="info-item">
                            <div class="info-label">Cancellation Reason</div>
                            <div class="info-value"><?php echo htmlspecialchars($consultation['c_cancellation_reason']); ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <div class="info-label">Last Updated</div>
                            <div class="info-value">
                                <i class="fas fa-clock"></i> <?php echo date('M d, Y g:i A', strtotime($consultation['updated_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div id="status-edit" class="edit-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Status</label>
                                <select id="edit_status" onchange="toggleCancellationField()">
                                    <option value="pending" <?php echo $consultation['c_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $consultation['c_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $consultation['c_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $consultation['c_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="form-group" id="cancellation-reason-group" style="display: none;">
                                <label>Cancellation Reason</label>
                                <select id="edit_cancellation_reason">
                                    <?php $saved_reason = $consultation['c_cancellation_reason'] ?? ''; ?>
                                    <option value="Administrative decision" <?php echo ($saved_reason === 'Administrative decision') ? 'selected' : ''; ?>>Administrative decision</option>
                                    <option value="Lawyer unavailable" <?php echo ($saved_reason === 'Lawyer unavailable') ? 'selected' : ''; ?>>Lawyer unavailable</option>
                                    <option value="Client request" <?php echo ($saved_reason === 'Client request') ? 'selected' : ''; ?>>Client request</option>
                                    <option value="Scheduling conflict" <?php echo ($saved_reason === 'Scheduling conflict') ? 'selected' : ''; ?>>Scheduling conflict</option>
                                    <option value="Emergency situation" <?php echo ($saved_reason === 'Emergency situation') ? 'selected' : ''; ?>>Emergency situation</option>
                                    <option value="Technical issues" <?php echo ($saved_reason === 'Technical issues') ? 'selected' : ''; ?>>Technical issues</option>
                                    <option value="Other circumstances" <?php echo ($saved_reason === 'Other circumstances') ? 'selected' : ''; ?>>Other circumstances</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button class="btn-save" onclick="saveSection('status')">
                                <i class="fas fa-check"></i> Save Changes
                            </button>
                            <button class="btn-cancel" onclick="toggleEdit('status')">Cancel</button>
                        </div>
                    </div>
                </div>


            <?php else: ?>
                <div class="alert alert-error">
                    Consultation not found or error loading data.
                </div>
                <a href="consultations.php" class="admin-btn admin-btn-outline">← Back to Consultations</a>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        const consultationId = <?php echo $consultation_id; ?>;
        
        function showToast(message, type = 'info', duration = 3000) {
            // Remove any existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            if (type === 'info') {
                toast.innerHTML = `
                    <div class="spinner"></div>
                    <span>${message}</span>
                `;
            } else {
                const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
                toast.innerHTML = `
                    ${icon}
                    <span>${message}</span>
                `;
            }
            
            document.body.appendChild(toast);
            
            if (duration > 0) {
                setTimeout(() => {
                    toast.style.animation = 'slideIn 0.3s ease-out reverse';
                    setTimeout(() => toast.remove(), 300);
                }, duration);
            }
            
            return toast;
        }
        
        function toggleEdit(section) {
            const viewDiv = document.getElementById(`${section}-view`);
            const editDiv = document.getElementById(`${section}-edit`);
            
            if (editDiv.classList.contains('active')) {
                editDiv.classList.remove('active');
                viewDiv.style.display = 'grid';
            } else {
                editDiv.classList.add('active');
                viewDiv.style.display = 'none';
            }
        }
        
        function toggleCancellationField() {
            const statusSelect = document.getElementById('edit_status');
            const reasonGroup = document.getElementById('cancellation-reason-group');
            
            if (statusSelect.value === 'cancelled') {
                reasonGroup.style.display = 'block';
            } else {
                reasonGroup.style.display = 'none';
            }
        }
        
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
        
        async function saveSection(section) {
            let data = { consultation_id: consultationId };
            
            if (section === 'general-info') {
                data.c_full_name = document.getElementById('edit_full_name').value;
                data.c_email = document.getElementById('edit_email').value;
                data.c_phone = document.getElementById('edit_phone').value;
                data.c_case_description = document.getElementById('edit_case_description').value;
            } else if (section === 'schedule') {
                data.c_practice_area = document.getElementById('edit_practice_area').value;
                data.lawyer_id = document.getElementById('edit_lawyer').value;
                data.c_consultation_date = document.getElementById('edit_date').value;
                data.c_consultation_time = document.getElementById('edit_time').value;
            } else if (section === 'status') {
                data.c_status = document.getElementById('edit_status').value;
                if (data.c_status === 'cancelled') {
                    data.cancellation_reason = document.getElementById('edit_cancellation_reason').value;
                }
            }
            
            // Show saving toast
            showToast('Saving changes...', 'info', 0);
            
            try {
                const response = await fetch('../api/admin/update_consultation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message || 'Changes saved successfully!', 'success', 2000);
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showToast(result.message || 'Error saving changes', 'error', 4000);
                }
            } catch (error) {
                showToast('Error saving changes: ' + error.message, 'error', 4000);
            }
        }
        
        // Initialize cancellation field visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleCancellationField();
        });
    </script>
</body>
</html>
