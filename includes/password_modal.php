<?php
/**
 * Password Change Modal Component
 * Include this file in any page where you want the password change modal
 */
?>

<!-- Password Change Modal -->
<div id="passwordModal" class="password-modal">
    <div class="password-modal-content">
        <div class="password-modal-header">
            <h2>Change Password</h2>
            <p>Please update your password for security</p>
        </div>
        
        <form id="passwordForm">
            <div id="passwordMessage"></div>
            
            <div class="password-form-group">
                <label for="currentPassword">Current Password</label>
                <input type="password" id="currentPassword" name="current_password" required>
            </div>
            
            <div class="password-form-group">
                <label for="newPassword">New Password</label>
                <input type="password" id="newPassword" name="new_password" required minlength="6">
                <div id="passwordStrength" class="password-strength"></div>
            </div>
            
            <div class="password-form-group">
                <label for="confirmPassword">Confirm New Password</label>
                <input type="password" id="confirmPassword" name="confirm_password" required minlength="6">
            </div>
            
            <div class="password-form-actions">
                <button type="button" class="password-btn password-btn-secondary" onclick="closePasswordModal()">Cancel</button>
                <button type="submit" class="password-btn password-btn-primary">Change Password</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Password Change Modal Styles */
.password-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
}

.password-modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mandatory password change styling */
.password-modal.mandatory {
    background-color: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(8px);
}

.password-modal.mandatory .password-modal-content {
    border: 3px solid #dc3545;
    box-shadow: 0 20px 40px rgba(220, 53, 69, 0.3);
}

