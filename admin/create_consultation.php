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
                        <div class="form-grid" style="margin-top: 20px;">
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
                                <label for="lawyer">
                                    Assigned Lawyer
                                    <span class="required">*</span>
                                </label>
                                <select id="lawyer" 
                                        name="lawyer" 
                                        class="form-control"
                                        required>
                                    <option value="">Select a lawyer</option>
                                    <?php foreach ($lawyers as $lawyer): ?>
                                        <option value="<?php echo $lawyer['user_id']; ?>" 
                                                data-name="<?php echo htmlspecialchars($lawyer['lp_fullname']); ?>">
                                            <?php 
                                            $prefix = $lawyer['lawyer_prefix'] ? $lawyer['lawyer_prefix'] . ' ' : '';
                                            echo htmlspecialchars($prefix . $lawyer['lp_fullname']); 
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="consultationDate">
                                    Consultation Date
                                    <span class="required">*</span>
                                </label>
                                <input type="date" 
                                       id="consultationDate" 
                                       name="consultationDate" 
                                       class="form-control"
                                       min="<?php echo date('Y-m-d'); ?>"
                                       required>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top: 20px;">
                            <div class="form-group">
                                <label for="consultationTime">
                                    Consultation Time
                                    <span class="required">*</span>
                                </label>
                                <input type="time" 
                                       id="consultationTime" 
                                       name="consultationTime" 
                                       class="form-control"
                                       required>
                            </div>
                            <div class="form-group">
                                <label for="status">
                                    Initial Status
                                </label>
                                <select id="status" 
                                        name="status" 
                                        class="form-control">
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
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
                alert('Please fill in all required fields correctly.');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            
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
                    // Show success message and redirect
                    alert('Consultation created successfully!');
                    window.location.href = 'consultations.php';
                } else {
                    alert('Error: ' + result.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                alert('An error occurred. Please try again.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    </script>
</body>
</html>
