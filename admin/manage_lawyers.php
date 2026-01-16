<?php
/**
 * Admin Lawyer Management
 * Create, view, edit, and manage lawyers
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/upload_config.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_lawyer':
                    // Create new lawyer
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $first_name = trim($_POST['first_name']);
                    $last_name = trim($_POST['last_name']);
                    $phone = trim($_POST['phone']);
                    $description = trim($_POST['description']);
                    $specializations = $_POST['specializations'] ?? [];
                    $password_option = $_POST['password_option'] ?? 'auto';
                    
                    // Validation
                    if (empty($username) || empty($email) || empty($first_name) || empty($last_name)) {
                        throw new Exception('Please fill in all required fields');
                    }
                    
                    // Check if username/email already exists
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([$username, $email]);
                    if ($check_stmt->fetch()) {
                        throw new Exception('Username or email already exists');
                    }
                    
                    // Handle password setup
                    $password_to_display = '';
                    $is_auto_generated = false;
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
                        
                        $hashed_password = $custom_password; // store plain text as requested
                        $password_to_display = $custom_password;
                        $is_auto_generated = false;
                    } else {
                        // Auto-generate temporary password
                        $temp_password = 'lawyer' . rand(1000, 9999);
                        $hashed_password = $temp_password; // store plain text as requested
                        $password_to_display = $temp_password;
                        $is_auto_generated = true;
                        // Auto-generated passwords are always temporary
                        $force_change = 'temporary';
                    }
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Insert user with default date preferences
                    $user_stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, first_name, last_name, phone, description, role, is_active, default_booking_weeks, max_booking_weeks, booking_window_enabled, temporary_password) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'lawyer', 1, 52, 104, 1, ?)
                    ");
                    $user_stmt->execute([$username, $email, $hashed_password, $first_name, $last_name, $phone, $description, $force_change]);
                    $lawyer_id = $pdo->lastInsertId();
                    
                    // Add specializations
                    if (!empty($specializations)) {
                        $spec_stmt = $pdo->prepare("INSERT INTO lawyer_specializations (user_id, practice_area_id) VALUES (?, ?)");
                        foreach ($specializations as $area_id) {
                            $spec_stmt->execute([$lawyer_id, $area_id]);
                        }
                    }
                    
                    $pdo->commit();
                    
                    // Create success message based on password type
                    if ($password_option === 'auto') {
                        // Detailed credentials box for auto-generated passwords
                        $credentials_box = "
                        <div style='background: linear-gradient(135deg, #e8f5e8, #d4edda); border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin: 15px 0;'>
                            <h4 style='color: #155724; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;'>
                                Lawyer Account Created Successfully
                            </h4>
                            <div style='background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>
                                <h5 style='margin: 0 0 10px 0; color: #155724;'>Auto-Generated Login Credentials:</h5>
                                <div style='font-family: monospace; background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0;'>
                                    <strong>Username:</strong> <span style='color: #0B1D3A; font-weight: bold;'>$username</span><br>
                                    <strong>Email:</strong> <span style='color: #0B1D3A; font-weight: bold;'>$email</span><br>
                                    <strong>Temporary Password:</strong> 
                                    <span id='password-display' style='color: #dc3545; font-weight: bold; font-size: 18px; background: #fff3cd; padding: 4px 8px; border-radius: 4px;'>$password_to_display</span>
                                    <button onclick='togglePasswordVisibility()' class='btn' style='margin-left: 10px; padding: 4px 8px; font-size: 12px; background: #6c757d; color: white;'>Hide</button>
                                </div>
                                <p style='color: #856404; margin: 10px 0;'><strong>Temporary Password:</strong> Lawyer must change password on first login</p>
                            </div>
                            <div style='margin-top: 15px; padding: 12px; background: rgba(255,193,7,0.1); border-radius: 6px; border-left: 4px solid #ffc107;'>
                                <strong style='color: #856404;'>Security Reminder:</strong>
                                <ul style='margin: 8px 0 0 20px; color: #856404;'>
                                    <li>Share these credentials through a secure channel (encrypted email, secure messaging)</li>
                                    <li>Do not send credentials via regular email or text message</li>
                                    <li>Consider sharing username and password separately</li>
                                    <li>Inform the lawyer to change their password after first login</li>
                                </ul>
                            </div>
                            <div style='text-align: center; margin-top: 15px;'>
                                <button onclick='copyCredentials()' class='btn btn-secondary' style='margin-right: 10px;'>Copy Credentials</button>
                                <button onclick='printCredentials()' class='btn btn-secondary'>Print Credentials</button>
                            </div>
                        </div>
                        <script>
                            // Initialize password system immediately when this message loads
                            setTimeout(function() {
                                const passwordDisplay = document.getElementById('password-display');
                                if (passwordDisplay && !window.originalPassword) {
                                    window.originalPassword = passwordDisplay.textContent;
                                    window.passwordVisible = true;
                                }
                            }, 100);
                        </script>";
                        
                        $message = $credentials_box;
                    } else {
                        // Simple success message for custom passwords
                        $message = "Lawyer account created successfully with custom password.";
                    }
                    
                    // Store message in session and redirect (PRG pattern)
                    $_SESSION['lawyer_message'] = $message;
                    header('Location: manage_lawyers.php');
                    exit;
                    
                case 'toggle_status':
                    // Toggle lawyer active status
                    $lawyer_id = $_POST['lawyer_id'];
                    $current_status = $_POST['current_status'];
                    $new_status = $current_status ? 0 : 1;
                    
                    $toggle_stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'lawyer'");
                    $toggle_stmt->execute([$new_status, $lawyer_id]);
                    
                    $status_text = $new_status ? 'activated' : 'deactivated';
                    
                    // Store message in session and redirect (PRG pattern)
                    $_SESSION['lawyer_message'] = "Lawyer $status_text successfully";
                    header('Location: manage_lawyers.php');
                    exit;
                    
                case 'reset_password':
                    // Enhanced password reset for lawyers
                    $lawyer_id = $_POST['lawyer_id'];
                    $password_option = $_POST['password_option'] ?? 'auto';
                    
                    // Get lawyer info for success message
                    $lawyer_info_stmt = $pdo->prepare("SELECT first_name, last_name, username, email FROM users WHERE id = ? AND role = 'lawyer'");
                    $lawyer_info_stmt->execute([$lawyer_id]);
                    $lawyer_info = $lawyer_info_stmt->fetch();
                    
                    if (!$lawyer_info) {
                        throw new Exception('Lawyer not found');
                    }
                    
                    // Handle password setup
                    $password_to_display = '';
                    $is_auto_generated = false;
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
                        
                        $hashed_password = $custom_password; // store plain text as requested
                        $password_to_display = $custom_password;
                        $is_auto_generated = false;
                    } else {
                        // Auto-generate temporary password
                        $temp_password = 'lawyer' . rand(1000, 9999);
                        $hashed_password = $temp_password; // store plain text as requested
                        $password_to_display = $temp_password;
                        $is_auto_generated = true;
                        // Auto-generated passwords are always temporary
                        $force_change = 'temporary';
                    }
                    
                    // Update password and temporary_password status
                    $reset_stmt = $pdo->prepare("UPDATE users SET password = ?, temporary_password = ? WHERE id = ? AND role = 'lawyer'");
                    $reset_stmt->execute([$hashed_password, $force_change, $lawyer_id]);
                    
                    // Create detailed success message
                    $lawyer_name = $lawyer_info['first_name'] . ' ' . $lawyer_info['last_name'];
                    $username = $lawyer_info['username'];
                    $email = $lawyer_info['email'];
                    
                    if ($password_option === 'auto') {
                        // Detailed credentials box for auto-generated passwords
                        $credentials_box = "
                        <div style='background: linear-gradient(135deg, #e8f5e8, #d4edda); border: 2px solid #28a745; border-radius: 10px; padding: 20px; margin: 15px 0;'>
                            <h4 style='color: #155724; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px;'>
                                🔄 Password Reset Successful for $lawyer_name
                            </h4>
                            <div style='background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;'>
                                <h5 style='margin: 0 0 10px 0; color: #155724;'>New Auto-Generated Login Credentials:</h5>
                                <div style='font-family: monospace; background: #f8f9fa; padding: 12px; border-radius: 6px; margin: 10px 0;'>
                                    <strong>Username:</strong> <span style='color: #0B1D3A; font-weight: bold;'>$username</span><br>
                                    <strong>Email:</strong> <span style='color: #0B1D3A; font-weight: bold;'>$email</span><br>
                                    <strong>New Password:</strong> 
                                    <span id='reset-password-display' style='color: #dc3545; font-weight: bold; font-size: 18px; background: #fff3cd; padding: 4px 8px; border-radius: 4px;'>$password_to_display</span>
                                    <button onclick='toggleResetPasswordVisibility()' class='btn' style='margin-left: 10px; padding: 4px 8px; font-size: 12px; background: #6c757d; color: white;'>Hide</button>
                                </div>
                                <p style='color: #856404; margin: 10px 0;'><strong>Temporary Password:</strong> Lawyer must change password on first login</p>
                            </div>
                            <div style='margin-top: 15px; padding: 12px; background: rgba(255,193,7,0.1); border-radius: 6px; border-left: 4px solid #ffc107;'>
                                <strong style='color: #856404;'>Security Reminder:</strong>
                                <ul style='margin: 8px 0 0 20px; color: #856404;'>
                                    <li>Share these credentials through a secure channel</li>
                                    <li>Inform the lawyer to change their password after first login</li>
                                    <li>Consider sharing username and password separately</li>
                                </ul>
                            </div>
                            <div style='text-align: center; margin-top: 15px;'>
                                <button onclick='copyResetCredentials()' class='btn btn-secondary' style='margin-right: 10px;'>Copy Credentials</button>
                                <button onclick='printResetCredentials()' class='btn btn-secondary'>Print Credentials</button>
                            </div>
                        </div>
                        <script>
                            setTimeout(function() {
                                const passwordDisplay = document.getElementById('reset-password-display');
                                if (passwordDisplay && !window.resetOriginalPassword) {
                                    window.resetOriginalPassword = passwordDisplay.textContent;
                                    window.resetPasswordVisible = true;
                                }
                            }, 100);
                        </script>";
                        
                        $message = $credentials_box;
                    } else {
                        // Simple success message for custom passwords
                        $message = "Password reset successfully for <strong>$lawyer_name</strong> with custom password. Please advise the lawyer to use their new password for login.";
                    }
                    
                    // Store message in session and redirect (PRG pattern)
                    $_SESSION['lawyer_message'] = $message;
                    header('Location: manage_lawyers.php');
                    exit;
                    
                case 'delete_lawyer':
                    // Delete lawyer and associated consultations
                    $lawyer_id = $_POST['lawyer_id'];
                    
                    // Get lawyer info for confirmation message
                    $lawyer_info_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ? AND role = 'lawyer'");
                    $lawyer_info_stmt->execute([$lawyer_id]);
                    $lawyer_info = $lawyer_info_stmt->fetch();
                    
                    if (!$lawyer_info) {
                        throw new Exception('Lawyer not found');
                    }
                    
                    // Count consultations that will be deleted
                    $consultation_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM consultations WHERE lawyer_id = ?");
                    $consultation_count_stmt->execute([$lawyer_id]);
                    $consultation_count = $consultation_count_stmt->fetchColumn();
                    
                    // Start transaction for safe deletion
                    $pdo->beginTransaction();
                    
                    // Delete lawyer (consultations will be automatically deleted due to foreign key cascade)
                    $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'lawyer'");
                    $delete_result = $delete_stmt->execute([$lawyer_id]);
                    
                    if (!$delete_result) {
                        throw new Exception('Failed to delete lawyer');
                    }
                    
                    $pdo->commit();
                    
                    $lawyer_name = $lawyer_info['first_name'] . ' ' . $lawyer_info['last_name'];
                    $message = "Lawyer <strong>$lawyer_name</strong> has been permanently deleted.";
                    if ($consultation_count > 0) {
                        $message .= " <strong>$consultation_count</strong> associated consultation(s) were also deleted.";
                    }
                    
                    // Store message in session and redirect (PRG pattern)
                    $_SESSION['lawyer_message'] = $message;
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

// Get all lawyers
// Check for session message (from redirect after POST)
if (isset($_SESSION['lawyer_message'])) {
    $message = $_SESSION['lawyer_message'];
    unset($_SESSION['lawyer_message']);
}

try {
    $pdo = getDBConnection();
    
    // Get lawyers with their specializations and consultation counts
    $lawyers_stmt = $pdo->query("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            u.phone,
            u.description,
            u.is_active,
            u.created_at,
            u.profile_picture,
            GROUP_CONCAT(DISTINCT pa.area_name SEPARATOR ', ') as specializations,
            (SELECT COUNT(*) FROM consultations c WHERE c.lawyer_id = u.id) as consultation_count
        FROM users u
        LEFT JOIN lawyer_specializations ls ON u.id = ls.user_id
        LEFT JOIN practice_areas pa ON ls.practice_area_id = pa.id
        WHERE u.role = 'lawyer'
        GROUP BY u.id, u.username, u.email, u.first_name, u.last_name, u.phone, u.description, u.is_active, u.created_at, u.profile_picture
        ORDER BY u.created_at DESC
    ");
    $lawyers = $lawyers_stmt->fetchAll();
    
    // Get practice areas for form
    $areas_stmt = $pdo->query("SELECT * FROM practice_areas ORDER BY area_name");
    $practice_areas = $areas_stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Lawyer Management";
$active_page = "lawyer";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lawyers - Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="../includes/confirmation-modal.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="section" style="padding:32px;">
                <h2>Statistics</h2>
                <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($lawyers); ?></div>
                    <div>Total Lawyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($lawyers, fn($l) => $l['is_active'])); ?></div>
                    <div>Active Lawyers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_filter($lawyers, fn($l) => !empty($l['profile_picture']))); ?></div>
                    <div>With Profile Pictures</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($practice_areas); ?></div>
                    <div>Practice Areas</div>
                </div>
            </div>
        </div>

        <!-- Existing Lawyers -->
        <div class="section" style="padding:32px 0 0 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                <h2 style="margin: 0; border-bottom: 3px solid #c5a253; padding-bottom: 8px;">Existing Lawyers (<?php echo count($lawyers); ?>)</h2>
                <button type="button" class="btn btn-primary" id="openCreateLawyerModal" style="padding: 12px 24px; background: #c5a253; border: none; border-radius: 8px; color: white; font-weight: 600; white-space: nowrap;"><i class="fas fa-plus-circle"></i> CREATE NEW LAWYER</button>
            </div>
            
            <?php if (empty($lawyers)): ?>
                <p>No lawyers found. Click "Create New Lawyer" button above to add your first lawyer.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="lawyers-table">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Specializations</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lawyers as $lawyer): ?>
                                <tr>
                                    <td>
                                        <img src="<?php echo htmlspecialchars(getProfilePictureUrl($lawyer['profile_picture'])); ?>" 
                                             alt="Profile" class="profile-pic" 
                                             onerror="this.src='<?php echo htmlspecialchars(getProfilePictureUrl('')); ?>'; this.style.display='block';">
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?></strong>
                                        <?php if ($lawyer['description']): ?>
                                            <br><small style="color: #666;"><?php echo htmlspecialchars(substr($lawyer['description'], 0, 50)) . (strlen($lawyer['description']) > 50 ? '...' : ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($lawyer['username']); ?></td>
                                    <td><?php echo htmlspecialchars($lawyer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($lawyer['phone'] ?: 'Not set'); ?></td>
                                    <td>
                                        <?php if ($lawyer['specializations']): ?>
                                            <?php 
                                            $specializations = explode(', ', $lawyer['specializations']);
                                            foreach ($specializations as $spec): 
                                            ?>
                                                <small><?php echo htmlspecialchars(trim($spec)); ?></small>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <small style="color: #999;">No specializations</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $lawyer['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $lawyer['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($lawyer['created_at'])); ?></td>
                                    <td>
                                        <a href="manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer['id']; ?>" 
                                           class="btn btn-primary" 
                                           title="Manage Schedule">
                                            📅
                                        </a>
                                        
                                        <form method="POST" style="display: inline;" id="toggleForm<?php echo $lawyer['id']; ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="lawyer_id" value="<?php echo $lawyer['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $lawyer['is_active']; ?>">
                                            <button type="button" class="btn <?php echo $lawyer['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                    onclick="confirmToggleStatus('<?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?>', <?php echo $lawyer['is_active']; ?>, <?php echo $lawyer['id']; ?>)">
                                                <?php echo $lawyer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        
                                        <button type="button" class="btn btn-secondary" 
                                                onclick="openPasswordResetModal(<?php echo $lawyer['id']; ?>, '<?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?>')">
                                                🔐
                                        </button>
                                        
                                        <form method="POST" style="display: inline;" id="deleteForm<?php echo $lawyer['id']; ?>">
                                            <input type="hidden" name="action" value="delete_lawyer">
                                            <input type="hidden" name="lawyer_id" value="<?php echo $lawyer['id']; ?>">
                                            <button type="button" class="btn btn-danger" 
                                                    onclick="confirmDelete('<?php echo htmlspecialchars($lawyer['first_name'] . ' ' . $lawyer['last_name']); ?>', <?php echo $lawyer['consultation_count']; ?>, <?php echo $lawyer['id']; ?>)">
                                                🗑️
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Password Reset Modal -->
    <div id="passwordResetModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color:black;">Reset Password</h3>
                <span class="close" onclick="closePasswordResetModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Reset password for: <strong id="resetLawyerName"></strong></p>
                
                <form id="passwordResetForm" method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" id="resetLawyerId" name="lawyer_id">
                    
                    <!-- Password Options -->
                    <div class="form-group">
                        <label>Password Reset Options</label>
                        <div class="password-setup-container" style="display: flex; flex-direction: column; gap: 16px; margin-top: 10px;">
                            <div class="password-option" style="background: white; border: 2px solid #e0e0e0; border-radius: 10px; padding: 16px; transition: all 0.3s ease;">
                                <label class="password-option-label" style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; margin: 0;">
                                    <input type="radio" name="password_option" value="auto" checked style="margin-top: 4px; width: 18px; height: 18px; accent-color: #c5a253;">
                                    <div class="option-content">
                                        <strong style="display: block; font-size: 15px; color: #000; margin-bottom: 4px;">Auto-generate temporary password</strong>
                                        <small style="display: block; color: #666; font-size: 13px; line-height: 1.4;">System will create a secure temporary password that the lawyer must change on first login</small>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="password-option" style="background: white; border: 2px solid #e0e0e0; border-radius: 10px; padding: 16px; transition: all 0.3s ease;">
                                <label class="password-option-label" style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; margin: 0;">
                                    <input type="radio" name="password_option" value="custom" style="margin-top: 4px; width: 18px; height: 18px; accent-color: #c5a253;">
                                    <div class="option-content">
                                        <strong style="display: block; font-size: 15px; color: #000; margin-bottom: 4px;">Set custom password</strong>
                                    </div>
                                </label>
                                <div id="reset-custom-password-fields" class="custom-password-fields" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 2px solid rgba(197, 162, 83, 0.2);">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                        <div>
                                            <label for="reset_custom_password" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000; font-size: 14px;">Password</label>
                                            <input type="password" id="reset_custom_password" name="custom_password" placeholder="Enter password" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                                            <small style="color: #666; display: block; margin-top: 4px; font-size: 12px;">Minimum 8 characters</small>
                                        </div>
                                        <div>
                                            <label for="reset_confirm_password" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000; font-size: 14px;">Confirm Password</label>
                                            <input type="password" id="reset_confirm_password" name="confirm_password" placeholder="Confirm password" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                                            <small id="reset-password-match" style="display: block; margin-top: 4px; font-size: 12px;"></small>
                                        </div>
                                    </div>
                                    <div style="margin-top: 10px;">
                                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: #333;">
                                            <input type="checkbox" id="reset_force_change" name="force_change" checked style="width: 16px; height: 16px; accent-color: #c5a253;">
                                            Force password change on first login
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closePasswordResetModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    /* Password Reset Modal Styles */
    .password-option:has(input[type="radio"]:checked) {
        border-color: #c5a253 !important;
        background: linear-gradient(135deg, #faf7f0 0%, white 100%) !important;
        box-shadow: 0 4px 12px rgba(197, 162, 83, 0.15);
    }

    .password-option-label input[type="radio"] {
        cursor: pointer;
    }

    .custom-password-fields {
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 500px;
        }
    }

    .password-option:has(input[type="radio"]:checked) .custom-password-fields {
        display: block !important;
    }

    .checkbox-label {
        transition: color 0.3s ease;
    }

    .checkbox-label:hover {
        color: #c5a253;
    }

    .checkbox-label input[type="checkbox"] {
        cursor: pointer;
    }

    /* Input Focus Animations */
    #reset_custom_password:focus,
    #reset_confirm_password:focus {
        border-color: #c5a253 !important;
        box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1) !important;
        transform: translateY(-1px);
        transition: all 0.3s ease;
    }
    </style>

    <script>
        // Enhanced page interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (this.type === 'submit' || this.closest('form')) {
                        this.style.opacity = '0.7';
                        this.style.transform = 'scale(0.98)';
                        
                        // Add loading text for submit buttons
                        if (this.type === 'submit') {
                            const originalText = this.textContent;
                            this.textContent = 'Processing...';
                            
                            setTimeout(() => {
                                this.textContent = originalText;
                                this.style.opacity = '1';
                                this.style.transform = 'scale(1)';
                            }, 2000);
                        }
                    }
                });
            });
            
            // Animate stats cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'slideInUp 0.6s ease-out forwards';
                    }
                });
            }, observerOptions);
            
            document.querySelectorAll('.stat-card, .section').forEach(el => {
                observer.observe(el);
            });
            
            // Add CSS animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .stat-card, .section {
                    opacity: 0;
                }
            `;
            document.head.appendChild(style);
        });
        
        // Password option handling
        document.querySelectorAll('input[name="password_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customFields = document.getElementById('custom-password-fields');
                if (this.value === 'custom') {
                    customFields.style.display = 'block';
                    document.getElementById('custom_password').required = true;
                    document.getElementById('confirm_password').required = true;
                } else {
                    customFields.style.display = 'none';
                    document.getElementById('custom_password').required = false;
                    document.getElementById('confirm_password').required = false;
                }
            });
        });
        
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('custom_password').value;
            const confirm = this.value;
            const matchIndicator = document.getElementById('password-match');
            
            if (confirm === '') {
                matchIndicator.textContent = '';
                return;
            }
            
            if (password === confirm) {
                matchIndicator.textContent = '✓ Passwords match';
                matchIndicator.style.color = '#28a745';
            } else {
                matchIndicator.textContent = '✗ Passwords do not match';
                matchIndicator.style.color = '#dc3545';
            }
        });
        
        // Password strength indicator
        document.getElementById('custom_password').addEventListener('input', function() {
            const password = this.value;
            const confirm = document.getElementById('confirm_password');
            
            // Clear confirm field validation when password changes
            if (confirm.value) {
                document.getElementById('password-match').textContent = '';
            }
            
            // Basic strength indication
            if (password.length >= 8) {
                this.style.borderColor = '#28a745';
            } else if (password.length > 0) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
        
        
        // Enhanced form validation with visual feedback
        document.getElementById('createLawyerForm').addEventListener('submit', function(e) {
            const specializations = document.querySelectorAll('input[name="specializations[]"]:checked');
            const requiredFields = ['first_name', 'last_name', 'username', 'email'];
            let isValid = true;
            
            // Check required fields
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    field.style.boxShadow = '0 0 0 3px rgba(220, 53, 69, 0.1)';
                    isValid = false;
                    
                    setTimeout(() => {
                        field.style.borderColor = '#e9ecef';
                        field.style.boxShadow = '0 2px 8px rgba(0,0,0,0.04)';
                    }, 3000);
                }
            });
            
            // Check password validation if custom option is selected
            const passwordOption = document.querySelector('input[name="password_option"]:checked').value;
            if (passwordOption === 'custom') {
                const customPassword = document.getElementById('custom_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (!customPassword) {
                    document.getElementById('custom_password').style.borderColor = '#dc3545';
                    isValid = false;
                }
                
                if (customPassword.length < 8) {
                    document.getElementById('custom_password').style.borderColor = '#dc3545';
                    showNotification('Password must be at least 8 characters long', 'error');
                    isValid = false;
                }
                
                if (customPassword !== confirmPassword) {
                    document.getElementById('confirm_password').style.borderColor = '#dc3545';
                    showNotification('Passwords do not match', 'error');
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                showNotification('Please fix the form errors', 'error');
                return false;
            }
            
            if (specializations.length === 0) {
                if (!confirm('No specializations selected. Continue anyway?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            showNotification('Creating lawyer account...', 'info');
        });
        
        // Enhanced checkbox interactions
        document.querySelectorAll('.checkbox-item').forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            const label = item.querySelector('label');
            
            // Ensure label clicks work properly
            label.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default to handle manually
                checkbox.checked = !checkbox.checked;
                checkbox.dispatchEvent(new Event('change', { bubbles: true }));
            });
            
            // Handle clicks on the container (but not on checkbox or label)
            item.addEventListener('click', function(e) {
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                // Add ripple effect
                const ripple = document.createElement('div');
                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255,255,255,0.6);
                    transform: scale(0);
                    animation: ripple 0.6s linear;
                    pointer-events: none;
                `;
                
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
        
        // Add ripple animation CSS
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            .checkbox-item {
                position: relative;
                overflow: hidden;
            }
        `;
        document.head.appendChild(rippleStyle);
        
        // Password visibility toggle
        window.passwordVisible = true;
        window.originalPassword = '';
        
        function togglePasswordVisibility() {
            const passwordDisplay = document.getElementById('password-display');
            const toggleButton = document.querySelector('button[onclick="togglePasswordVisibility()"]');
            
            if (!passwordDisplay || !toggleButton) {
                console.log('Password display or toggle button not found');
                return;
            }
            
            // Initialize original password if not set
            if (!window.originalPassword) {
                window.originalPassword = passwordDisplay.textContent;
            }
            
            if (window.passwordVisible) {
                // Hide password
                passwordDisplay.textContent = '••••••••';
                passwordDisplay.style.letterSpacing = '2px';
                toggleButton.innerHTML = 'Show';
                toggleButton.style.background = '#28a745';
                window.passwordVisible = false;
            } else {
                // Show password
                passwordDisplay.textContent = window.originalPassword;
                passwordDisplay.style.letterSpacing = 'normal';
                toggleButton.innerHTML = 'Hide';
                toggleButton.style.background = '#6c757d';
                window.passwordVisible = true;
            }
        }
        
        // Initialize password visibility system when success message appears
        function initializePasswordSystem() {
            const passwordDisplay = document.getElementById('password-display');
            if (passwordDisplay && !originalPassword) {
                originalPassword = passwordDisplay.textContent;
                passwordVisible = true;
            }
        }
        
        // Call initialization periodically to catch dynamically added elements
        setInterval(initializePasswordSystem, 500);
        
        // Auto-hide password after 30 seconds for security
        setTimeout(() => {
            if (window.passwordVisible && document.getElementById('password-display')) {
                togglePasswordVisibility();
                showNotification('Password hidden for security', 'info');
            }
        }, 30000);
        
        // Credential management functions
        function copyCredentials() {
            // Get the actual password from the display element or original variable
            const passwordDisplay = document.getElementById('password-display');
            const usernameElement = document.querySelector('[data-username]');
            const emailElement = document.querySelector('[data-email]');
            
            let password = '';
            let username = '';
            let email = '';
            
            // Try to get password from display element or original password
            if (passwordDisplay) {
                password = window.passwordVisible ? passwordDisplay.textContent : window.originalPassword;
                // If password is dots, use original
                if (password === '••••••••') {
                    password = window.originalPassword;
                }
            }
            
            // Extract username and email from the success message
            const credentialsText = document.querySelector('.alert-success');
            if (credentialsText) {
                const usernameMatch = credentialsText.innerHTML.match(/Username:<\/strong>\s*<span[^>]*>([^<]+)<\/span>/);
                const emailMatch = credentialsText.innerHTML.match(/Email:<\/strong>\s*<span[^>]*>([^<]+)<\/span>/);
                
                if (usernameMatch) username = usernameMatch[1];
                if (emailMatch) email = emailMatch[1];
                
                // If password is hidden, try to extract from HTML
                if (!password) {
                    const passwordMatch = credentialsText.innerHTML.match(/Password:<\/strong>[^<]*<span[^>]*>([^<]+)<\/span>/);
                    if (passwordMatch) password = passwordMatch[1];
                }
            }
            
            if (username && email && password) {
                const credentials = `LAWYER LOGIN CREDENTIALS
