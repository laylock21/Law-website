<?php
/**
 * AJAX Endpoint - Get Blocked Dates (Paginated)
 * Returns HTML for blocked dates section only
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../config/database.php';

$lawyer_id = isset($_GET['lawyer_id']) ? (int)$_GET['lawyer_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

if ($lawyer_id <= 0) {
    http_response_code(400);
    exit('Invalid lawyer ID');
}

try {
    $pdo = getDBConnection();
    
    // Pagination settings
    $per_page = 5;
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM lawyer_availability
        WHERE user_id = ? 
        AND schedule_type = 'blocked'
        AND (
            (specific_date IS NOT NULL AND specific_date >= CURDATE())
            OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= CURDATE())
        )
    ");
    $count_stmt->execute([$lawyer_id]);
    $total = $count_stmt->fetchColumn();
    $total_pages = ceil($total / $per_page);
    
    // Get paginated blocked dates
    $blocked_stmt = $pdo->prepare("
        SELECT id, specific_date, start_date, end_date, blocked_reason, created_at
        FROM lawyer_availability
        WHERE user_id = ? 
        AND schedule_type = 'blocked'
        AND (
            (specific_date IS NOT NULL AND specific_date >= CURDATE())
            OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= CURDATE())
        )
        ORDER BY COALESCE(specific_date, start_date) ASC
        LIMIT ? OFFSET ?
    ");
    $blocked_stmt->execute([$lawyer_id, $per_page, $offset]);
    $blocked_dates = $blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output HTML
    if ($total === 0): ?>
        <p style="text-align: center; color: #6c757d; padding: 20px;">
            No blocked dates for this lawyer.
        </p>
    <?php else: ?>
        <!-- Bulk Actions Bar -->
        <div id="bulk-actions-bar" style="display: none; background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #daa520;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="color: #0b1d3a;">
                        <span id="selected-count">0</span> date(s) selected
                    </strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="selectAllBlocked()">Select All</button>
                    <button type="button" class="btn btn-secondary" onclick="deselectAllBlocked()">Deselect All</button>
                    <button type="button" class="btn btn-danger" onclick="bulkUnblock()">
                        <i class="fas fa-trash"></i> Unblock Selected
                    </button>
                </div>
            </div>
        </div>
        
        <form id="bulk-unblock-form" method="POST" action="manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>">
            <input type="hidden" name="action" value="bulk_unblock">
            <input type="hidden" name="blocked_ids" id="blocked-ids-input">
        </form>
        
        <?php foreach ($blocked_dates as $blocked): ?>
            <div class="blocked-date-item" style="position: relative;">
                <!-- Checkbox for multi-select -->
                <div class="bulk-checkbox-container" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); display: none;">
                    <input type="checkbox" class="blocked-checkbox" value="<?php echo $blocked['id']; ?>" 
                           onchange="updateBulkActions()" 
                           style="width: 20px; height: 20px; cursor: pointer;">
                </div>
                <div class="blocked-date-content" style="display: flex; justify-content: space-between; align-items: center; transition: margin-left 0.3s ease; margin-left: 0;">
                    <div class="date-info">
                        <?php if (!empty($blocked['start_date']) && !empty($blocked['end_date'])): ?>
                            <span style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; display: inline-block; margin-bottom: 8px;">
                                <i class="fas fa-calendar-times"></i> BLOCKED RANGE
                            </span>
                            <br>
                            <strong><?php echo date('M d, Y', strtotime($blocked['start_date'])); ?> - <?php echo date('M d, Y', strtotime($blocked['end_date'])); ?></strong>
                        <?php else: ?>
                            <span style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 4px 10px; border-radius: 6px; font-size: 0.85rem; display: inline-block; margin-bottom: 8px;">
                                <i class="fas fa-ban"></i> BLOCKED DATE
                            </span>
                            <br>
                            <strong><?php echo date('l, F j, Y', strtotime($blocked['specific_date'])); ?></strong>
                        <?php endif; ?>
                        <small style="display: block; margin-top: 4px;">
                            <?php echo $blocked['blocked_reason'] ? htmlspecialchars($blocked['blocked_reason']) : 'Unavailable'; ?>
                        </small>
                        <small style="color: #999;">Blocked on: <?php echo date('M j, Y g:i A', strtotime($blocked['created_at'])); ?></small>
                    </div>
                    <form method="POST" action="manage_lawyer_schedule.php?lawyer_id=<?php echo $lawyer_id; ?>" style="display: inline;">
                        <input type="hidden" name="action" value="unblock_date">
                        <input type="hidden" name="availability_id" value="<?php echo $blocked['id']; ?>">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Unblock
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $page - 1; ?>)" title="Previous">
                        ← Previous
                    </a>
                <?php else: ?>
                    <span class="disabled">← Previous</span>
                <?php endif; ?>
                
                <?php
                $range = 2;
                for ($i = 1; $i <= $total_pages; $i++):
                    if ($i == 1 || $i == $total_pages || abs($i - $page) <= $range):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $i; ?>)"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php
                    elseif (abs($i - $page) == $range + 1):
                        echo '<span class="disabled">...</span>';
                    endif;
                endfor;
                ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $page + 1; ?>)" title="Next">
                        Next →
                    </a>
                <?php else: ?>
                    <span class="disabled">Next →</span>
                <?php endif; ?>
            </div>
            
            <div class="pagination-info">
                Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> blocked dates
            </div>
        <?php endif; ?>
    <?php endif;
    
} catch (PDOException $e) {
    error_log("Database error in get_blocked_dates.php: " . $e->getMessage());
    http_response_code(500);
    echo '<p style="color: #dc3545; text-align: center;">Error loading blocked dates. Please try again.</p>';
}
?>
