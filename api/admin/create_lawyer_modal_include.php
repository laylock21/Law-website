<!-- Create Lawyer Modal -->
<div class="modal fade" id="createLawyerModal" tabindex="-1" aria-labelledby="createLawyerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width: 700px; margin: 1.75rem auto; display: flex; align-items: center; min-height: calc(100% - 3.5rem);">
        <div class="modal-content create-lawyer-modal" style="max-height: 90vh; width: 100%;">
            <div class="modal-header" style="flex-shrink: 0;">
                <h5 class="modal-title" id="createLawyerModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Create New Lawyer
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="overflow-y: auto; max-height: calc(90vh - 180px);">
                <!-- Progress Bar -->
                <div class="progress-container" style="margin-bottom: 32px;">
                    <div class="progress-bar-wrapper" style="position: relative; height: 60px; display: flex; align-items: center;">
                        <!-- Progress Bar Background -->
                        <div style="position: absolute; width: 100%; height: 6px; background: #e0e0e0; border-radius: 3px; top: 50%; transform: translateY(-50%);"></div>
                        
                        <!-- Progress Bar Fill -->
                        <div id="progressBarFill" style="position: absolute; height: 6px; background: linear-gradient(90deg, #c5a253 0%, #d4b36a 100%); border-radius: 3px; top: 50%; transform: translateY(-50%); width: 0%; transition: width 0.4s ease;"></div>
                        
                        <!-- Progress Circle -->
                        <div id="progressCircle" style="position: absolute; width: 50px; height: 50px; background: white; border: 4px solid #c5a253; border-radius: 50%; display: flex; align-items: center; justify-content: center; left: 0%; transform: translateX(-50%); transition: left 0.4s ease; box-shadow: 0 4px 12px rgba(197, 162, 83, 0.3); z-index: 2;">
                            <i class="fas fa-check" style="color: #c5a253; font-size: 20px; display: none;" id="progressCheck"></i>
                            <span id="progressNumber" style="color: #c5a253; font-weight: 700; font-size: 18px;">1</span>
                        </div>
                    </div>
                    
                    <!-- Step Labels -->
                    <div style="display: flex; justify-content: space-between; margin-top: 8px; padding: 0 10px;">
                        <span style="font-size: 12px; font-weight: 600; color: #c5a253;">Basic Info</span>
                        <span style="font-size: 12px; font-weight: 600; color: #999;">Contact</span>
                        <span style="font-size: 12px; font-weight: 600; color: #999;">Password</span>
                        <span style="font-size: 12px; font-weight: 600; color: #999;">Specializations</span>
                    </div>
                </div>

                <form method="POST" id="createLawyerFormModal">
                    <input type="hidden" name="action" value="create_lawyer">
                    
                    <!-- Step 1: Username, First Name, Last Name -->
                    <div class="lawyer-form-step active" data-step="1">
                        <h3 class="step-title" style="font-size: 20px; margin-bottom: 20px;"><i class="fas fa-user-circle" style="color: #c5a253;"></i> Basic Information</h3>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="username_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">Username *</label>
                            <input type="text" id="username_modal" name="username" required style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="first_name_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">First Name *</label>
                            <input type="text" id="first_name_modal" name="first_name" required style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="last_name_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">Last Name *</label>
                            <input type="text" id="last_name_modal" name="last_name" required style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                        </div>
                    </div>

                    <!-- Step 2: Email, Phone, Description -->
                    <div class="lawyer-form-step" data-step="2">
                        <h3 class="step-title" style="font-size: 20px; margin-bottom: 20px;"><i class="fas fa-check-circle" style="color: #c5a253;"></i> Contact Information</h3>
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="email_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">Email *</label>
                            <input type="email" id="email_modal" name="email" required style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                            <small id="email_error" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">
                                <i class="fas fa-exclamation-circle"></i> Please enter a valid email address (e.g., example@email.com)
                            </small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="phone_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">Phone</label>
                            <input type="tel" id="phone_modal" name="phone" placeholder="(+63) 917 123 4567" style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px;">
                            <small id="phone_error" style="display: none; color: #dc3545; font-size: 12px; margin-top: 4px;">
                                <i class="fas fa-exclamation-circle"></i> Please enter a valid phone number (e.g., +63 917 123 4567)
                            </small>
                        </div>
                        
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="description_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000;">Description/Bio</label>
                            <textarea id="description_modal" name="description" rows="3" placeholder="Brief description of the lawyer's experience and expertise" style="width: 100%; padding: 12px 14px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                        </div>
                    </div>

                    <!-- Step 3: Password Setup -->
                    <div class="lawyer-form-step" data-step="3">
                        <h3 class="step-title" style="font-size: 20px; margin-bottom: 20px;"><i class="fas fa-lock" style="color: #c5a253;"></i> Password Setup</h3>
                        <div class="password-setup-container" style="display: flex; flex-direction: column; gap: 16px;">
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
                                <div id="custom-password-fields-modal" class="custom-password-fields" style="display: none; margin-top: 16px; padding-top: 16px; border-top: 2px solid rgba(197, 162, 83, 0.2);">
                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label for="custom_password_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000; font-size: 14px;">Password</label>
                                        <input type="password" id="custom_password_modal" name="custom_password" placeholder="Enter password" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; required">
                                        <small style="color: #666; display: block; margin-top: 4px; font-size: 12px;">Minimum 8 characters</small>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 12px;">
                                        <label for="confirm_password_modal" style="display: block; margin-bottom: 6px; font-weight: 600; color: #000; font-size: 14px;">Confirm Password</label>
                                        <input type="password" id="confirm_password_modal" name="confirm_password" placeholder="Confirm password" style="width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; required">
                                        <small id="password-match-modal" style="display: block; margin-top: 4px; font-size: 12px;"></small>
                                    </div>
                                    <div class="form-group">
                                        <label class="checkbox-label" style="display: flex !important; align-items: center !important; gap: 8px; cursor: pointer; font-size: 13px; color: #333; line-height: 1;">
                                            <input type="checkbox" id="force_change_modal" name="force_change" style="width: 16px; height: 16px; accent-color: #c5a253; flex-shrink: 0; margin: 0; vertical-align: middle;">
                                            <span style="line-height: 1.3; display: inline-block;">Force password change on first login</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Specializations -->
                    <div class="lawyer-form-step" data-step="4">
                        <h3 class="step-title" style="font-size: 20px; margin-bottom: 8px;"><i class="fas fa-briefcase" style="color: #c5a253;"></i> Legal Specializations</h3>
                        <p class="step-description" style="color: #666; margin-bottom: 16px; font-size: 14px;">Select at least one area of legal expertise</p>
                        <div class="specializations-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; max-height: 300px; overflow-y: auto; padding-right: 6px;">
                            <?php if (isset($practice_areas)): ?>
                                <?php foreach ($practice_areas as $area): ?>
                                    <div class="checkbox-item" style="background: white; border: 2px solid #e0e0e0; border-radius: 8px; padding: 14px; cursor: pointer; transition: all 0.3s ease;">
                                        <label for="spec_modal_<?php echo $area['id']; ?>" style="cursor: pointer; margin: 0; display: flex; align-items: center; gap: 12px; width: 100%;">
                                            <input type="checkbox" id="spec_modal_<?php echo $area['id']; ?>" name="specializations[]" value="<?php echo $area['id']; ?>" style="width: 20px; height: 20px; accent-color: #c5a253; flex-shrink: 0; cursor: pointer;">
                                            <span style="font-size: 15px; font-weight: 600; color: #000000 !important; flex: 1; line-height: 1.4;"><?php echo htmlspecialchars($area['area_name']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="lawyer-form-navigation" style="display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 2px solid rgba(197, 162, 83, 0.2);">
                        <button type="button" class="btn btn-secondary btn-lawyer-prev" id="lawyerPrevBtn" style="padding: 10px 24px; font-size: 14px; font-weight: 700; border-radius: 8px; background: white; color: #000; border: 2px solid #000; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary btn-lawyer-next" id="lawyerNextBtn" style="padding: 10px 24px; font-size: 14px; font-weight: 700; border-radius: 8px; background: #c5a253; color: white; border: none; margin-left: auto; display: inline-flex; align-items: center; gap: 6px;">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                        <button type="submit" class="btn btn-primary btn-lawyer-submit" id="lawyerSubmitBtn" style="display: none; padding: 10px 24px; font-size: 14px; font-weight: 700; border-radius: 8px; background: #c5a253; color: white; border: none; margin-left: auto;">
                            <i class="fas fa-check"></i> Create Lawyer Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Create Lawyer Modal Styles */
.create-lawyer-modal {
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    margin: 0 auto;
}

#createLawyerModal .modal-dialog {
    margin-left: auto !important;
    margin-right: auto !important;
}