=====================================
Username: ${username}
Email: ${email}
Password: ${password}

IMPORTANT SECURITY NOTES:
- Share these credentials securely
- Lawyer must change password on first login
- Do not send via regular email or text

Generated: ${new Date().toLocaleString()}`;
                
                navigator.clipboard.writeText(credentials).then(() => {
                    showNotification('Credentials copied to clipboard!', 'success');
                }).catch(() => {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = credentials;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showNotification('Credentials copied to clipboard!', 'success');
                });
            } else {
                showNotification('Could not extract credentials for copying', 'error');
            }
        }
        
        function printCredentials() {
            const credentialsDiv = document.querySelector('.alert-success');
            if (!credentialsDiv) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Lawyer Login Credentials</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .credentials { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>MD Law Firm - Lawyer Login Credentials</h1>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                    </div>
                    ${credentialsDiv.innerHTML}
                    <div class="footer">
                        <p>This document contains sensitive information. Handle securely and destroy after use.</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Enhanced delete confirmation with consultation warning using unified modal
        async function confirmDelete(lawyerName, consultationCount, lawyerId) {
            let message = `Are you sure you want to permanently delete "${lawyerName}"?`;
            
            if (consultationCount > 0) {
                message += `\n\nWARNING: This lawyer has ${consultationCount} consultation(s) that will also be permanently deleted!`;
            }
            
            message += `\n\nThis action cannot be undone.`;
            
            const confirmed = await ConfirmModal.confirm({
                title: 'Delete Lawyer',
                message: message,
                confirmText: 'Delete',
                cancelText: 'Cancel',
                type: 'danger'
            });
            
            if (confirmed) {
                document.getElementById('deleteForm' + lawyerId).submit();
            }
        }
        
        // Toggle status confirmation using unified modal
        async function confirmToggleStatus(lawyerName, currentStatus, lawyerId) {
            const action = currentStatus ? 'deactivate' : 'activate';
            const message = `Are you sure you want to ${action} "${lawyerName}"?`;
            
            const confirmed = await ConfirmModal.confirm({
                title: `${action.charAt(0).toUpperCase() + action.slice(1)} Lawyer`,
                message: message,
                confirmText: action.charAt(0).toUpperCase() + action.slice(1),
                cancelText: 'Cancel',
                type: currentStatus ? 'warning' : 'success'
            });
            
            if (confirmed) {
                document.getElementById('toggleForm' + lawyerId).submit();
            }
        }
        
        // Password Reset Modal Functions
        function openPasswordResetModal(lawyerId, lawyerName) {
            document.getElementById('resetLawyerId').value = lawyerId;
            document.getElementById('resetLawyerName').textContent = lawyerName;
            document.getElementById('passwordResetModal').style.display = 'flex';
            
            // Reset form
            document.getElementById('passwordResetForm').reset();
            document.querySelector('input[name="password_option"][value="auto"]').checked = true;
            document.getElementById('reset-custom-password-fields').style.display = 'none';
            document.getElementById('reset_custom_password').required = false;
            document.getElementById('reset_confirm_password').required = false;
        }
        
        function closePasswordResetModal() {
            document.getElementById('passwordResetModal').style.display = 'none';
        }
        
        // Password reset option handling
        document.querySelectorAll('input[name="password_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customFields = document.getElementById('reset-custom-password-fields');
                if (this.value === 'custom') {
                    customFields.style.display = 'block';
                    document.getElementById('reset_custom_password').required = true;
                    document.getElementById('reset_confirm_password').required = true;
                } else {
                    customFields.style.display = 'none';
                    document.getElementById('reset_custom_password').required = false;
                    document.getElementById('reset_confirm_password').required = false;
                }
            });
        });
        
        // Password confirmation validation for reset
        document.getElementById('reset_confirm_password').addEventListener('input', function() {
            const password = document.getElementById('reset_custom_password').value;
            const confirm = this.value;
            const matchIndicator = document.getElementById('reset-password-match');
            
            if (confirm === '') {
                matchIndicator.textContent = '';
                return;
            }
            
            if (password === confirm) {
                matchIndicator.textContent = '✓ Passwords match';
                matchIndicator.style.color = '#28a745';
                this.style.borderColor = '#28a745';
            } else {
                matchIndicator.textContent = '✗ Passwords do not match';
                matchIndicator.style.color = '#dc3545';
                this.style.borderColor = '#dc3545';
            }
        });
        
        // Password strength indicator for reset
        document.getElementById('reset_custom_password').addEventListener('input', function() {
            const password = this.value;
            const confirm = document.getElementById('reset_confirm_password');
            
            // Clear confirm field validation when password changes
            if (confirm.value) {
                document.getElementById('reset-password-match').textContent = '';
                confirm.style.borderColor = '#e9ecef';
            }
            
            // Basic strength indication
            if (password.length >= 8) {
                this.style.borderColor = '#28a745';
            } else if (password.length > 0) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('passwordResetModal');
            if (event.target === modal) {
                closePasswordResetModal();
            }
        });
        
        // Reset password visibility functions
        window.resetPasswordVisible = true;
        window.resetOriginalPassword = '';
        
        function toggleResetPasswordVisibility() {
            const passwordDisplay = document.getElementById('reset-password-display');
            const toggleButton = document.querySelector('button[onclick="toggleResetPasswordVisibility()"]');
            
            if (!passwordDisplay || !toggleButton) {
                return;
            }
            
            if (!window.resetOriginalPassword) {
                window.resetOriginalPassword = passwordDisplay.textContent;
            }
            
            if (window.resetPasswordVisible) {
                passwordDisplay.textContent = '••••••••';
                passwordDisplay.style.letterSpacing = '2px';
                toggleButton.innerHTML = 'Show';
                toggleButton.style.background = '#28a745';
                window.resetPasswordVisible = false;
            } else {
                passwordDisplay.textContent = window.resetOriginalPassword;
                passwordDisplay.style.letterSpacing = 'normal';
                toggleButton.innerHTML = 'Hide';
                toggleButton.style.background = '#6c757d';
                window.resetPasswordVisible = true;
            }
        }
        
        function copyResetCredentials() {
            const credentialsDiv = document.querySelector('.alert-success');
            if (!credentialsDiv) return;
            
            const usernameMatch = credentialsDiv.innerHTML.match(/Username:<\/strong>\s*<span[^>]*>([^<]+)<\/span>/);
            const emailMatch = credentialsDiv.innerHTML.match(/Email:<\/strong>\s*<span[^>]*>([^<]+)<\/span>/);
            
            let password = window.resetPasswordVisible ? 
                document.getElementById('reset-password-display').textContent : 
                window.resetOriginalPassword;
            
            if (password === '••••••••') {
                password = window.resetOriginalPassword;
            }
            
            if (usernameMatch && emailMatch && password) {
                const credentials = `LAWYER PASSWORD RESET CREDENTIALS
