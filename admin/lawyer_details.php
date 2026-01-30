<?php
/**
 * Admin Lawyer Details Page
 * View and manage individual lawyer information
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/Logger.php';

Logger::init('INFO');

$message = '';
$error = '';
$lawyer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lawyer_id) {
    header('Location: manage_lawyers.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $current_status = $_POST['current_status'];
                    $new_status = $current_status ? 0 : 1;
                    
                    $toggle_stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE user_id = ? AND role = 'lawyer'");
                    $toggle_stmt->execute([$new_status, $lawyer_id]);
                    
                    Logger::security('lawyer_status_changed', [
                        'admin_id' => $_SESSION['user_id'],
                        'lawyer_id' => $lawyer_id,
                        'new_status' => $new_status ? 'active' : 'inactive'
                    ]);
                    
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    $_SESSION['lawyer_message'] = "Lawyer $status_text successfully";
                    header("Location: lawyer_details.php?id=$lawyer_id");
                    exit;
                    
                case 'reset_password':
                    $password_option = $_POST['password_option'] ?? 'auto';
                    
                    $password_to_display = '';
                    $force_change = isset($_POST['force_change']) ? 'temporary' : null;
                    
                    if ($password_option === 'custom') {
                        $custom_password = $_POST['custom_password'] ?? '';
                        $confirm_password = $_POST['confirm_password'] ?? '';
                        
                        if (empty($custom_password)) {
                            throw new Exception('Custom password is required when selected');
                        }
                        
                        if (strlen($custom_password) < 8) {
                            throw new Exception('Password must be at least 8 characters long');
                        }
                        
                        if ($custom_password !== $confirm_password) {
                            throw new Exception('Passwords do not match');
                        }
                        
                        $hashed_password = password_hash($custom_password, PASSWORD_BCRYPT);
                        $password_to_display = $custom_password;
                    } else {
                        $temp_password = 'lawyer' . rand(1000, 9999);
                        $hashed_password = password_hash($temp_password, PASSWORD_BCRYPT);
                        $password_to_display = $temp_password;
                        $force_change = 'temporary';
                    }
                    
                    $reset_stmt = $pdo->prepare("UPDATE users SET password = ?, temporary_password = ? WHERE user_id = ? AND role = 'lawyer'");
                    $reset_stmt->execute([$hashed_password, $force_change, $lawyer_id]);
                    
                    Logger::security('lawyer_password_reset', [
                        'admin_id' => $_SESSION['user_id'],
                        'lawyer_id' => $lawyer_id,
                        'password_type' => $password_option
                    ]);
                    
                    if ($password_option === 'auto') {
                        $_SESSION['new_password'] = $password_to_display;
                        $_SESSION['lawyer_message'] = "Password reset successfully! New temporary password: <strong>$password_to_display</strong>";
                    } else {
                        $_SESSION['lawyer_message'] = "Password reset successfully with custom password.";
                    }
                    
                    header("Location: lawyer_details.php?id=$lawyer_id");
                    exit;
                    
                case 'delete_lawyer':
                    $pdo->beginTransaction();
                    
                    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role = 'lawyer'");
                    $delete_result = $delete_stmt->execute([$lawyer_id]);
                    
                    if (!$delete_result) {
                        throw new Exception('Failed to delete lawyer');
                    }
                    
                    $pdo->commit();
                    
                    Logger::security('lawyer_deleted', [
                        'admin_id' => $_SESSION['user_id'],
                        'lawyer_id' => $lawyer_id
                    ]);
                    
                    $_SESSION['lawyer_message'] = "Lawyer deleted successfully";
                    header('Location: manage_lawyers.php');
                    exit;
            }
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Check for session message
if (isset($_SESSION['lawyer_message'])) {
    $message = $_SESSION['lawyer_message'];
    unset($_SESSION['lawyer_message']);
}

// Get lawyer details
try {
    $pdo = getDBConnection();
    
    $lawyer_stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            u.phone,
            u.is_active,
            u.created_at,
            lp.lp_fullname,
            lp.lp_description,
            lp.profile,
            GROUP_CONCAT(DISTINCT pa.area_name SEPARATOR ', ') as specializations,
            (SELECT COUNT(*) FROM consultations c WHERE c.lawyer_id = u.user_id) as consultation_count,
            (SELECT COUNT(*) FROM consultations c WHERE c.lawyer_id = u.user_id AND c.c_status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM consultations c WHERE c.lawyer_id = u.user_id AND c.c_status = 'confirmed') as confirmed_count,
            (SELECT COUNT(*) FROM consultations c WHERE c.lawyer_id = u.user_id AND c.c_status = 'completed') as completed_count
        FROM users u
        LEFT JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        LEFT JOIN lawyer_specializations ls ON u.user_id = ls.lawyer_id
        LEFT JOIN practice_areas pa ON ls.pa_id = pa.pa_id
        WHERE u.user_id = ? AND u.role = 'lawyer'
        GROUP BY u.user_id, u.username, u.email, u.phone, u.is_active, u.created_at, lp.lp_fullname, lp.lp_description, lp.profile
    ");
    $lawyer_stmt->execute([$lawyer_id]);
    $lawyer = $lawyer_stmt->fetch();
    
    if (!$lawyer) {
        header('Location: manage_lawyers.php');
        exit;
    }
    
    // Get practice areas for form
    $areas_stmt = $pdo->query("SELECT pa_id, area_name FROM practice_areas ORDER BY area_name");
    $practice_areas = $areas_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

$page_title = "Lawyer Details";
$active_page = "lawyer";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lawyer Details - Admin Dashboard</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="../includes/confirmation-modal.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .details-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .details-header {
            background: #c5a253;
            color: white;
            padding: 32px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .details-header h1 {
            margin: 0 0 8px 0;
            font-size: 32px;
            font-weight: 700;
        }
        
        .details-header .subtitle {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .info-card h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid #c5a253;
        }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #666;
            width: 200px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #000;
            flex: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .stat-box .number {
            font-size: 36px;
            font-weight: 700;
            color: #c5a253;
            margin-bottom: 8px;
        }
        
        .stat-box .label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #c5a253;
            color: white;
        }
        
        .btn-primary:hover {
            background: #b08f42;
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .status-active {
            background: #c5a253;
            color: white;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="details-container">
            <a href="manage_lawyers.php" class="btn btn-secondary" style="margin-bottom: 16px;">
                <i class="fas fa-arrow-left"></i> Back to Lawyers
            </a>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <div class="details-header">
                <div style="display: flex; gap: 32px; align-items: flex-start;">
                    <!-- Profile Picture - Left -->
                    <div style="flex-shrink: 0;">
                        <?php if (!empty($lawyer['profile'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo base64_encode($lawyer['profile']); ?>" 
                                 alt="<?php echo htmlspecialchars($lawyer['lp_fullname']); ?>" 
                                 style="width: 150px; height: 150px; border-radius: 12px; object-fit: cover; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                        <?php else: ?>
                            <div style="width: 150px; height: 150px; border-radius: 12px; background: white; display: flex; align-items: center; justify-content: center; border: 4px solid white; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
                                <i class="fas fa-user" style="font-size: 60px; color: #c5a253;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Personal Information - Right -->
                    <div style="flex: 1;">
                        <h1 style="margin: 0 0 12px 0;"><?php echo htmlspecialchars($lawyer['lp_fullname'] ?? 'Unknown'); ?></h1>
                        <div style="margin-bottom: 16px;">
                            <span class="status-badge <?php echo $lawyer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo $lawyer['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            
            
            <div class="info-card">
                <h2><i class="fas fa-user"></i> Basic Information</h2>
                <div class="info-row">
                    <div class="info-label">Full Name:</div>
                    <div class="info-value"><?php echo htmlspecialchars($lawyer['lp_fullname'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Username:</div>
                    <div class="info-value"><?php echo htmlspecialchars($lawyer['username']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Email:</div>
                    <div class="info-value"><?php echo htmlspecialchars($lawyer['email']); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Phone:</div>
                    <div class="info-value"><?php echo htmlspecialchars($lawyer['phone'] ?: 'Not set'); ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Description:</div>
                    <div class="info-value"><?php echo htmlspecialchars($lawyer['lp_description'] ?: 'No description'); ?></div>
                </div>
            </div>

            
            <div class="info-card">
                <h2><i class="fas fa-briefcase"></i> Specializations</h2>
                <?php if ($lawyer['specializations']): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                        <?php 
                        $specializations = explode(', ', $lawyer['specializations']);
                        foreach ($specializations as $spec): 
                        ?>
                            <span style="background: #f0f0f0; padding: 8px 16px; border-radius: 20px; font-weight: 600;">
                                <?php echo htmlspecialchars(trim($spec)); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #999;">No specializations assigned</p>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h2><i class="fas fa-cog"></i> Actions</h2>
                <div class="action-buttons">
                    <a href="manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>" class="btn btn-primary" style="text-decoration: none;">
                        <i class="fas fa-calendar-alt"></i> Manage Schedule
                    </a>
                    
                    <button type="button" class="btn btn-primary" onclick="openPasswordResetModal()">
                        <i class="fas fa-key"></i> Reset Password
                    </button>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="current_status" value="<?php echo $lawyer['is_active']; ?>">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-toggle-<?php echo $lawyer['is_active'] ? 'on' : 'off'; ?>"></i>
                            <?php echo $lawyer['is_active'] ? 'Deactivate' : 'Activate'; ?> Account
                        </button>
                    </form>
                    
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash"></i> Delete Lawyer
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- Password Reset Modal -->
    <div class="modal fade" id="passwordResetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <input type="radio" name="password_option" value="auto" checked> 
                                Auto-generate temporary password
                            </label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <input type="radio" name="password_option" value="custom"> 
                                Set custom password
                            </label>
                        </div>
                        
                        <div id="customPasswordFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="custom_password" id="customPassword">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" name="confirm_password" id="confirmPassword">
                            </div>
                            <div class="mb-3">
                                <label>
                                    <input type="checkbox" name="force_change"> 
                                    Force password change on first login
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../includes/confirmation-modal.js"></script>
    
    <script>
        function openPasswordResetModal() {
            const modal = new bootstrap.Modal(document.getElementById('passwordResetModal'));
            modal.show();
        }
        
        // Toggle custom password fields
        document.querySelectorAll('input[name="password_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customFields = document.getElementById('customPasswordFields');
                if (this.value === 'custom') {
                    customFields.style.display = 'block';
                } else {
                    customFields.style.display = 'none';
                }
            });
        });
        
        async function confirmDelete() {
            const confirmed = await ConfirmModal.confirm({
                title: 'Delete Lawyer',
                message: 'Are you sure you want to delete this lawyer? This action cannot be undone and will also delete all associated consultations.',
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_lawyer">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