.create-lawyer-modal .modal-header {
    background: linear-gradient(135deg, #050505 0%, #808080 100%) !important;
    color: white;
    border-bottom: none;
    padding: 24px 32px;
    position: relative;
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.create-lawyer-modal .modal-header::after {
    display: none;
}

.create-lawyer-modal .modal-header .btn-close {
    filter: invert(1);
    opacity: 0.9;
    transition: all 0.3s ease;
}

.create-lawyer-modal .modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.create-lawyer-modal .modal-title {
    font-weight: 700;
    font-size: 1.35rem;
    color: white;
    letter-spacing: 0.5px;
}

.create-lawyer-modal .modal-title i {
    color: #c5a253;
}

.create-lawyer-modal .modal-body {
    padding: 32px 40px;
    background: white;
}

/* Form Steps */
.lawyer-form-step {
    display: none;
    opacity: 0;
    transform: translateX(30px);
}

.lawyer-form-step.active {
    display: block;
    animation: slideInRight 0.5s ease forwards;
}

@keyframes slideInRight {
    from {
        opacity: 0;
        transform: translateX(30px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.step-title {
    font-size: 20px;
    font-weight: 700;
    color: white;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #3a3a3a 0%, #4a6fa5 100%);
    border-radius: 10px;
    position: relative;
    overflow: hidden;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 12px;
}

.step-description {
    color: #666666;
    margin-bottom: 16px;
    font-size: 14px;
    animation: fadeIn 0.6s ease 0.2s forwards;
    opacity: 0;
}

@keyframes fadeIn {
    to {
        opacity: 1;
    }
}

/* Password Setup */
.password-setup-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.password-option {
    background: white;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 16px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.password-option::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(197, 162, 83, 0.1), transparent);
    transition: left 0.5s ease;
}

.password-option:hover::before {
    left: 100%;
}

.password-option:has(input[type="radio"]:checked) {
    border-color: #c5a253;
    background: linear-gradient(135deg, #faf7f0 0%, white 100%);
    box-shadow: 0 4px 12px rgba(197, 162, 83, 0.15);
    transform: translateY(-2px);
}

.password-option-label {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    cursor: pointer;
    margin: 0;
}

.password-option-label input[type="radio"] {
    margin-top: 4px;
    width: 18px;
    height: 18px;
    accent-color: #c5a253;
    cursor: pointer;
}

.option-content {
    flex: 1;
}

.option-content strong {
    display: block;
    font-size: 15px;
    color: #000;
    margin-bottom: 4px;
}

.option-content small {
    display: block;
    color: #666;
    font-size: 13px;
    line-height: 1.4;
}

.custom-password-fields {
    display: none;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 2px solid rgba(197, 162, 83, 0.2);
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
    display: block;
}

.checkbox-label {
    display: flex !important;
    align-items: center !important;
    gap: 8px;
    cursor: pointer;
    font-size: 13px;
    color: #333;
    transition: color 0.3s ease;
    line-height: 1;
}

.checkbox-label:hover {
    color: #c5a253;
}

.checkbox-label input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: #c5a253;
    cursor: pointer;
    flex-shrink: 0;
    margin: 0;
    vertical-align: middle;
}

.checkbox-label span {
    line-height: 1.3;
    display: inline-block;
}

.checkbox-item {
    transition: all 0.3s ease;
    position: relative;
}

.checkbox-item:hover {
    border-color: #c5a253 !important;
    background: #faf7f0 !important;
    transform: translateX(4px);
    box-shadow: 0 2px 8px rgba(197, 162, 83, 0.2);
}

.checkbox-item label {
    position: relative;
    z-index: 1;
}

.checkbox-item label span {
    color: #000000 !important;
    font-weight: 600 !important;
    text-shadow: 0 0 1px rgba(0, 0, 0, 0.1);
}

.checkbox-item:has(input[type="checkbox"]:checked) {
    border-color: #c5a253 !important;
    background: linear-gradient(135deg, #faf7f0 0%, #fff9e6 100%) !important;
    box-shadow: 0 2px 12px rgba(197, 162, 83, 0.3);
}

.checkbox-item:has(input[type="checkbox"]:checked) label span {
    color: #c5a253 !important;
    font-weight: 700 !important;
}

/* Form Navigation */
.lawyer-form-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 2px solid rgba(197, 162, 83, 0.2);
}

.btn-lawyer-prev,
.btn-lawyer-next,
.btn-lawyer-submit {
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 700;
    border-radius: 8px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    position: relative;
    overflow: hidden;
}

.btn-lawyer-prev::before,
.btn-lawyer-next::before,
.btn-lawyer-submit::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.3);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-lawyer-prev:hover::before,
.btn-lawyer-next:hover::before,
.btn-lawyer-submit:hover::before {
    width: 300px;
    height: 300px;
}

.btn-lawyer-prev {
    background: white;
    color: #000;
    border: 2px solid #000;
}

.btn-lawyer-prev:hover {
    background: #000;
    color: white;
    transform: translateX(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lawyer-next,
.btn-lawyer-submit {
    background: #c5a253;
    color: white;
    border: none;
    margin-left: auto;
    box-shadow: 0 4px 12px rgba(197, 162, 83, 0.3);
}

.btn-lawyer-next:hover,
.btn-lawyer-submit:hover {
    background: #b08f42;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(176, 143, 66, 0.4);
}

.btn-lawyer-next:active,
.btn-lawyer-submit:active {
    transform: translateY(0);
}

/* Input Focus Animations */
input[type="text"]:focus,
input[type="email"]:focus,
input[type="tel"]:focus,
input[type="password"]:focus,
textarea:focus {
    border-color: #c5a253 !important;
    box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1) !important;
    transform: translateY(-1px);
    transition: all 0.3s ease;
}

/* Label Animation on Focus */
.form-group {
    position: relative;
}

.form-group label {
    transition: all 0.3s ease;
}

.form-group:has(input:focus) label,
.form-group:has(textarea:focus) label {
    color: #c5a253;
    transform: translateX(2px);
}

/* Progress Bar Pulse Animation */
#progressCircle {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 4px 12px rgba(197, 162, 83, 0.3);
    }
    50% {
        box-shadow: 0 4px 20px rgba(197, 162, 83, 0.5);
    }
}
</style>

<script>
// Create Lawyer Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('createLawyerModal');
    const openBtn = document.getElementById('openCreateLawyerModal');
    const prevBtn = document.getElementById('lawyerPrevBtn');
    const nextBtn = document.getElementById('lawyerNextBtn');
    const submitBtn = document.getElementById('lawyerSubmitBtn');
    let currentStep = 1;
    const totalSteps = 4;

    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', function() {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            currentStep = 1;
            showStep(currentStep);
        });
    }

    // Previous button
    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    // Next button
    nextBtn.addEventListener('click', function() {
        if (validateStep(currentStep)) {
            if (currentStep < totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }
    });

    function showStep(step) {
        // Hide all steps
        document.querySelectorAll('.lawyer-form-step').forEach(s => s.classList.remove('active'));

        // Show current step
        document.querySelector(`.lawyer-form-step[data-step="${step}"]`).classList.add('active');

        // Scroll modal body to top
        const modalBody = document.querySelector('#createLawyerModal .modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }

        // Update progress bar
        const progressPercent = ((step - 1) / (totalSteps - 1)) * 100;
        document.getElementById('progressBarFill').style.width = progressPercent + '%';
        document.getElementById('progressCircle').style.left = progressPercent + '%';
        
        // Update progress number/check
        const progressNumber = document.getElementById('progressNumber');
        const progressCheck = document.getElementById('progressCheck');
        
        if (step === totalSteps) {
            progressNumber.style.display = 'none';
            progressCheck.style.display = 'block';
        } else {
            progressNumber.style.display = 'block';
            progressNumber.textContent = step;
            progressCheck.style.display = 'none';
        }

        // Update buttons
        prevBtn.style.display = step === 1 ? 'none' : 'inline-flex';
        nextBtn.style.display = step === totalSteps ? 'none' : 'inline-flex';
        submitBtn.style.display = step === totalSteps ? 'inline-flex' : 'none';
    }

    function validateStep(step) {
        const currentStepEl = document.querySelector(`.lawyer-form-step[data-step="${step}"]`);
        const requiredInputs = currentStepEl.querySelectorAll('input[required]');
        
        // Hide all error messages first
        document.querySelectorAll('[id$="_error"]').forEach(err => err.style.display = 'none');
        
        for (let input of requiredInputs) {
            if (!input.value.trim()) {
                input.focus();
                input.style.borderColor = '#dc3545';
                setTimeout(() => input.style.borderColor = '#e0e0e0', 3000);
                return false;
            }
            
            // Email validation
            if (input.type === 'email') {
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(input.value)) {
                    input.focus();
                    input.style.borderColor = '#dc3545';
                    document.getElementById('email_error').style.display = 'block';
                    setTimeout(() => {
                        input.style.borderColor = '#e0e0e0';
                        document.getElementById('email_error').style.display = 'none';
                    }, 5000);
                    return false;
                }
            }
        }
        
        // Phone validation (optional field but validate format if filled)
        const phoneInput = document.getElementById('phone_modal');
        if (phoneInput && phoneInput.value.trim()) {
            // Philippine phone number pattern: +63 or 0 followed by 9-10 digits
            const phonePattern = /^(\+63|0)[0-9]{9,10}$/;
            const cleanPhone = phoneInput.value.replace(/[\s\-\(\)]/g, ''); // Remove spaces, dashes, parentheses
            
            if (!phonePattern.test(cleanPhone)) {
                phoneInput.focus();
                phoneInput.style.borderColor = '#dc3545';
                document.getElementById('phone_error').style.display = 'block';
                setTimeout(() => {
                    phoneInput.style.borderColor = '#e0e0e0';
                    document.getElementById('phone_error').style.display = 'none';
                }, 5000);
                return false;
            }
        }

        // Validate specializations on step 4
        if (step === 4) {
            const checked = document.querySelectorAll('input[name="specializations[]"]:checked');
            if (checked.length === 0) {
                alert('Please select at least one specialization');
                return false;
            }
        }

        return true;
    }

    // Password option toggle
    document.querySelectorAll('input[name="password_option"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const customFields = document.getElementById('custom-password-fields-modal');
            if (this.value === 'custom') {
                customFields.style.display = 'block';
            } else {
                customFields.style.display = 'none';
            }
        });
    });

    // Password match validation
    const confirmPassword = document.getElementById('confirm_password_modal');
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            const password = document.getElementById('custom_password_modal').value;
            const matchEl = document.getElementById('password-match-modal');
            if (this.value === password && this.value !== '') {
                matchEl.textContent = '✓ Passwords match';
                matchEl.style.color = '#28a745';
            } else if (this.value !== '') {
                matchEl.textContent = '✗ Passwords do not match';
                matchEl.style.color = '#dc3545';
            } else {
                matchEl.textContent = '';
            }
        });
    }
});
</script>