=====================================
Username: ${usernameMatch[1]}
Email: ${emailMatch[1]}
New Password: ${password}

IMPORTANT SECURITY NOTES:
- Share these credentials securely
- Lawyer must change password on first login
- Do not send via regular email or text

Generated: ${new Date().toLocaleString()}`;
                
                navigator.clipboard.writeText(credentials).then(() => {
                    showNotification('Reset credentials copied to clipboard!', 'success');
                }).catch(() => {
                    const textArea = document.createElement('textarea');
                    textArea.value = credentials;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    showNotification('Reset credentials copied to clipboard!', 'success');
                });
            }
        }
        
        function printResetCredentials() {
            const credentialsDiv = document.querySelector('.alert-success');
            if (!credentialsDiv) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Password Reset Credentials</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
                        .credentials { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                        .footer { margin-top: 30px; font-size: 12px; color: #666; text-align: center; }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>MD Law Firm - Password Reset Credentials</h1>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                    </div>
                    ${credentialsDiv.innerHTML}
                    <div class="footer">
                        <p>This document contains sensitive information. Handle securely and destroy after use.</p>
                    </div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }
        
        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 8px;
                color: white;
                font-weight: 600;
                z-index: 1000;
                transform: translateX(100%);
                transition: transform 0.3s ease;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            `;
            
            switch(type) {
                case 'success':
                    notification.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
                    break;
                case 'error':
                    notification.style.background = 'linear-gradient(135deg, #dc3545, #e74c3c)';
                    break;
                case 'info':
                    notification.style.background = 'linear-gradient(135deg, #17a2b8, #138496)';
                    break;
            }
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 3000);
        }

        // Drag to scroll functionality for table
        const tableWrapper = document.querySelector('.table-wrapper');
        if (tableWrapper) {
            let isDown = false;
            let startX;
            let scrollLeft;

            tableWrapper.addEventListener('mousedown', (e) => {
                // Only enable drag on the wrapper, not on buttons or links
                if (e.target.tagName === 'BUTTON' || e.target.tagName === 'A' || e.target.closest('button') || e.target.closest('a')) {
                    return;
                }
                isDown = true;
                tableWrapper.style.cursor = 'grabbing';
                startX = e.pageX - tableWrapper.offsetLeft;
                scrollLeft = tableWrapper.scrollLeft;
            });

            tableWrapper.addEventListener('mouseleave', () => {
                isDown = false;
                tableWrapper.style.cursor = 'grab';
            });

            tableWrapper.addEventListener('mouseup', () => {
                isDown = false;
                tableWrapper.style.cursor = 'grab';
            });

            tableWrapper.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - tableWrapper.offsetLeft;
                const walk = (x - startX) * 2; // Scroll speed multiplier
                tableWrapper.scrollLeft = scrollLeft - walk;
            });
        }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Unified Confirmation Modal JS -->
    <script src="../includes/confirmation-modal.js"></script>
    
    <!-- Create Lawyer Modal -->
    <?php include 'create_lawyer_modal_include.php'; ?>
        </div>
    </main>
</body>
</html>
