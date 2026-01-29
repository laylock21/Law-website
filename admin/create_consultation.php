<?php
/**
 * Admin Panel - Create Consultation
 * Allows admin to manually create consultation requests
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Get active lawyers and practice areas for the form
try {
    $pdo = getDBConnection();
    
    // Get active lawyers with their profiles
    $lawyers_stmt = $pdo->query("
        SELECT u.user_id, lp.lp_fullname, lp.lawyer_prefix 
        FROM users u
        INNER JOIN lawyer_profile lp ON u.user_id = lp.lawyer_id
        WHERE u.role = 'lawyer' AND u.is_active = 1
        ORDER BY lp.lp_fullname ASC
    ");
    $lawyers = $lawyers_stmt->fetchAll();
    
    // Get active practice areas
    $areas_stmt = $pdo->query("
        SELECT pa_id, area_name 
        FROM practice_areas 
        WHERE is_active = 1 
        ORDER BY area_name ASC
    ");
    $practice_areas = $areas_stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $lawyers = [];
    $practice_areas = [];
}

// Set page-specific variables for the header
$page_title = "Create Consultation";
$active_page = "create_consultation";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Consultation | MD Law Firm</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .create-consultation-container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 32px;
            margin-bottom: 24px;
        }
        
        .form-section {
            margin-bottom: 24px;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #3a3a3a;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-section-title i {
            color: #C5A253;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-grid.single-column {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #3a3a3a;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
            margin-left: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #C5A253;
            box-shadow: 0 0 0 3px rgba(197, 162, 83, 0.1);
        }
        
        .form-control:invalid:not(:placeholder-shown) {
            border-color: #dc3545;
        }
        
        .form-control:invalid:focus:not(:placeholder-shown) {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        .form-control.touched.invalid {
            border-color: #dc3545;
        }
        
        .form-control.touched.invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 2px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #C5A253, #d4b36a);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 162, 83, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
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
        
        .alert i {
            font-size: 20px;
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-card {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container create-consultation-container">
            
            <div class="form-card">
                <h2 style="margin: 0 0 24px 0; color: #3a3a3a; font-size: 24px;">
                    <i class="fas fa-plus-circle" style="color: #C5A253;"></i>
                    Create New Consultation
                </h2>
                
                <form id="createConsultationForm" method="POST" novalidate>
                    <!-- Client Information -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-user"></i>
                            Client Information
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fullName">
                                    Full Name
                                    <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="fullName" 
                                       name="fullName" 
                                       class="form-control" 
                                       placeholder="Enter client's full name"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="phone">
                                    Phone Number
                                    <span class="required">*</span>
                                </label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control" 
                                       placeholder="09123456789"
                                       pattern="[0-9]{11}"
                                       maxlength="11"
                                       required>
                                <small style="color: #6c757d; font-size: 12px; margin-top: 4px; display: block;">
                                    11 digits (e.g., 09123456789)
                                </small>
                            </div>
                        </div>
                        <div class="form-grid single-column" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="email">
                                    Email Address
                                    <span class="required">*</span>
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       placeholder="client@example.com"
                                       required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Case Details -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-file-alt"></i>
                            Case Details
                        </div>
                        <div class="form-grid single-column">
                            <div class="form-group">
                                <label for="caseDescription">
                                    Case Description
                                    <span class="required">*</span>
                                </label>
                                <textarea id="caseDescription" 
                                          name="caseDescription" 
                                          class="form-control" 
                                          placeholder="Describe the case details..."
                                          required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointment Details -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Appointment Details
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="practiceArea">
                                    Practice Area
                                    <span class="required">*</span>
                                </label>
                                <select id="practiceArea" 
                                        name="practiceArea" 
                                        class="form-control"
                                        required>
                                    <option value="">Select practice area</option>
                                    <?php foreach ($practice_areas as $area): ?>
                                        <option value="<?php echo htmlspecialchars($area['area_name']); ?>">
                                            <?php echo htmlspecialchars($area['area_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="lawyer">
                                    Assigned Lawyer
                                    <span class="required">*</span>
                                </label>
                                <select id="lawyer" 
                                        name="lawyer" 
                                        class="form-control"
                                        required
                                        disabled>
                                    <option value="">Select practice area first</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="consultationDate">
                                    Consultation Date
                                    <span class="required">*</span>
                                </label>
                                <select id="consultationDate" 
                                        name="consultationDate" 
                                        class="form-control"
                                        required
                                        disabled>
                                    <option value="">Select lawyer first</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="consultationTime">
                                    Consultation Time
                                    <span class="required">*</span>
                                </label>
                                <select id="consultationTime" 
                                        name="consultationTime" 
                                        class="form-control"
                                        required
                                        disabled>
                                    <option value="">Select date first</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="consultations.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i>
                            Create Consultation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Toast notification function
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
        
        // Add touched class when field is interacted with
        document.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('blur', function() {
                this.classList.add('touched');
            });
            
            field.addEventListener('input', function() {
                if (this.classList.contains('touched')) {
                    if (this.validity.valid) {
                        this.classList.remove('invalid');
                    } else {
                        this.classList.add('invalid');
                    }
                }
            });
        });
        
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 11);
        });
        
        // Practice Area change - Load lawyers by specialization
        document.getElementById('practiceArea').addEventListener('change', async function() {
            const practiceArea = this.value;
            const lawyerSelect = document.getElementById('lawyer');
            const dateSelect = document.getElementById('consultationDate');
            const timeSelect = document.getElementById('consultationTime');
            
            // Reset dependent fields
            lawyerSelect.innerHTML = '<option value="">Loading lawyers...</option>';
            lawyerSelect.disabled = true;
            dateSelect.innerHTML = '<option value="">Select lawyer first</option>';
            dateSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Select date first</option>';
            timeSelect.disabled = true;
            
            if (!practiceArea) {
                lawyerSelect.innerHTML = '<option value="">Select practice area first</option>';
                return;
            }
            
            try {
                const response = await fetch(`../api/get_lawyers_by_specialization.php?specialization=${encodeURIComponent(practiceArea)}`);
                const data = await response.json();
                
                if (data.success && data.lawyers.length > 0) {
                    lawyerSelect.innerHTML = '<option value="">Select a lawyer</option>';
                    data.lawyers.forEach(lawyer => {
                        const option = document.createElement('option');
                        option.value = lawyer.id;
                        option.textContent = lawyer.name;
                        option.setAttribute('data-name', lawyer.full_name);
                        lawyerSelect.appendChild(option);
                    });
                    lawyerSelect.disabled = false;
                } else {
                    lawyerSelect.innerHTML = '<option value="">No lawyers available for this practice area</option>';
                }
            } catch (error) {
                console.error('Error loading lawyers:', error);
                lawyerSelect.innerHTML = '<option value="">Error loading lawyers</option>';
            }
        });
        
        // Lawyer change - Load available dates
        document.getElementById('lawyer').addEventListener('change', async function() {
            const lawyerId = this.value;
            const dateSelect = document.getElementById('consultationDate');
            const timeSelect = document.getElementById('consultationTime');
            
            // Reset dependent fields
            dateSelect.innerHTML = '<option value="">Loading dates...</option>';
            dateSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Select date first</option>';
            timeSelect.disabled = true;
            
            if (!lawyerId) {
                dateSelect.innerHTML = '<option value="">Select lawyer first</option>';
                return;
            }
            
            try {
                // Calculate 3 months from today
                const today = new Date();
                const endDate = new Date();
                endDate.setMonth(endDate.getMonth() + 3);
                
                const startDateStr = today.toISOString().split('T')[0];
                const endDateStr = endDate.toISOString().split('T')[0];
                
                const response = await fetch(`../api/get_lawyer_availability.php?lawyer_id=${lawyerId}&start_date=${startDateStr}&end_date=${endDateStr}`);
                const data = await response.json();
                
                if (data.success && data.available_dates && data.available_dates.length > 0) {
                    dateSelect.innerHTML = '<option value="">Select a date</option>';
                    
                    // Use detailed_availability if available, otherwise use available_dates
                    const dates = data.detailed_availability || data.available_dates.map(date => ({date: date}));
                    
                    dates.forEach(dateInfo => {
                        const dateValue = typeof dateInfo === 'string' ? dateInfo : dateInfo.date;
                        const dateObj = new Date(dateValue + 'T00:00:00');
                        const formattedDate = dateObj.toLocaleDateString('en-US', { 
                            weekday: 'short', 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                        
                        const option = document.createElement('option');
                        option.value = dateValue;
                        option.textContent = formattedDate;
                        
                        // Add slots info if available
                        if (dateInfo.max_appointments && dateInfo.booked !== undefined) {
                            const slotsLeft = dateInfo.max_appointments - dateInfo.booked;
                            option.textContent += ` (${slotsLeft} slot${slotsLeft !== 1 ? 's' : ''} left)`;
                        }
                        
                        dateSelect.appendChild(option);
                    });
                    dateSelect.disabled = false;
                } else {
                    dateSelect.innerHTML = '<option value="">No available dates in the next 3 months</option>';
                }
            } catch (error) {
                console.error('Error loading dates:', error);
                dateSelect.innerHTML = '<option value="">Error loading dates</option>';
            }
        });
        
        // Date change - Load available time slots
        document.getElementById('consultationDate').addEventListener('change', async function() {
            const lawyerId = document.getElementById('lawyer').value;
            const selectedDate = this.value;
            const timeSelect = document.getElementById('consultationTime');
            
            timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
            timeSelect.disabled = true;
            
            if (!selectedDate) {
                timeSelect.innerHTML = '<option value="">Select date first</option>';
                return;
            }
            
            try {
                const response = await fetch(`../api/get_time_slots.php?lawyer_id=${lawyerId}&date=${selectedDate}`);
                const data = await response.json();
                
                if (data.success && data.time_slots && data.time_slots.length > 0) {
                    timeSelect.innerHTML = '<option value="">Select a time</option>';
                    data.time_slots.forEach(slot => {
                        const option = document.createElement('option');
                        option.value = slot.time_24h || slot.time;
                        option.textContent = slot.display || slot.time_formatted || slot.time;
                        
                        if (!slot.available) {
                            option.disabled = true;
                            option.textContent += ' (Booked)';
                        }
                        
                        timeSelect.appendChild(option);
                    });
                    timeSelect.disabled = false;
                } else if (data.blocked) {
                    timeSelect.innerHTML = '<option value="">Date is blocked</option>';
                } else {
                    timeSelect.innerHTML = '<option value="">No time slots available</option>';
                }
            } catch (error) {
                console.error('Error loading time slots:', error);
                timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
            }
        });
        
        // Form submission
        document.getElementById('createConsultationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Validate all fields
            let isValid = true;
            this.querySelectorAll('.form-control[required]').forEach(field => {
                field.classList.add('touched');
                if (!field.validity.valid) {
                    field.classList.add('invalid');
                    isValid = false;
                } else {
                    field.classList.remove('invalid');
                }
            });
            
            if (!isValid) {
                showToast('Please fill in all required fields correctly.', 'error', 4000);
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
            // Show creating toast
            showToast('Creating consultation...', 'info', 0);
            
            const formData = new FormData(this);
            const lawyerSelect = document.getElementById('lawyer');
            const selectedOption = lawyerSelect.options[lawyerSelect.selectedIndex];
            formData.append('lawyerName', selectedOption.getAttribute('data-name'));
            
            try {
                const response = await fetch('../api/admin/create_consultation.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showToast('Consultation created successfully!', 'success', 2000);
                    setTimeout(() => {
                        window.location.href = 'consultations.php';
                    }, 1500);
                } else {
                    showToast('Error: ' + result.message, 'error', 5000);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error', 4000);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>