.password-modal-content {
    background: var(--white, #FFFFFF);
    border-radius: 12px;
    padding: 30px;
    width: 90%;
    max-width: 450px;
    box-shadow: 0 20px 40px rgba(11, 29, 58, 0.2);
    transform: scale(0.9);
    opacity: 0;
    transition: all 0.3s ease;
}

.password-modal.show .password-modal-content {
    transform: scale(1);
    opacity: 1;
}

.password-modal-header {
    text-align: center;
    margin-bottom: 25px;
}

.password-modal-header h2 {
    color: var(--navy, #0B1D3A);
    font-family: var(--font-serif, 'Playfair Display', serif);
    font-size: 1.8rem;
    margin-bottom: 8px;
}

.password-modal-header p {
    color: var(--text-light, #6C757D);
    font-size: 0.95rem;
}

.password-form-group {
    margin-bottom: 20px;
}

.password-form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--navy, #0B1D3A);
    font-weight: 500;
}

.password-form-group input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
    box-sizing: border-box;
}

.password-form-group input:focus {
    outline: none;
    border-color: var(--gold, #C5A253);
    box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1);
}

.password-strength {
    font-size: 0.85rem;
    margin-top: 8px;
    padding: 5px 0;
    min-height: 20px;
}

.password-strength.weak { color: #dc3545; }
.password-strength.medium { color: #ffc107; }
.password-strength.strong { color: #28a745; }

.password-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.password-btn {
    flex: 1;
    padding: 12px 20px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.password-btn-primary {
    background: var(--navy, #0B1D3A);
    color: var(--white, #FFFFFF);
}

.password-btn-primary:hover {
    background: var(--navy-light, #1A2B4A);
    transform: translateY(-1px);
}

.password-btn-secondary {
    background: #f8f9fa;
    color: var(--text-dark, #212529);
    border: 1px solid #dee2e6;
}

.password-btn-secondary:hover {
    background: #e9ecef;
}

.password-error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.password-success {
    background: #d1edff;
    color: #0c5460;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 20px;
    font-size: 0.9rem;
}

.password-loading {
    opacity: 0.7;
    pointer-events: none;
}

body.modal-open {
    overflow: hidden;
}

/* Responsive design */
@media (max-width: 600px) {
    .password-modal-content {
        width: 95%;
        padding: 20px;
    }
    
    .password-form-actions {
        flex-direction: column;
    }
}
</style>

<script>
// Password Modal Functions
function showPasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) {
        document.body.classList.add('modal-open');
        modal.classList.add('show');
    } else {
        console.error('Password modal not found');
    }
}

function closePasswordModal() {
    const modal = document.getElementById('passwordModal');
    if (modal) {
        document.body.classList.remove('modal-open');
        modal.classList.remove('show');
        
        // Clear form and messages
        const form = document.getElementById('passwordForm');
        const messageDiv = document.getElementById('passwordMessage');
        const strengthDiv = document.getElementById('passwordStrength');
        
        if (form) form.reset();
        if (messageDiv) messageDiv.innerHTML = '';
        if (strengthDiv) {
            strengthDiv.textContent = '';
            strengthDiv.className = 'password-strength';
        }
        
        // Reset input border colors
        const inputs = modal.querySelectorAll('input');
        inputs.forEach(input => {
            input.style.borderColor = '#e9ecef';
        });
    } else {
        console.error('Password modal not found');
    }
}

// Initialize password modal functionality
document.addEventListener('DOMContentLoaded', function() {
    // Check if modal elements exist before adding event listeners
    const passwordForm = document.getElementById('passwordForm');
    const newPasswordInput = document.getElementById('newPassword');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const passwordModal = document.getElementById('passwordModal');
    
    if (!passwordForm || !newPasswordInput || !confirmPasswordInput || !passwordModal) {
        console.error('Password modal elements not found');
        return;
    }
    
    // Check if we need to show modal automatically for temporary passwords
    // This will be triggered by PHP if temporary_password = 'temporary'
    if (window.showPasswordModalOnLoad) {
        setTimeout(function() {
            showPasswordModal();
            
            // Add mandatory styling
            passwordModal.classList.add('mandatory');
            
            // Add mandatory change styling and messaging
            const modalHeader = passwordModal.querySelector('.password-modal-header');
            if (modalHeader) {
                const existingP = modalHeader.querySelector('p');
                if (existingP) {
                    existingP.innerHTML = '<strong style="color: #dc3545;">⚠️ You must change your temporary password to continue using the system.</strong>';
                }
            }
            
            // Hide cancel button for mandatory password change
            const cancelBtn = passwordModal.querySelector('.password-btn-secondary');
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
            
            // Override close function temporarily
            window.originalClosePasswordModal = window.closePasswordModal;
            window.closePasswordModal = function() {
                // Don't allow closing if it's a mandatory change
                if (window.showPasswordModalOnLoad) {
                    return false;
                }
                window.originalClosePasswordModal();
            };
        }, 500);
    }
    
    // Handle form submission
    passwordForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        const messageDiv = document.getElementById('passwordMessage');
        
        // Clear previous messages
        messageDiv.innerHTML = '';
        
        // Get form values
        const currentPassword = formData.get('current_password');
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        // Client-side validation
        if (newPassword.length < 6) {
            messageDiv.innerHTML = '<div class="password-error">New password must be at least 6 characters long</div>';
            return;
        }
        
        if (newPassword !== confirmPassword) {
            messageDiv.innerHTML = '<div class="password-error">New passwords do not match</div>';
            return;
        }
        
        // Show loading state
        form.classList.add('password-loading');
        
        try {
            // Determine the correct API path based on current location
            const apiPath = window.location.pathname.includes('/lawyer/') ? '../api/change_password.php' : 'api/change_password.php';
            
            const response = await fetch(apiPath, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    confirm_password: confirmPassword
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                messageDiv.innerHTML = '<div class="password-success">' + result.message + '</div>';
                
                // If this was a mandatory password change, restore normal modal behavior
                if (window.showPasswordModalOnLoad) {
                    window.showPasswordModalOnLoad = false;
                    
                    // Remove mandatory styling
                    passwordModal.classList.remove('mandatory');
                    
                    // Restore original close function
                    if (window.originalClosePasswordModal) {
                        window.closePasswordModal = window.originalClosePasswordModal;
                    }
                    
                    // Show cancel button again
                    const cancelBtn = passwordModal.querySelector('.password-btn-secondary');
                    if (cancelBtn) {
                        cancelBtn.style.display = 'flex';
                    }
                    
                    // Remove temporary password notice if it exists
                    const tempNotice = document.querySelector('.temporary-password-notice');
                    if (tempNotice) {
                        tempNotice.style.display = 'none';
                    }
                }
                
                // Close modal after 2 seconds
                setTimeout(() => {
                    closePasswordModal();
                    form.reset();
                    
                    // Reload page if it was a mandatory change to refresh the UI
                    if (window.showPasswordModalOnLoad === false) {
                        window.location.reload();
                    }
                }, 2000);
            } else {
                messageDiv.innerHTML = '<div class="password-error">' + result.message + '</div>';
            }
        } catch (error) {
            messageDiv.innerHTML = '<div class="password-error">An error occurred. Please try again.</div>';
            console.error('Password change error:', error);
        } finally {
            form.classList.remove('password-loading');
        }
    });

    // Password strength checker
    newPasswordInput.addEventListener('input', function(e) {
        const password = e.target.value;
        const strengthDiv = document.getElementById('passwordStrength');
        
        if (password.length === 0) {
            strengthDiv.textContent = '';
            strengthDiv.className = 'password-strength';
            return;
        }
        
        let strength = 0;
        let feedback = [];
        
        // Length check
        if (password.length >= 8) strength++;
        else feedback.push('at least 8 characters');
        
        // Uppercase check
        if (/[A-Z]/.test(password)) strength++;
        else feedback.push('uppercase letter');
        
        // Lowercase check
        if (/[a-z]/.test(password)) strength++;
        else feedback.push('lowercase letter');
        
        // Number check
        if (/\d/.test(password)) strength++;
        else feedback.push('number');
        
        // Special character check
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
        else feedback.push('special character');
        
        if (strength < 2) {
            strengthDiv.textContent = 'Weak password. Add: ' + feedback.slice(0, 2).join(', ');
            strengthDiv.className = 'password-strength weak';
        } else if (strength < 4) {
            strengthDiv.textContent = 'Medium strength. Consider adding: ' + feedback.slice(0, 1).join(', ');
            strengthDiv.className = 'password-strength medium';
        } else {
            strengthDiv.textContent = 'Strong password!';
            strengthDiv.className = 'password-strength strong';
        }
    });

    // Real-time password confirmation validation
    confirmPasswordInput.addEventListener('input', function(e) {
        const newPassword = newPasswordInput.value;
        const confirmPassword = e.target.value;
        
        if (confirmPassword.length > 0) {
            if (newPassword === confirmPassword) {
                e.target.style.borderColor = '#28a745';
            } else {
                e.target.style.borderColor = '#dc3545';
            }
        } else {
            e.target.style.borderColor = '#e9ecef';
        }
    });

    // Close modal when clicking outside
    passwordModal.addEventListener('click', function(e) {
        // Don't close if it's a mandatory password change
        if (window.showPasswordModalOnLoad) {
            return;
        }
        
        if (e.target === this) {
            closePasswordModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        // Don't close if it's a mandatory password change
        if (window.showPasswordModalOnLoad) {
            return;
        }
        
        if (e.key === 'Escape') {
            closePasswordModal();
        }
    });
});
</script>