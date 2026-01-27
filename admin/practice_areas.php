<?php
/**
 * Admin Practice Areas Management
 * Create, edit, and manage practice areas
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDBConnection();
        
        // Add new practice area
        if (isset($_POST['action']) && $_POST['action'] === 'add') {
            $area_name = trim($_POST['area_name']);
            $description = trim($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($area_name)) {
                throw new Exception('Practice area name is required');
            }
            
            $stmt = $pdo->prepare("INSERT INTO practice_areas (area_name, pa_description, is_active) VALUES (?, ?, ?)");
            $stmt->execute([$area_name, $description, $is_active]);
            
            $success_message = "Practice area added successfully!";
        }
        
        // Edit practice area
        if (isset($_POST['action']) && $_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $area_name = trim($_POST['area_name']);
            $description = trim($_POST['description']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($area_name)) {
                throw new Exception('Practice area name is required');
            }
            
            $stmt = $pdo->prepare("UPDATE practice_areas SET area_name = ?, pa_description = ?, is_active = ? WHERE pa_id = ?");
            $stmt->execute([$area_name, $description, $is_active, $id]);
            
            $success_message = "Practice area updated successfully!";
        }
        
        // Delete practice area
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            
            // Check if any lawyers are using this practice area
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM lawyer_specializations WHERE pa_id = ?");
            $check_stmt->execute([$id]);
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                throw new Exception("Cannot delete practice area. It is assigned to $count lawyer(s).");
            }
            
            $stmt = $pdo->prepare("DELETE FROM practice_areas WHERE pa_id = ?");
            $stmt->execute([$id]);
            
            $success_message = "Practice area deleted successfully!";
        }
        
        // Toggle active status
        if (isset($_POST['action']) && $_POST['action'] === 'toggle') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE practice_areas SET is_active = NOT is_active WHERE pa_id = ?");
            $stmt->execute([$id]);
            
            $success_message = "Practice area status updated!";
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all practice areas with lawyer count
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("
        SELECT 
            pa.*,
            COUNT(ls.lawyer_id) as lawyer_count
        FROM practice_areas pa
        LEFT JOIN lawyer_specializations ls ON pa.pa_id = ls.pa_id
        GROUP BY pa.pa_id
        ORDER BY pa.area_name ASC
    ");
    $practice_areas = $stmt->fetchAll();
    
    // Get statistics
    $total_areas = count($practice_areas);
    $active_areas = count(array_filter($practice_areas, fn($pa) => $pa['is_active'] == 1));
    $inactive_areas = $total_areas - $active_areas;
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $practice_areas = [];
}

// Set page-specific variables for the header
$page_title = "Practice Areas";
$active_page = "practice_areas";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Practice Areas | MD Law Firm</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="../includes/modal-container.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .practice-areas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .practice-area-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }
        
        .practice-area-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-color: var(--gold);
        }
        
        .practice-area-card.inactive {
            opacity: 0.6;
            background: #f8f9fa;
        }
        
        .area-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        
        .area-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--navy);
            margin: 0;
            font-family: var(--font-serif);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .area-description {
            color: var(--text-light);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 12px 0;
            min-height: 60px;
        }
        
        .area-stats {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 16px 0;
            padding: 12px;
            background: var(--gray-light);
            border-radius: 8px;
        }
        
        .area-stats i {
            color: var(--gold);
            font-size: 1.1rem;
        }
        
        .area-stats span {
            color: var(--navy);
            font-weight: 600;
        }
        
        .area-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        .area-actions button,
        .area-actions .btn-edit {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn-edit {
            background: var(--gold);
            color: var(--navy);
            text-decoration: none;
        }
        
        .btn-edit:hover {
            background: #B08F42;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-toggle {
            background: var(--info-color);
            color: white;
        }
        
        .btn-toggle:hover {
            background: #138496;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .add-area-card {
            background: linear-gradient(135deg, var(--gold) 0%, #B08F42 100%);
            border-radius: 12px;
            padding: 40px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px dashed rgba(255, 255, 255, 0.5);
            min-height: 250px;
        }
        
        .add-area-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(197, 162, 83, 0.3);
            border-color: white;
        }
        
        .add-area-card i {
            font-size: 3rem;
            color: white;
            margin-bottom: 16px;
        }
        
        .add-area-card h3 {
            color: white !important;
            margin: 0;
            font-size: 1.3rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--navy);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .close-modal:hover {
            background: var(--gray-light);
            color: var(--danger-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--navy);
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 16px;
            font-family: var(--font-sans);
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--gold);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-submit {
            background: var(--gold);
            color: var(--navy);
        }
        
        .btn-submit:hover {
            background: #B08F42;
            color: white;
        }
        
        .btn-cancel {
            background: var(--gray-light);
            color: var(--text-dark);
        }
        
        .btn-cancel:hover {
            background: #e2e6ea;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 768px) {
            .practice-areas-grid {
                grid-template-columns: 1fr;
            }
            
            .area-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 24px;
                width: 95%;
            }
        }
        
        /* Unified Modal Button Styles */
        .kiro-modal-footer .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: var(--font-sans);
        }
        
        .kiro-modal-footer .btn-primary {
            background: var(--gold);
            color: var(--navy);
        }
        
        .kiro-modal-footer .btn-primary:hover {
            background: #B08F42;
            color: white;
        }
        
        .kiro-modal-footer .btn-secondary {
            background: var(--gray-light);
            color: var(--text-dark);
        }
        
        .kiro-modal-footer .btn-secondary:hover {
            background: #e2e6ea;
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            
            <?php if ($success_message): ?>
                <div class="admin-alert admin-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="admin-alert admin-alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="admin-stats-grid dashboard-stats" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 30px;">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_areas; ?></div>
                    <div class="admin-stat-label">Total Areas</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $active_areas; ?></div>
                    <div class="admin-stat-label">Active</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $inactive_areas; ?></div>
                    <div class="admin-stat-label">Inactive</div>
                </div>
            </div>
            
            <!-- Practice Areas Grid -->
            <div class="practice-areas-grid">
                <!-- Add New Card -->
                <div class="add-area-card" onclick="openAddModal()">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Add New Practice Area</h3>
                </div>
                
                <!-- Practice Area Cards -->
                <?php foreach ($practice_areas as $area): ?>
                    <div class="practice-area-card <?php echo $area['is_active'] ? '' : 'inactive'; ?>">
                        <div class="area-header">
                            <h3 class="area-title"><?php echo htmlspecialchars($area['area_name']); ?></h3>
                            <span class="status-badge <?php echo $area['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $area['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        
                        <p class="area-description">
                            <?php echo htmlspecialchars($area['pa_description'] ?: 'No description provided'); ?>
                        </p>
                        
                        <div class="area-stats">
                            <i class="fas fa-user-tie"></i>
                            <span><?php echo $area['lawyer_count']; ?></span>
                            <span style="color: var(--text-light); font-weight: normal;">
                                <?php echo $area['lawyer_count'] == 1 ? 'Lawyer' : 'Lawyers'; ?>
                            </span>
                        </div>
                        
                        <div class="area-actions">
                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($area); ?>)'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-toggle" onclick="confirmToggle(<?php echo $area['pa_id']; ?>)">
                                <i class="fas fa-toggle-on"></i> Toggle
                            </button>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $area['pa_id']; ?>, '<?php echo htmlspecialchars(addslashes($area['area_name'])); ?>', <?php echo $area['lawyer_count']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
        </div>
    </main>

    <!-- Unified Modal Container -->
    <?php include '../includes/modal-container.php'; ?>

    <!-- Add/Edit Modal -->
    <div id="practiceAreaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Practice Area</h2>
                <button class="close-modal" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form id="practiceAreaForm" method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="areaId">
                
                <div class="form-group">
                    <label for="area_name">Practice Area Name *</label>
                    <input type="text" id="area_name" name="area_name" required placeholder="e.g., Criminal Defense">
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Brief description of this practice area..."></textarea>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active" style="margin: 0;">Active (visible to clients)</label>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Save Practice Area
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../includes/modal-container.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add Practice Area';
            document.getElementById('formAction').value = 'add';
            document.getElementById('areaId').value = '';
            document.getElementById('area_name').value = '';
            document.getElementById('description').value = '';
            document.getElementById('is_active').checked = true;
            document.getElementById('practiceAreaModal').classList.add('show');
        }
        
        function openEditModal(area) {
            document.getElementById('modalTitle').textContent = 'Edit Practice Area';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('areaId').value = area.pa_id;
            document.getElementById('area_name').value = area.area_name;
            document.getElementById('description').value = area.pa_description || '';
            document.getElementById('is_active').checked = area.is_active == 1;
            document.getElementById('practiceAreaModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('practiceAreaModal').classList.remove('show');
        }
        
        // Close modal when clicking outside
        document.getElementById('practiceAreaModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        // Confirmation functions using unified modal
        function confirmToggle(id) {
            ModalContainer.confirm(
                'Are you sure you want to toggle the status of this practice area?',
                function() {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                },
                'Toggle Status'
            );
        }
        
        function confirmDelete(id, name, lawyerCount) {
            if (lawyerCount > 0) {
                ModalContainer.showError(
                    `Cannot delete "${name}" because it is assigned to ${lawyerCount} lawyer(s). Please reassign or remove those lawyers first.`,
                    'Cannot Delete'
                );
                return;
            }
            
            ModalContainer.confirm(
                `Are you sure you want to delete "${name}"? This action cannot be undone.`,
                function() {
                    // Create and submit form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                },
                'Delete Practice Area'
            );
        }
    </script>
</body>
</html>
