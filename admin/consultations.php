<?php
/**
 * Admin Panel - View Consultations
 * Requires authentication (basic implementation)
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';

// Helper function to generate sortable column headers
function getSortableHeader($column, $display_name, $current_sort, $current_order) {
    $current_params = $_GET;
    $sort_icon = '';
    
    // Check if this column is actively sorted (not just default)
    $is_actively_sorted = isset($_GET['sort']) && $_GET['sort'] === $column;
    
    if ($is_actively_sorted) {
        if ($current_order === 'ASC') {
            // Currently ascending, next click goes to descending
            $current_params['sort'] = $column;
            $current_params['order'] = 'desc';
            $sort_icon = ' <i class="fas fa-sort-up"></i>';
        } else {
            // Currently descending, next click removes sort
            unset($current_params['sort']);
            unset($current_params['order']);
            $sort_icon = ' <i class="fas fa-sort-down"></i>';
        }
    } else {
        // Not currently sorted, next click goes to ascending
        $current_params['sort'] = $column;
        $current_params['order'] = 'asc';
        $sort_icon = ' <i class="fas fa-sort"></i>';
    }
    
    $query_string = http_build_query($current_params);
    
    return '<a href="?' . $query_string . '" class="sortable-header">' . $display_name . $sort_icon . '</a>';
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_consultations = $_POST['selected_consultations'] ?? [];
    
    if (!empty($selected_consultations) && in_array($action, ['confirm', 'complete', 'pending', 'cancelled'])) {
        try {
            $pdo = getDBConnection();
            require_once '../vendor/autoload.php'; // Load Composer dependencies (PHPMailer)
            require_once '../includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            
            $updated_count = 0;
            $email_count = 0;
            
            foreach ($selected_consultations as $consultation_id) {
                $consultation_id = (int)$consultation_id;
                
                // Get current status
                $check_stmt = $pdo->prepare("SELECT c_status FROM consultations WHERE c_id = ?");
                $check_stmt->execute([$consultation_id]);
                $current = $check_stmt->fetch();
                
                if ($current) {
                    $old_status = $current['c_status'];
                    
                    // Map action to status
                    $status_map = [
                        'pending' => 'pending',
                        'confirm' => 'confirmed',
                        'complete' => 'completed',
                        'cancelled' => 'cancelled'
                    ];
                    $new_status = $status_map[$action];
                    
                    // Only update if status is different
                    if ($old_status !== $new_status) {
                        // Update status and cancellation reason
                        if ($action === 'cancelled') {
                            // Set cancellation reason when cancelled
                            $update_stmt = $pdo->prepare("UPDATE consultations SET c_status = ?, c_cancellation_reason = ? WHERE c_id = ?");
                            $update_stmt->execute([$new_status, 'Cancelled by admin', $consultation_id]);
                        } else {
                            // Clear cancellation reason when changing to pending, confirmed, or completed
                            $update_stmt = $pdo->prepare("UPDATE consultations SET c_status = ?, c_cancellation_reason = NULL WHERE c_id = ?");
                            $update_stmt->execute([$new_status, $consultation_id]);
                        }
                        
                        if ($update_stmt->rowCount() > 0) {
                            $updated_count++;
                            
                            // Send email notification for confirm, complete, and cancel actions
                            $queued = false;
                            if ($new_status === 'confirmed') {
                                $queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
                            } elseif ($new_status === 'completed') {
                                $queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
                            } elseif ($new_status === 'cancelled') {
                                $queued = $emailNotification->notifyAppointmentCancelled($consultation_id, 'Cancelled by admin');
                            }
                            
                            if ($queued) {
                                $email_count++;
                            }
                        }
                    }
                }
            }
            
            if ($email_count > 0) {
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
                            console.log('Bulk emails sent successfully');
                        }
                    }).catch(error => {
                        console.log('Email processing error:', error);
                    });
                }, 100);
                </script>";
                
                $_SESSION['async_email_script'] = $async_script;
            }
            
            $action_name = $action;
            $message = "Successfully updated {$updated_count} consultation(s) to {$action_name}.";
            if ($email_count > 0) {
                $message .= " {$email_count} email(s) sent.";
            }
            $_SESSION['bulk_message'] = $message;
            
        } catch (Exception $e) {
            $_SESSION['bulk_error'] = "Error processing bulk action: " . $e->getMessage();
        }
        
        header('Location: consultations.php?' . http_build_query([
            'page' => $_GET['page'] ?? 1,
            'sort' => $_GET['sort'] ?? 'created_at',
            'order' => $_GET['order'] ?? 'desc'
        ]));
        exit;
    }
}

// Check for session messages
if (isset($_SESSION['bulk_message'])) {
    $success_message = $_SESSION['bulk_message'];
    unset($_SESSION['bulk_message']);
}
if (isset($_SESSION['bulk_error'])) {
    $error_message = $_SESSION['bulk_error'];
    unset($_SESSION['bulk_error']);
}

// Handle search
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Handle status filter
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// Get consultations with pagination and sorting
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = 'DESC'; // Default order

if (isset($_GET['sort']) && isset($_GET['order'])) {
    // Only apply custom sorting if both sort and order are specified
    $sort_order = $_GET['order'] === 'asc' ? 'ASC' : 'DESC';
} else if (!isset($_GET['sort'])) {
    // No sort specified, use default created_at DESC
    $sort_by = 'created_at';
    $sort_order = 'DESC';
}

// Allowed sort columns for security
$allowed_sort_columns = [
    'c_id', 'c_full_name', 'c_email', 'c_phone', 
    'c_consultation_date', 'c_status', 'created_at'
];

if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'created_at';
}

try {
    $pdo = getDBConnection();
    
    // Build search and filter conditions
    $conditions = [];
    $search_params = [];
    
    if (!empty($search_query)) {
        $conditions[] = "(
            c.c_full_name LIKE ? OR 
            c.c_email LIKE ? OR 
            c.c_phone LIKE ? OR 
            c.c_status LIKE ? OR
            c.c_id LIKE ? OR
            c.c_case_description LIKE ? OR
            c.c_practice_area LIKE ? OR
            lp.lp_fullname LIKE ?
        )";
        
        $search_term = "%{$search_query}%";
        $search_params = array_merge($search_params, array_fill(0, 8, $search_term));
    }
    
    // Add status filter
    if ($status_filter !== 'all') {
        $conditions[] = "c.c_status = ?";
        $search_params[] = $status_filter;
    }
    
    $search_conditions = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
    
    // Get total count with search and filter (with JOIN for lawyer name search)
    $count_query = "SELECT COUNT(*) FROM consultations c 
                    LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id" . $search_conditions;
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($search_params);
    $total_consultations = $count_stmt->fetchColumn();
    $total_pages = ceil($total_consultations / $limit);
    
    // Get consultations with sorting, search, and filter (with JOIN for lawyer name search)
    $query = "
        SELECT c.* FROM consultations c
        LEFT JOIN lawyer_profile lp ON c.lawyer_id = lp.lawyer_id
        {$search_conditions}
        ORDER BY c.{$sort_by} {$sort_order}
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($query);
    $params = array_merge($search_params, [$limit, $offset]);
    $stmt->execute($params);
    $consultations = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $consultations = [];
}
?>

<?php
// Set page-specific variables for the header
$page_title = "Lawyer Consultations";
$active_page = "consultations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Consultations | MD Law Firm</title>
    <link rel="stylesheet" href="../src/admin/css/styles.css">
    <link rel="stylesheet" href="../includes/confirmation-modal.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Toast notification styles */
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
            min-width: 300px;
            max-width: 500px;
        }
        
        .toast.success {
            background: #27ae60;
        }
        
        .toast.error {
            background: #e74c3c;
        }
        
        .toast i {
            font-size: 1.25rem;
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
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        /* Mobile responsive styles for consultations page */
        @media (max-width: 768px) {
            .consultations-stats {
                grid-template-columns: repeat(3, 1fr) !important;
                gap: 0.75rem;
            }
            
            .consult-mobile-header-controls {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
                width: 100%;
            }
            
            .consult-mobile-search-section {
                width: 100%;
            }
            
            .consult-mobile-search-section .search-container {
                flex-direction: column;
                gap: 0.5rem;
                position: relative;
                overflow: visible;
                right:auto;
            }
            
            .consult-mobile-search-section .search-input-wrapper {
                width: 100%;
            }
            
            .consult-mobile-search-section .admin-dropdown {
                width: 100%;
                margin-left: 0 !important;
            }
            
            .consult-mobile-bulk-section {
                width: 100%;
                display: none;
                opacity: 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease, opacity 0.3s ease;
            }
            
            .consult-mobile-bulk-section.show {
                display: block;
                opacity: 1;
                max-height: 200px;
            }
            
            .consult-mobile-bulk-section .bulk-form {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            
            .consult-mobile-bulk-section .admin-dropdown {
                width: 100%;
            }
            
            .consult-mobile-bulk-section .apply-selected {
                width: 100%;
                justify-content: center;
            }
            
            .consult-mobile-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .consult-mobile-table-wrapper table {
                min-width: 800px;
            }
        }
        
        /* Mobile card view - hidden by default */
        .mobile-consultations-cards {
            display: none;
        }
        
        @media (max-width: 480px) {
            .consultations-stats {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            
            .admin-stat-card {
                padding: 1rem;
            }
            
            .admin-stat-number {
                font-size: 1.75rem;
            }
            
            /* Hide desktop table on mobile */
            .desktop-table {
                display: none !important;
            }
            
            /* Show mobile cards */
            .mobile-consultations-cards {
                display: block !important;
            }
            
            /* Mobile consultation card styles */
            .consultation-card {
                background: white;
                border-radius: 8px;
                padding: 16px;
                margin-bottom: 12px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                border-left: 4px solid #c5a253;
            }
            
            .consultation-card-header {
                display: flex;
                align-items: center;
                gap: 12px;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #e9ecef;
            }
            
            .consultation-card-checkbox {
                flex-shrink: 0;
                padding-top: 2px;
            }
            
            .consultation-card-checkbox input[type="checkbox"] {
                width: 18px;
                height: 18px;
                cursor: pointer;
            }
            
            .consultation-card-title-section {
                flex: 1;
            }
            
            .consultation-card-id {
                font-size: 12px;
                color: #6c757d;
                font-weight: 600;
                margin-right: 8px;
            }
            
            .consultation-card-name {
                font-size: 16px;
                font-weight: 600;
                color: #0b1d3a;
                display: flex;
                align-items: center;
            }
            
            .consultation-card-body {
                margin-bottom: 12px;
            }
            
            .consultation-card-row {
                display: flex;
                justify-content: space-between;
                align-items: start;
                padding: 8px 0;
                border-bottom: 1px solid #f8f9fa;
                gap: 12px;
            }
            
            .consultation-card-row:last-child {
                border-bottom: none;
            }
            
            .consultation-card-label {
                font-size: 12px;
                color: #6c757d;
                font-weight: 600;
                flex-shrink: 0;
                min-width: 80px;
            }
            
            .consultation-card-label i {
                margin-right: 4px;
                width: 14px;
            }
            
            .consultation-card-value {
                font-size: 13px;
                color: #0b1d3a;
                text-align: right;
                word-break: break-word;
            }
            
            .consultation-card-footer {
                display: flex;
                gap: 8px;
            }
            
            .consultation-card-btn {
                flex: 1;
                padding: 10px;
                border-radius: 6px;
                font-weight: 600;
                font-size: 13px;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 6px;
                background: #c5a253;
                color: white;
                text-decoration: none;
                transition: all 0.2s ease;
            }
            
            .consultation-card-btn:hover {
                background: #b08f42;
                transform: translateY(-1px);
            }
        }
        
        /* Extra small screens */
        @media (max-width: 375px) {
            .consultation-card {
                padding: 12px;
            }
            
            .consultation-card-name {
                font-size: 14px;
            }
            
            .consultation-card-label {
                font-size: 11px;
                min-width: 70px;
            }
            
            .consultation-card-value {
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="admin-page">
    <?php include 'partials/sidebar.php'; ?>

    <main class="admin-main-content">
        <div class="container">

            <div class="admin-stats-grid consultations-stats" style="grid-template-columns: repeat(5, 1fr);">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_consultations; ?></div>
                    <div class="admin-stat-label">Total Consultations</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number">
                        <?php 
                        try {
                            $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'pending'");
                            echo $pending_stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="admin-stat-label">Pending</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number">
                        <?php 
                        try {
                            $confirmed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'confirmed'");
                            echo $confirmed_stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="admin-stat-label">Confirmed</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number">
                        <?php 
                        try {
                            $completed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'completed'");
                            echo $completed_stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="admin-stat-label">Completed</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number">
                        <?php 
                        try {
                            $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE c_status = 'cancelled'");
                            echo $pending_stmt->fetchColumn();
                        } catch (Exception $e) {
                            echo '0';
                        }
                        ?>
                    </div>
                    <div class="admin-stat-label">Cancelled</div>
                </div>
            </div>

            <div class="admin-consultations-table">
                <div class="admin-section-header">
                    <h2>Consultation Requests</h2>
                    <div class="header-controls consult-mobile-header-controls">
                        <!-- Search Form -->
                        <div class="search-section consult-mobile-search-section">
                            <form method="GET" class="search-form">
                                <div class="search-container">
                                    <div class="search-input-wrapper">
                                        <input type="text" 
                                               name="search" 
                                               value="<?php echo htmlspecialchars($search_query); ?>" 
                                               placeholder="Search consultations..." 
                                               class="form-control search-input">
                                        <?php if (!empty($search_query)): ?>
                                            <a href="consultations.php" class="clear-btn-inside" title="Clear search">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="submit" class="search-btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                    <!-- Status Filter Dropdown -->
                                    <select name="status" class="admin-dropdown" onchange="this.form.submit()" style="margin-left: 10px; min-width: 150px;">
                                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <!-- Preserve current sort parameters -->
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">
                            </form>
                        </div>
                        
                        <!-- Bulk Actions Form -->
                        <div class="bulk-actions-section consult-mobile-bulk-section" id="bulk-actions-section">
                            <form method="POST" id="bulk-form" class="bulk-form">
                                <select name="bulk_action" id="bulk_action" class="admin-dropdown admin-dropdown-primary">
                                    <option value="" selected>Select actions</options>
                                    <option value="pending">Set to Pending</option>
                                    <option value="confirm">Set to Confirmed</option>
                                    <option value="complete">Set to Completed</option>
                                    <option value="cancelled">Set to Cancelled</option>
                                </select>
                                <button type="submit" class="admin-btn admin-btn-primary apply-selected" onclick="return confirmBulkAction()">
                                    <i class="fas fa-check-circle"></i> Apply to Selected
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive consult-mobile-table-wrapper">
                    <form method="POST" id="consultations-form">
                    <!-- Desktop Table View -->
                    <table class="desktop-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                                <th><?php echo getSortableHeader('c_id', 'ID', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('c_full_name', 'Name', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('c_email', 'Email', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('c_phone', 'Phone', $sort_by, $sort_order); ?></th>
                                <th>Lawyer</th>
                                <th><?php echo getSortableHeader('c_consultation_date', 'Date', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('c_status', 'Status', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('created_at', 'Created', $sort_by, $sort_order); ?></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_consultations[]" value="<?php echo $consultation['c_id']; ?>" class="consultation-checkbox desktop-checkbox"></td>
                                    <td><?php echo htmlspecialchars($consultation['c_id']); ?></td>
                                    <td><?php 
                                        $full_name = htmlspecialchars($consultation['c_full_name']);
                                        echo mb_strlen($full_name) > 13 ? mb_substr($full_name, 0, 13) . '...' : $full_name;
                                    ?></td>
                                    <td><?php 
                                        $email = htmlspecialchars($consultation['c_email']);
                                        echo mb_strlen($email) > 13 ? mb_substr($email, 0, 13) . '...' : $email;
                                    ?></td>
                                    <td><?php echo htmlspecialchars($consultation['c_phone']); ?></td>
                                    <td>
                                        <?php 
                                        // Get lawyer name from lawyer_profile
                                        if ($consultation['lawyer_id']) {
                                            $lawyer_stmt = $pdo->prepare("SELECT lp_fullname FROM lawyer_profile WHERE lawyer_id = ?");
                                            $lawyer_stmt->execute([$consultation['lawyer_id']]);
                                            $lawyer = $lawyer_stmt->fetch();
                                            echo $lawyer ? htmlspecialchars($lawyer['lp_fullname']) : 'Unknown';
                                        } else {
                                            echo 'Not assigned';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($consultation['c_consultation_date']) {
                                            echo date('M d, Y', strtotime($consultation['c_consultation_date']));
                                            // Show time if available
                                            if (!empty($consultation['c_consultation_time'])) {
                                                echo '<br><small style="color: #666;"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($consultation['c_consultation_time'])) . '</small>';
                                            }
                                        } else {
                                            echo 'No date';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="admin-status-badge admin-status-<?php echo $consultation['c_status']; ?>">
                                            <?php echo ucfirst($consultation['c_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?></td>
                                    <td>
                                        <div class="admin-action-buttons">
                                            <a href="view_consultation.php?id=<?php echo $consultation['c_id']; ?>" class="admin-btn admin-btn-primary btn-view">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <!-- Mobile Card View -->
                    <div class="mobile-consultations-cards">
                        <?php foreach ($consultations as $consultation): ?>
                            <div class="consultation-card">
                                <div class="consultation-card-header">
                                    <div class="consultation-card-checkbox">
                                        <input type="checkbox" name="selected_consultations[]" value="<?php echo $consultation['c_id']; ?>" class="consultation-checkbox mobile-checkbox" onchange="updateBulkActionsVisibility()">
                                    </div>
                                    <div class="consultation-card-title-section">
                                        <div class="consultation-card-name">
                                            <span class="consultation-card-id">#<?php echo htmlspecialchars($consultation['c_id']); ?></span>
                                            <?php 
                                                $full_name = htmlspecialchars($consultation['c_full_name']);
                                                echo mb_strlen($full_name) > 13 ? mb_substr($full_name, 0, 13) . '...' : $full_name;
                                            ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="admin-status-badge admin-status-<?php echo $consultation['c_status']; ?>">
                                            <?php echo ucfirst($consultation['c_status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="consultation-card-body">
                                    <div class="consultation-card-row">
                                        <div class="consultation-card-label"><i class="fas fa-envelope"></i> Email</div>
                                        <div class="consultation-card-value"><?php 
                                            $email = htmlspecialchars($consultation['c_email']);
                                            echo mb_strlen($email) > 13 ? mb_substr($email, 0, 13) . '...' : $email;
                                        ?></div>
                                    </div>
                                    <div class="consultation-card-row">
                                        <div class="consultation-card-label"><i class="fas fa-phone"></i> Phone</div>
                                        <div class="consultation-card-value"><?php echo htmlspecialchars($consultation['c_phone']); ?></div>
                                    </div>
                                    <div class="consultation-card-row">
                                        <div class="consultation-card-label"><i class="fas fa-user-tie"></i> Lawyer</div>
                                        <div class="consultation-card-value">
                                            <?php 
                                            if ($consultation['lawyer_id']) {
                                                $lawyer_stmt = $pdo->prepare("SELECT lp_fullname FROM lawyer_profile WHERE lawyer_id = ?");
                                                $lawyer_stmt->execute([$consultation['lawyer_id']]);
                                                $lawyer = $lawyer_stmt->fetch();
                                                echo $lawyer ? htmlspecialchars($lawyer['lp_fullname']) : 'Unknown';
                                            } else {
                                                echo 'Not assigned';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="consultation-card-row">
                                        <div class="consultation-card-label"><i class="fas fa-calendar"></i> Date</div>
                                        <div class="consultation-card-value">
                                            <?php 
                                            if ($consultation['c_consultation_date']) {
                                                echo date('M d, Y', strtotime($consultation['c_consultation_date']));
                                                if (!empty($consultation['c_consultation_time'])) {
                                                    echo ' at ' . date('g:i A', strtotime($consultation['c_consultation_time']));
                                                }
                                            } else {
                                                echo 'No date';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="consultation-card-row">
                                        <div class="consultation-card-label"><i class="fas fa-clock"></i> Created</div>
                                        <div class="consultation-card-value"><?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="consultation-card-footer">
                                    <a href="view_consultation.php?id=<?php echo $consultation['c_id']; ?>" class="consultation-card-btn">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </form>
                    
                    <?php if (!empty($search_query)): ?>
                        <div style="padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #e9ecef; font-size: 14px; color: #6c757d;">
                            <i class="fas fa-search"></i> 
                            Showing <?php echo count($consultations); ?> result(s) for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"
                            <?php if ($total_consultations > 0): ?>
                                (<?php echo $total_consultations; ?> total matches)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="admin-pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php 
                        $page_params = $_GET;
                        $page_params['page'] = $i;
                        $page_query = http_build_query($page_params);
                        ?>
                        <?php if ($i === $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo $page_query; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php 
    // Output async email script if present
    if (isset($_SESSION['async_email_script'])) {
        echo $_SESSION['async_email_script'];
        unset($_SESSION['async_email_script']);
    }
    ?>
    
    <script>
    // Toast notification function
    function showToast(message, type = 'success', duration = 5000) {
        // Remove any existing toasts
        const existingToasts = document.querySelectorAll('.toast');
        existingToasts.forEach(toast => toast.remove());
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
        toast.innerHTML = `
            ${icon}
            <span>${message}</span>
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    // Show toast for PHP messages
    <?php if (isset($success_message)): ?>
        showToast('<?php echo addslashes($success_message); ?>', 'success', 5000);
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        showToast('<?php echo addslashes($error_message); ?>', 'error', 5000);
    <?php endif; ?>
    
    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('select-all');
        const consultationCheckboxes = document.querySelectorAll('.consultation-checkbox');
        
        consultationCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
        
        updateBulkActionsVisibility();
    }
    
    function updateBulkActionsVisibility() {
        const selectedCheckboxes = document.querySelectorAll('.consultation-checkbox:checked');
        const bulkActionsSection = document.getElementById('bulk-actions-section');
        const searchContainer = document.querySelector('.search-container');
        
        if (selectedCheckboxes.length > 0) {
            bulkActionsSection.classList.add('show');
            if (searchContainer) {
                searchContainer.classList.add('relative');
            }
        } else {
            bulkActionsSection.classList.remove('show');
            if (searchContainer) {
                searchContainer.classList.remove('relative');
            }
        }
    }
    
    function confirmBulkAction() {
        // Get only visible checkboxes to avoid duplicates
        const isMobile = window.innerWidth <= 480;
        const selector = isMobile ? '.mobile-checkbox:checked' : '.desktop-checkbox:checked';
        const selectedCheckboxes = document.querySelectorAll(selector);
        const bulkAction = document.getElementById('bulk_action').value;
        
        if (selectedCheckboxes.length === 0) {
            openInfoModal('Please select at least one consultation.');
            return false;
        }
        
        if (!bulkAction) {
            openInfoModal('Please select an action.');
            return false;
        }
        
        let actionText = bulkAction;
        let confirmMessage = '';
        
        if (bulkAction === 'confirm' || bulkAction === 'complete') {
            confirmMessage = `Are you sure you want to ${actionText} ${selectedCheckboxes.length} consultation(s)? This will send email notifications to clients.`;
        } else if (bulkAction === 'cancelled') {
            confirmMessage = `Are you sure you want to cancel ${selectedCheckboxes.length} consultation(s)? The cancellation reason will be set to "Cancelled by admin".`;
        } else if (bulkAction === 'pending') {
            confirmMessage = `Are you sure you want to set ${selectedCheckboxes.length} consultation(s) to pending status?`;
        }
        
        // Save pending bulk action and show modal
        window.__bulkPending = {
            action: bulkAction,
            ids: Array.from(selectedCheckboxes).map(cb => cb.value),
            message: confirmMessage
        };

        openBulkModal(confirmMessage);
        return false;
    }
    
    // Handle bulk form submission via modal confirm
    document.getElementById('bulk-form').addEventListener('submit', function(e) {
        e.preventDefault();
        confirmBulkAction();
    });

    // Info and bulk modals
    window.__bulkPending = null;

    function openInfoModal(message) {
        const mdl = document.getElementById('adminInfoModal');
        if (!mdl) return alert(message);
        document.getElementById('adminInfoMessage').textContent = message;
        mdl.style.display = 'flex';
    }

    function closeInfoModal() {
        const mdl = document.getElementById('adminInfoModal');
        if (!mdl) return;
        mdl.style.display = 'none';
    }

    function openBulkModal(message) {
        const mdl = document.getElementById('adminBulkModal');
        if (!mdl) return confirm(message);
        document.getElementById('adminBulkMessage').textContent = message;
        mdl.style.display = 'flex';
    }

    function closeBulkModal() {
        const mdl = document.getElementById('adminBulkModal');
        if (!mdl) return;
        mdl.style.display = 'none';
        window.__bulkPending = null;
    }

    // Confirm button handler
    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === 'adminBulkConfirmBtn') {
            if (!window.__bulkPending) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_action';
            actionInput.value = window.__bulkPending.action;
            form.appendChild(actionInput);

            window.__bulkPending.ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_consultations[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }

        if (e.target && (e.target.id === 'adminBulkCancelBtn' || e.target.classList.contains('kiro-modal-backdrop'))) {
            closeBulkModal();
            closeInfoModal();
        }
    });
    
    // Add event listeners to all consultation checkboxes
    document.addEventListener('DOMContentLoaded', function() {
        const consultationCheckboxes = document.querySelectorAll('.consultation-checkbox');
        
        consultationCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActionsVisibility);
        });
    });
    </script>
    
    <!-- Admin Info Modal -->
    <div id="adminInfoModal" class="kiro-modal-wrapper" style="display:none;">
        <div class="kiro-modal-backdrop"></div>
        <div class="kiro-modal-dialog">
            <div class="kiro-modal-header">
                <h3 class="kiro-modal-title">Notice</h3>
                <button class="kiro-modal-close" onclick="closeInfoModal()">&times;</button>
            </div>
            <div class="kiro-modal-body">
                <p id="adminInfoMessage">Message</p>
            </div>
            <div class="kiro-modal-footer">
                <button class="kiro-btn kiro-btn-info" onclick="closeInfoModal()">OK</button>
            </div>
        </div>
    </div>

    <!-- Admin Bulk Confirm Modal -->
    <div id="adminBulkModal" class="kiro-modal-wrapper" style="display:none;">
        <div class="kiro-modal-backdrop"></div>
        <div class="kiro-modal-dialog">
            <div class="kiro-modal-header">
                <h3 class="kiro-modal-title">Confirm Bulk Action</h3>
                <button class="kiro-modal-close" onclick="closeBulkModal()">&times;</button>
            </div>
            <div class="kiro-modal-body">
                <p id="adminBulkMessage">Are you sure?</p>
            </div>
            <div class="kiro-modal-footer">
                <button class="kiro-btn kiro-btn-secondary" id="adminBulkCancelBtn">Cancel</button>
                <button class="kiro-btn kiro-btn-info" id="adminBulkConfirmBtn">Confirm</button>
            </div>
        </div>
    </div>
</body>
</html>
