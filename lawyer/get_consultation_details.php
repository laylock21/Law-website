<?php
/**
 * AJAX endpoint for fetching consultation details for modal display
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];
$lawyer_name = $_SESSION['lawyer_name'] ?? 'Lawyer';
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$consultation_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid consultation ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Fetch consultation details (restricted to this lawyer or unassigned)
    $stmt = $pdo->prepare('SELECT * FROM consultations WHERE id = ? AND (lawyer_id = ? OR lawyer_id IS NULL) LIMIT 1');
    $stmt->execute([$consultation_id, $lawyer_id]);
    $consultation = $stmt->fetch();
    
    if (!$consultation) {
        echo json_encode(['success' => false, 'message' => 'Consultation not found or access denied']);
        exit;
    }
    
    // Generate the HTML content for the modal
    ob_start();
    ?>
    
    <div class="modal-consultation-details">
        <h3>Consultation #<?php echo $consultation_id; ?></h3>
        <p>Assigned to: <?php echo htmlspecialchars($lawyer_name); ?></p>
        
        <h4>Client & Case Details</h4>
        
        <table class="modal-consultation-table">
            <tbody>
                <tr>
                    <th>Client Name</th>
                    <td><?php echo htmlspecialchars($consultation['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($consultation['email']); ?>" 
                           style="color: var(--gold); text-decoration: none;">
                            <?php echo htmlspecialchars($consultation['email']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>Phone</th>
                    <td>
                        <a href="tel:<?php echo htmlspecialchars($consultation['phone']); ?>" 
                           style="color: var(--gold); text-decoration: none;">
                            <?php echo htmlspecialchars($consultation['phone']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th>Practice Area</th>
                    <td><?php echo htmlspecialchars($consultation['practice_area']); ?></td>
                </tr>
                <tr>
                    <th>Consultation Date</th>
                    <td>
                        <?php 
                        if ($consultation['consultation_date']) {
                            echo date('l, F d, Y', strtotime($consultation['consultation_date']));
                        } elseif ($consultation['selected_date']) {
                            echo date('l, F d, Y', strtotime($consultation['selected_date']));
                        } else {
                            echo 'No specific date requested';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Consultation Time</th>
                    <td>
                        <?php 
                        if (!empty($consultation['consultation_time'])) {
                            echo '<i class="fas fa-clock" style="color: var(--gold); margin-right: 8px;"></i>' . 
                                 date('g:i A', strtotime($consultation['consultation_time']));
                        } else {
                            echo '<span style="color: #999;">No specific time selected</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td>
                        <span class="lawyer-status-badge lawyer-status-<?php echo $consultation['status']; ?> modal-status-badge">
                            <?php echo ucfirst($consultation['status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Case Description</th>
                    <td>
                        <div class="modal-case-description">
                            <?php echo nl2br(htmlspecialchars($consultation['case_description'])); ?>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>Submitted</th>
                    <td><?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="modal-form-section">
        <h3>Update Status</h3>
        <form id="modalStatusForm">
            <input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
            <input type="hidden" name="action" value="update_status">
            
            <div class="modal-form-group">
                <label for="modal_new_status">Status</label>
                <select name="new_status" id="modal_new_status">
                    <option value="pending" <?php echo $consultation['status']==='pending'?'selected':''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $consultation['status']==='confirmed'?'selected':''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo $consultation['status']==='cancelled'?'selected':''; ?>>Cancelled</option>
                    <option value="completed" <?php echo $consultation['status']==='completed'?'selected':''; ?>>Completed</option>
                </select>
            </div>
            
            <div class="modal-form-group" id="modal_cancellation_reason_group" style="display: none;">
                <label for="modal_cancellation_reason">Cancellation Reason</label>
                <select name="cancellation_reason" id="modal_cancellation_reason">
                    <?php $saved_reason = $consultation['cancellation_reason'] ?? ''; ?>
                    <option value="Lawyer decision" <?php echo ($saved_reason === 'Lawyer decision') ? 'selected' : ''; ?>>Lawyer decision</option>
                    <option value="Lawyer unavailable" <?php echo ($saved_reason === 'Lawyer unavailable') ? 'selected' : ''; ?>>Lawyer unavailable</option>
                    <option value="Client request" <?php echo ($saved_reason === 'Client request') ? 'selected' : ''; ?>>Client request</option>
                    <option value="Scheduling conflict" <?php echo ($saved_reason === 'Scheduling conflict') ? 'selected' : ''; ?>>Scheduling conflict</option>
                    <option value="Emergency situation" <?php echo ($saved_reason === 'Emergency situation') ? 'selected' : ''; ?>>Emergency situation</option>
                    <option value="Technical issues" <?php echo ($saved_reason === 'Technical issues') ? 'selected' : ''; ?>>Technical issues</option>
                    <option value="Other circumstances" <?php echo ($saved_reason === 'Other circumstances') ? 'selected' : ''; ?>>Other circumstances</option>
                </select>
            </div>
            
            <div class="modal-form-group">
                <button type="submit">Update Status</button>
            </div>
        </form>
    </div>
    
    <?php
    $html = ob_get_clean();
    
    echo json_encode([
        'success' => true,
        'html' => $html,
        'consultation_id' => $consultation_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>