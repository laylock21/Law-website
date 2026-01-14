<?php
/**
 * Lawyer Profile Edit Form
 * Allows lawyers to edit their profile information including specializations
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../config/upload_config.php';

$lawyer_id = $_SESSION['lawyer_id'];
$message = '';
$error = '';

// Handle success/error messages from form processing
if (isset($_GET['success']) && $_GET['success'] == '1') {
    if (isset($_GET['password_changed']) && $_GET['password_changed'] == '1') {
        $message = 'Profile updated successfully! Your password has been changed.';
    } else {
        $message = 'Profile updated successfully!';
    }
}

if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}

try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // Get current lawyer information
    $lawyer_stmt = $pdo->prepare("
        SELECT id, username, email, first_name, last_name, phone, description, profile_picture 
        FROM users 
        WHERE id = ? AND role = 'lawyer'
    ");
    $lawyer_stmt->execute([$lawyer_id]);
    $lawyer = $lawyer_stmt->fetch();
    
    if (!$lawyer) {
        throw new Exception("Lawyer profile not found");
    }
    
    // Get all practice areas for specialization selection
    $practice_areas_stmt = $pdo->query("
        SELECT id, area_name 
        FROM practice_areas 
        WHERE is_active = 1 
        ORDER BY area_name
    ");
    $all_practice_areas = $practice_areas_stmt->fetchAll();
    
    // Get lawyer's current specializations
    $current_specializations_stmt = $pdo->prepare("
        SELECT practice_area_id 
        FROM lawyer_specializations 
        WHERE user_id = ?
    ");
    $current_specializations_stmt->execute([$lawyer_id]);
    $current_specializations = array_column($current_specializations_stmt->fetchAll(), 'practice_area_id');
    
} catch (Exception $e) {
    $error = "Error loading profile: " . $e->getMessage();
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Edit Profile";
$active_page = "profile";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - <?php echo htmlspecialchars($_SESSION['lawyer_name']); ?></title>
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="lawyer-page edit-profile-page">
    <?php include 'partials/sidebar.php'; ?> 

    <!-- Main Content -->
    <div class="edit-profile-content">
        <div class="container">
        
        <?php if ($message): ?>
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <strong>Success!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <strong>Error!</strong>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($lawyer) && $lawyer): ?>
        <!-- Profile Information Form -->
        <form action="process_profile_edit.php" method="POST" id="profileForm" class="profile-form">
            
            <!-- Top Row - Small Cards -->
            <div class="form-cards-top-row">
                <!-- Profile Picture Card - Small -->
                <div class="form-card profile-picture-card small-card">
                    <div class="card-header">
                        <h3><i class="fas fa-camera"></i> Profile Picture</h3>
                    </div>
                    <div class="card-body">
                        <div class="profile-picture-horizontal">
                            <div class="profile-image-section">
                                <img id="profile-preview" 
                                     src="<?php echo getProfilePictureUrl($lawyer['profile_picture'] ?? ''); ?>" 
                                     alt="Profile Picture" 
                                     class="profile-image-horizontal">
                            </div>
                            <div class="profile-controls-section">
                                <div class="control-buttons">
                                    <input type="file" 
                                           id="profile_picture" 
                                           name="profile_picture" 
                                           accept="image/jpeg,image/jpg,image/png,image/gif"
                                           style="display: none;">
                                    <button type="button" 
                                            class="btn-profile-action btn-choose" 
                                            onclick="document.getElementById('profile_picture').click()">
                                        <i class="fas fa-upload"></i>
                                        <span>Choose Photo</span>
                                    </button>
                                    <button type="button" 
                                            class="btn-profile-action btn-remove" 
                                            onclick="removeProfilePicture()"
                                            <?php echo empty($lawyer['profile_picture']) ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash"></i>
                                        <span>Remove Photo</span>
                                    </button>
                                </div>
                                <div class="file-requirements">
                                    <div class="req-item">
                                        <i class="fas fa-weight-hanging"></i>
                                        <span>Max 5MB</span>
                                    </div>
                                    <div class="req-item">
                                        <i class="fas fa-file-image"></i>
                                        <span>JPG, PNG, GIF</span>
                                    </div>
                                </div>
                                <div id="upload-status" class="upload-status-horizontal"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Password Change Card - Small -->
                <div class="form-card password-card small-card">
                    <div class="card-header">
                        <h3><i class="fas fa-lock"></i> Password Settings</h3>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 20px 10px;">
                            <p style="margin: 0 0 20px 0; color: #6c757d; font-size: 14px;">
                                <i class="fas fa-shield-alt" style="color: #28a745; font-size: 32px; display: block; margin-bottom: 10px;"></i>
                                Keep your account secure by updating your password regularly.
                            </p>
                            <button type="button" class="btn btn-primary" onclick="openPasswordModal()" style="width: 100%;">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row - Large Cards -->
            <div class="form-cards-bottom-row">
                <!-- Personal Information Card - Large -->
                <div class="form-card personal-info-card large-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user"></i> Personal Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-grid-large">
                            <div class="form-group">
                                <label for="first_name">
                                    <i class="fas fa-user"></i>
                                    <span>First Name</span>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name" 
                                       value="<?php echo htmlspecialchars($lawyer['first_name'] ?? ''); ?>" 
                                       required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="last_name">
                                    <i class="fas fa-user"></i>
                                    <span>Last Name</span>
                                    <span class="required">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name" 
                                       value="<?php echo htmlspecialchars($lawyer['last_name'] ?? ''); ?>" 
                                       required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    <span>Email Address</span>
                                    <span class="required">*</span>
                                </label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($lawyer['email']); ?>" 
                                       required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    <span>Phone Number</span>
                                </label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($lawyer['phone'] ?? ''); ?>" 
                                       placeholder="e.g., (+63) 917 123 4567" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="description">
                                <i class="fas fa-file-text"></i>
                                <span>Professional Description</span>
                            </label>
                            <textarea id="description" name="description" class="form-textarea" rows="3"
                                      placeholder="Describe your experience, expertise, and professional background..."><?php echo htmlspecialchars($lawyer['description'] ?? ''); ?></textarea>
                            <small class="form-hint">This will be displayed on your public profile</small>
                        </div>
                    </div>
                </div>

                <!-- Legal Specializations Card - Large -->
                <div class="form-card specializations-card large-card">
                    <div class="card-header">
                        <h3><i class="fas fa-gavel"></i> Legal Specializations</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($all_practice_areas)): ?>
                            <div class="specializations-scrollable-container">
                                <!-- Specializations Scrollable Area -->
                                <div class="specializations-scroll-area">
                                    <?php foreach ($all_practice_areas as $area): ?>
                                        <div class="specialization-item">
                                            <input type="checkbox" 
                                                   id="specialization_<?php echo $area['id']; ?>" 
                                                   name="specializations[]" 
                                                   value="<?php echo $area['id']; ?>"
                                                   <?php echo in_array($area['id'], $current_specializations) ? 'checked' : ''; ?>>
                                            <label for="specialization_<?php echo $area['id']; ?>">
                                                <span class="checkmark">
                                                    <i class="fas fa-check"></i>
                                                </span>
                                                <span class="specialization-name">
                                                    <?php echo htmlspecialchars($area['area_name']); ?>
                                                </span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Selected Count Display -->
                                <div class="selected-count">
                                    <i class="fas fa-check-circle"></i>
                                    <span id="selectedCount">0</span> specialization(s) selected
                                </div>
                            </div>
                            
                            <small class="form-hint">
                                <i class="fas fa-info-circle"></i> 
                                You must select at least one specialization. These will determine which consultations you can handle.
                            </small>
                        <?php else: ?>
                            <div class="alert alert-error">
                                <div class="alert-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="alert-content">
                                    <strong>No Practice Areas Available</strong>
                                    <p>Please contact administrator to add practice areas.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Profile
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        <?php endif; ?>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fas fa-lock"></i> Change Password</h3>
                <button type="button" class="modal-close" onclick="closePasswordModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form action="process_password_change.php" method="POST" id="passwordForm">
                <div class="modal-body">
                    <p style="margin: 0 0 20px 0; color: #6c757d;">
                        <i class="fas fa-info-circle"></i>
                        Enter your current password and choose a new secure password. All fields are required.
                    </p>
                    
                    <div class="form-group">
                        <label for="password_current">
                            <i class="fas fa-key"></i>
                            <span>Current Password</span>
                            <span class="required">*</span>
                        </label>
                        <input type="password" id="password_current" name="current_password" 
                               placeholder="Enter your current password" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_new">
                            <i class="fas fa-lock"></i>
                            <span>New Password</span>
                            <span class="required">*</span>
                        </label>
                        <input type="password" id="password_new" name="new_password" 
                               placeholder="Enter new password (min. 8 characters)" class="form-input" required>
                        <div id="password-strength-modal" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm">
                            <i class="fas fa-check-double"></i>
                            <span>Confirm New Password</span>
                            <span class="required">*</span>
                        </label>
                        <input type="password" id="password_confirm" name="confirm_new_password" 
                               placeholder="Confirm new password" class="form-input" required>
                        <small id="password-match-status-modal" class="form-hint">Passwords must match</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Remove Profile Picture Modal -->
    <div id="removePhotoModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-trash"></i> Remove Profile Picture</h3>
                <button type="button" class="modal-close" onclick="closeRemoveModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove your profile picture?</p>
                <p class="modal-subtitle">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="confirmRemovePhoto()">
                    <i class="fas fa-trash"></i> Remove
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeRemoveModal()">
                    Cancel
                </button>
            </div>
        </div>
    </div>
    
    <script>
        function updateSelectedCount() {
            const checkedBoxes = document.querySelectorAll('input[name="specializations[]"]:checked');
            document.getElementById('selectedCount').textContent = checkedBoxes.length;
        }
        
        // Initialize selected count
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
            
            // Add event listeners to all checkboxes
            const checkboxes = document.querySelectorAll('input[name="specializations[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });

            // Add hover effects for profile picture and buttons
            const photoContainer = document.querySelector('.photo-container');
            const chooseBtn = document.querySelector('.btn-choose');
            const removeBtn = document.querySelector('.btn-remove');

            // Remove the conflicting JavaScript hover effects - CSS handles this better
        });

        // Profile picture upload handling
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            console.log('=== PROFILE PICTURE UPLOAD DEBUG ===');
            console.log('File selected:', file.name, 'Size:', file.size, 'Type:', file.type);
            
            // Validate file
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (file.size > maxSize) {
                console.log('ERROR: File too large');
                alert('File size must be less than 5MB');
                e.target.value = '';
                return;
            }
            
            if (!allowedTypes.includes(file.type)) {
                console.log('ERROR: Invalid file type');
                alert('Only JPG, PNG, and GIF files are allowed');
                e.target.value = '';
                return;
            }
            
            console.log('File validation passed, starting upload...');
            // Upload file directly without showing preview first
            uploadProfilePicture(file);
        });
        
        function uploadProfilePicture(file) {
            console.log('=== UPLOAD FUNCTION CALLED ===');
            const statusDiv = document.getElementById('upload-status') || document.querySelector('.upload-status-horizontal');
            const formData = new FormData();
            formData.append('profile_picture', file);
            
            console.log('Status div found:', !!statusDiv);
            console.log('FormData created, starting fetch...');
            
            // Show progress
            if (statusDiv) {
                statusDiv.innerHTML = '<div style="color: #007bff;">📤 Uploading...</div>';
            }
            
            fetch('upload_profile_picture.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('=== UPLOAD RESPONSE ===');
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                return response.json();
            })
            .then(data => {
                console.log('=== UPLOAD DATA ===');
                console.log('Full response:', data);
                if (data.success) {
                    console.log('Upload successful!');
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div style="color: green;">✅ Profile picture updated successfully!</div>';
                    }
                    
                    // Update preview image with new URL (add timestamp to prevent caching)
                    const previewImg = document.getElementById('profile-preview');
                    console.log('Preview img element:', !!previewImg);
                    if (previewImg && data.url) {
                        const newUrl = data.url + '?t=' + Date.now();
                        console.log('Updating preview image from:', previewImg.src, 'to:', newUrl);
                        previewImg.src = newUrl;
                        
                        // Force image reload
                        previewImg.onload = function() {
                            console.log('✅ Preview image loaded successfully');
                        };
                        previewImg.onerror = function() {
                            console.error('❌ Failed to load preview image:', newUrl);
                        };
                    } else {
                        console.error('Preview image element not found or no URL provided');
                        console.log('previewImg:', previewImg, 'data.url:', data.url);
                    }
                    
                    // Enable remove button
                    const removeBtn = document.querySelector('.btn-remove');
                    console.log('Remove button found:', !!removeBtn);
                    if (removeBtn) {
                        removeBtn.disabled = false;
                        removeBtn.style.cursor = 'pointer';
                        removeBtn.style.background = '#6c757d';
                        removeBtn.style.color = 'white';
                        removeBtn.style.opacity = '1';
                        console.log('Remove button enabled');
                    }
                    
                    // Debug information
                    if (data.debug) {
                        console.log('Upload debug info:', data.debug);
                    }
                    
                    // Clear the file input after successful upload
                    document.getElementById('profile_picture').value = '';
                } else {
                    console.error('Upload failed:', data.message);
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div style="color: red;">❌ ' + (data.message || 'Upload failed') + '</div>';
                    }
                }
            })
            .catch(error => {
                console.error('=== UPLOAD ERROR ===');
                console.error('Upload error:', error);
                if (statusDiv) {
                    statusDiv.innerHTML = '<div style="color: red;">❌ Upload failed. Please try again.</div>';
                }
            });
        }
        
        function removeProfilePicture() {
            console.log('=== REMOVE PROFILE PICTURE DEBUG ===');
            const removeBtn = document.querySelector('.btn-remove');
            console.log('Remove button found:', !!removeBtn);
            console.log('Remove button disabled:', removeBtn ? removeBtn.disabled : 'N/A');
            
            if (!removeBtn || removeBtn.disabled) {
                console.log('Remove button is disabled or not found - exiting');
                return;
            }
            
            // Show the modal instead of confirm dialog
            showRemoveModal();
        }
        
        function showRemoveModal() {
            const modal = document.getElementById('removePhotoModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
                
                // Trigger animation after a small delay to ensure display is set
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            }
        }
        
        function closeRemoveModal() {
            const modal = document.getElementById('removePhotoModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto'; // Restore scrolling
                
                // Hide modal after animation completes
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300);
            }
        }
        
        function confirmRemovePhoto() {
            console.log('User confirmed profile picture removal');
            closeRemoveModal();
            
            const removeBtn = document.querySelector('.btn-remove');
            const statusDiv = document.getElementById('upload-status') || document.querySelector('.upload-status-horizontal');
            console.log('Status div found:', !!statusDiv);
            
            if (statusDiv) {
                statusDiv.innerHTML = '<div style="color: #007bff;">🗑️ Removing profile picture...</div>';
            }
            
            // Disable remove button immediately
            if (removeBtn) {
                removeBtn.disabled = true;
                removeBtn.style.cursor = 'not-allowed';
                removeBtn.style.background = '#e9ecef';
                removeBtn.style.color = '#adb5bd';
                removeBtn.style.opacity = '0.6';
            }
            
            // Clear the file input
            const fileInput = document.getElementById('profile_picture');
            if (fileInput) {
                fileInput.value = '';
            }
            
            console.log('Making API call to remove_profile_picture.php...');
            
            // Make actual API call to remove from server
            fetch('remove_profile_picture.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove'
                })
            })
            .then(response => {
                console.log('=== REMOVE RESPONSE ===');
                console.log('Response status:', response.status);
                console.log('Response ok:', response.ok);
                return response.json();
            })
            .then(data => {
                console.log('=== REMOVE DATA ===');
                console.log('Full response:', data);
                if (data.success) {
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div style="color: green;">✅ Profile picture removed successfully!</div>';
                    }
                    
                    // Update preview to default image
                    const previewImg = document.getElementById('profile-preview');
                    console.log('Preview img element:', !!previewImg);
                    console.log('Default URL:', data.defaultUrl);
                    if (previewImg && data.defaultUrl) {
                        console.log('Updating preview to default image:', data.defaultUrl);
                        previewImg.src = data.defaultUrl;
                    }
                    
                    // Clear the file input
                    const fileInput = document.getElementById('profile_picture');
                    if (fileInput) {
                        fileInput.value = '';
                    }
                } else {
                    console.error('Remove failed:', data.message);
                    if (statusDiv) {
                        statusDiv.innerHTML = '<div style="color: red;">❌ ' + (data.message || 'Failed to remove profile picture') + '</div>';
                    }
                    
                    // Re-enable remove button on failure
                    if (removeBtn) {
                        removeBtn.disabled = false;
                        removeBtn.style.cursor = 'pointer';
                        removeBtn.style.background = '#6c757d';
                        removeBtn.style.color = 'white';
                        removeBtn.style.opacity = '1';
                    }
                }
            })
            .catch(error => {
                console.error('=== REMOVE ERROR ===');
                console.error('Remove API error:', error);
                if (statusDiv) {
                    statusDiv.innerHTML = '<div style="color: red;">❌ Network error. Please try again.</div>';
                }
                
                // Re-enable remove button on error
                if (removeBtn) {
                    removeBtn.disabled = false;
                    removeBtn.style.cursor = 'pointer';
                    removeBtn.style.background = '#6c757d';
                    removeBtn.style.color = 'white';
                    removeBtn.style.opacity = '1';
                }
            });
        }
        
        // Password change functionality - removed toggle, fields always visible
        // Password fields in the modal are always required
        
        // Open password modal
        function openPasswordModal() {
            const modal = document.getElementById('passwordModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
                
                // Clear form fields when opening
                document.getElementById('passwordForm').reset();
                document.getElementById('password-strength-modal').innerHTML = '';
                document.getElementById('password-match-status-modal').textContent = 'Passwords must match';
                document.getElementById('password-match-status-modal').style.color = '#6c757d';
            }
        }
        
        // Close password modal
        function closePasswordModal() {
            const modal = document.getElementById('passwordModal');
            if (modal) {
                modal.classList.remove('show');
                document.body.style.overflow = 'auto';
                
                setTimeout(() => {
                    modal.style.display = 'none';
                    // Clear form when closing
                    document.getElementById('passwordForm').reset();
                }, 300);
            }
        }
        
        // Password strength indicator for modal
        document.getElementById('password_new').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('password-strength-modal');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            let feedback = [];
            
            // Length check
            if (password.length >= 8) {
                strength += 1;
            } else {
                feedback.push('At least 8 characters');
            }
            
            // Uppercase check
            if (/[A-Z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Uppercase letter');
            }
            
            // Lowercase check
            if (/[a-z]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Lowercase letter');
            }
            
            // Number check
            if (/\d/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Number');
            }
            
            // Special character check
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
                strength += 1;
            } else {
                feedback.push('Special character');
            }
            
            // Display strength
            let strengthText = '';
            let strengthColor = '';
            
            if (strength <= 2) {
                strengthText = 'Weak';
                strengthColor = '#dc3545';
            } else if (strength <= 3) {
                strengthText = 'Fair';
                strengthColor = '#ffc107';
            } else if (strength <= 4) {
                strengthText = 'Good';
                strengthColor = '#28a745';
            } else {
                strengthText = 'Strong';
                strengthColor = '#28a745';
            }
            
            strengthDiv.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                    <div style="flex: 1; height: 4px; background: #e9ecef; border-radius: 2px;">
                        <div style="height: 100%; background: ${strengthColor}; width: ${(strength/5)*100}%; border-radius: 2px; transition: all 0.3s;"></div>
                    </div>
                    <span style="color: ${strengthColor}; font-weight: bold; font-size: 12px;">${strengthText}</span>
                </div>
                ${feedback.length > 0 ? `<small style="color: #6c757d;">Missing: ${feedback.join(', ')}</small>` : ''}
            `;
        });
        
        // Password confirmation check for modal
        document.getElementById('password_confirm').addEventListener('input', function() {
            const newPassword = document.getElementById('password_new').value;
            const confirmPassword = this.value;
            const statusDiv = document.getElementById('password-match-status-modal');
            
            if (confirmPassword === '') {
                statusDiv.textContent = 'Passwords must match';
                statusDiv.style.color = '#6c757d';
                return;
            }
            
            if (newPassword === confirmPassword) {
                statusDiv.textContent = '✓ Passwords match';
                statusDiv.style.color = '#28a745';
            } else {
                statusDiv.textContent = '✗ Passwords do not match';
                statusDiv.style.color = '#dc3545';
            }
        });
        
        // Profile form validation (no password validation)
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const specializations = document.querySelectorAll('input[name="specializations[]"]:checked');
            
            if (specializations.length === 0) {
                e.preventDefault();
                alert('Please select at least one legal specialization.');
                return false;
            }
            
            // Confirm before submitting
            if (!confirm('Are you sure you want to update your profile?')) {
                e.preventDefault();
                return false;
            }
            
            console.log('Profile form validation passed, submitting...');
        });
        
        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('password_current').value;
            const newPassword = document.getElementById('password_new').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            
            if (!currentPassword) {
                e.preventDefault();
                alert('Please enter your current password.');
                document.getElementById('password_current').focus();
                return false;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                document.getElementById('password_new').focus();
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                document.getElementById('password_confirm').focus();
                return false;
            }
            
            // Confirm before submitting
            if (!confirm('Are you sure you want to change your password?')) {
                e.preventDefault();
                return false;
            }
            
            console.log('Password change validation passed, submitting...');
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.startsWith('63')) {
                    value = value.substring(2);
                }
                if (value.length >= 10) {
                    value = value.substring(0, 10);
                    e.target.value = `(+63) ${value.substring(0, 3)} ${value.substring(3, 6)} ${value.substring(6)}`;
                }
            }
        });
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const removeModal = document.getElementById('removePhotoModal');
            const passwordModal = document.getElementById('passwordModal');
            
            if (e.target === removeModal) {
                closeRemoveModal();
            }
            if (e.target === passwordModal) {
                closePasswordModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRemoveModal();
                closePasswordModal();
            }
        });
    </script>
</body>
</html>
