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
    $new_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $sort_icon = '';
    
    if ($current_sort === $column) {
        $sort_icon = $current_order === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
    } else {
        $sort_icon = ' <i class="fas fa-sort"></i>';
    }
    
    $current_params = $_GET;
    $current_params['sort'] = $column;
    $current_params['order'] = $new_order;
    
    $query_string = http_build_query($current_params);
    
    return '<a href="?' . $query_string . '" class="sortable-header">' . $display_name . $sort_icon . '</a>';
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_consultations = $_POST['selected_consultations'] ?? [];
    
    if (!empty($selected_consultations) && in_array($action, ['confirm', 'complete'])) {
        try {
            $pdo = getDBConnection();
            require_once '../includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            
            $updated_count = 0;
            $email_count = 0;
            
            foreach ($selected_consultations as $consultation_id) {
                $consultation_id = (int)$consultation_id;
                
                // Get current status
                $check_stmt = $pdo->prepare("SELECT status FROM consultations WHERE id = ?");
                $check_stmt->execute([$consultation_id]);
                $current = $check_stmt->fetch();
                
                if ($current) {
                    $old_status = $current['status'];
                    $new_status = ($action === 'confirm') ? 'confirmed' : 'completed';
                    
                    // Only update if status is different
                    if ($old_status !== $new_status) {
                        // Update status
                        $update_stmt = $pdo->prepare("UPDATE consultations SET status = ? WHERE id = ?");
                        $update_stmt->execute([$new_status, $consultation_id]);
                        
                        if ($update_stmt->rowCount() > 0) {
                            $updated_count++;
                            
                            // Send email notification
                            $queued = false;
                            if ($new_status === 'confirmed') {
                                $queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
                            } elseif ($new_status === 'completed') {
                                $queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
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
            
            $action_name = ($action === 'confirm') ? 'confirmed' : 'completed';
            $_SESSION['bulk_message'] = "Successfully {$action_name} {$updated_count} consultation(s). {$email_count} email(s) sent.";
            
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

// Get consultations with pagination and sorting
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

// Allowed sort columns for security
$allowed_sort_columns = [
    'id', 'full_name', 'email', 'phone', 'practice_area', 
    'selected_lawyer', 'consultation_date', 'status', 'created_at'
];

if (!in_array($sort_by, $allowed_sort_columns)) {
    $sort_by = 'created_at';
}

try {
    $pdo = getDBConnection();
    
    // Build search conditions
    $search_conditions = '';
    $search_params = [];
    
    if (!empty($search_query)) {
        $search_conditions = " WHERE (
            full_name LIKE ? OR 
            email LIKE ? OR 
            phone LIKE ? OR 
            practice_area LIKE ? OR 
            selected_lawyer LIKE ? OR 
            status LIKE ? OR
            id LIKE ?
        )";
        
        $search_term = "%{$search_query}%";
        $search_params = array_fill(0, 7, $search_term);
    }
    
    // Get total count with search
    $count_query = "SELECT COUNT(*) FROM consultations" . $search_conditions;
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($search_params);
    $total_consultations = $count_stmt->fetchColumn();
    $total_pages = ceil($total_consultations / $limit);
    
    // Get consultations with sorting and search
    $query = "
        SELECT * FROM consultations 
        {$search_conditions}
        ORDER BY {$sort_by} {$sort_order}
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
    <link rel="stylesheet" href="../styles.css">
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="admin-page">
    <?php include 'partials/header.php'; ?>

    <main class="admin-main-content">
        <div class="container">
            <?php if (isset($success_message)): ?>
                <div class="admin-alert admin-alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="admin-alert admin-alert-error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="admin-stats-grid">
                <div class="admin-stat-card">
                    <div class="admin-stat-number"><?php echo $total_consultations; ?></div>
                    <div class="admin-stat-label">Total Consultations</div>
                </div>
                <div class="admin-stat-card">
                    <div class="admin-stat-number">
                        <?php 
                        try {
                            $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'pending'");
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
                            $confirmed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'confirmed'");
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
                            $completed_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'completed'");
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
                            $pending_stmt = $pdo->query("SELECT COUNT(*) FROM consultations WHERE status = 'cancelled'");
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
                    <div class="header-controls">
                        <!-- Search Form -->
                        <div class="search-section">
                            <form method="GET" class="search-form">
                                <div class="search-container">
                                    <input type="text" 
                                           name="search" 
                                           value="<?php echo htmlspecialchars($search_query); ?>" 
                                           placeholder="Search consultations..." 
                                           class="form-control search-input">
                                    <button type="submit" class="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                                <?php if (!empty($search_query)): ?>
                                    <a href="consultations.php" class="admin-btn admin-btn-secondary clear-btn" title="Clear search">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                                <!-- Preserve current sort parameters -->
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">
                            </form>
                        </div>
                        
                        <!-- Bulk Actions Form -->
                        <div class="bulk-actions-section">
                            <form method="POST" id="bulk-form" class="bulk-form">
                                <select name="bulk_action" id="bulk_action" class="admin-dropdown admin-dropdown-primary">
                                    <option value="">Select Action</option>
                                    <option value="confirm">✅ Bulk Confirm</option>
                                    <option value="complete">✅ Bulk Complete</option>
                                </select>
                                <button type="submit" class="admin-btn admin-btn-primary" onclick="return confirmBulkAction()">
                                    <i class="fas fa-check-circle"></i> Apply to Selected
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <form method="POST" id="consultations-form">
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                                <th><?php echo getSortableHeader('id', 'ID', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('full_name', 'Name', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('email', 'Email', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('phone', 'Phone', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('practice_area', 'Practice Area', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('selected_lawyer', 'Lawyer', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('consultation_date', 'Date', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('status', 'Status', $sort_by, $sort_order); ?></th>
                                <th><?php echo getSortableHeader('created_at', 'Created', $sort_by, $sort_order); ?></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_consultations[]" value="<?php echo $consultation['id']; ?>" class="consultation-checkbox"></td>
                                    <td><?php echo htmlspecialchars($consultation['id']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['email']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['practice_area']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['selected_lawyer']); ?></td>
                                    <td>
                                        <?php 
                                        if ($consultation['consultation_date']) {
                                            echo date('M d, Y', strtotime($consultation['consultation_date']));
                                            // Show time if available
                                            if (!empty($consultation['consultation_time'])) {
                                                echo '<br><small style="color: #666;"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($consultation['consultation_time'])) . '</small>';
                                            }
                                        } elseif ($consultation['selected_date']) {
                                            echo date('M d, Y', strtotime($consultation['selected_date']));
                                            // Show time if available
                                            if (!empty($consultation['consultation_time'])) {
                                                echo '<br><small style="color: #666;"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($consultation['consultation_time'])) . '</small>';
                                            }
                                        } else {
                                            echo 'No date';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="admin-status-badge admin-status-<?php echo $consultation['status']; ?>">
                                            <?php echo ucfirst($consultation['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?></td>
                                    <td>
                                        <div class="admin-action-buttons">
                                            <a href="view_consultation.php?id=<?php echo $consultation['id']; ?>" class="admin-btn admin-btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    function toggleSelectAll() {
        const selectAllCheckbox = document.getElementById('select-all');
        const consultationCheckboxes = document.querySelectorAll('.consultation-checkbox');
        
        consultationCheckboxes.forEach(checkbox => {
            checkbox.checked = selectAllCheckbox.checked;
        });
    }
    
    function confirmBulkAction() {
        const selectedCheckboxes = document.querySelectorAll('.consultation-checkbox:checked');
        const bulkAction = document.getElementById('bulk_action').value;
        
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one consultation.');
            return false;
        }
        
        if (!bulkAction) {
            alert('Please select an action.');
            return false;
        }
        
        const actionText = bulkAction === 'confirm' ? 'confirm' : 'complete';
        const confirmMessage = `Are you sure you want to ${actionText} ${selectedCheckboxes.length} consultation(s)? This will send email notifications to clients.`;
        
        return confirm(confirmMessage);
    }
    
    // Handle bulk form submission
    document.getElementById('bulk-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (confirmBulkAction()) {
            const selectedCheckboxes = document.querySelectorAll('.consultation-checkbox:checked');
            const bulkAction = document.getElementById('bulk_action').value;
            
            // Create a form with selected consultations
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            // Add bulk action
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'bulk_action';
            actionInput.value = bulkAction;
            form.appendChild(actionInput);
            
            // Add selected consultations
            selectedCheckboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_consultations[]';
                input.value = checkbox.value;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    </script>
</body>
</html>
