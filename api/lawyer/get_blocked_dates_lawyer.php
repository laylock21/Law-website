<?php
/**
 * AJAX Endpoint - Get Blocked Dates for Lawyer (Paginated)
 * Returns HTML for blocked dates section only
 */

session_start();

// Authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../../config/database.php';

$lawyer_id = $_SESSION['user_id'];
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

try {
    $pdo = getDBConnection();
    
    // Pagination settings
    $per_page = 5;
    $offset = ($page - 1) * $per_page;
    
    // Get all blocked dates (for total count)
    $all_blocked_stmt = $pdo->prepare("
        SELECT id, specific_date, start_date, end_date, blocked_reason, created_at
        FROM lawyer_availability
        WHERE user_id = ? 
        AND schedule_type = 'blocked'
        AND (
            (specific_date IS NOT NULL AND specific_date >= CURDATE())
            OR (start_date IS NOT NULL AND end_date IS NOT NULL AND end_date >= CURDATE())
        )
        ORDER BY 
            COALESCE(specific_date, start_date) ASC
    ");
    $all_blocked_stmt->execute([$lawyer_id]);
    $blocked_dates = $all_blocked_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = count($blocked_dates);
    $total_pages = ceil($total / $per_page);
    
    // Get paginated blocked dates
    $blocked_dates_paginated = array_slice($blocked_dates, $offset, $per_page);
    
    // Output HTML
    if ($total === 0): ?>
        <p style="color: #6c757d; padding: 20px; text-align: center; background: #f8f9fa; border-radius: 8px;">
            No blocked dates.
        </p>
    <?php else: ?>
        <!-- Bulk Actions Bar -->
        <div id="bulk-actions-bar" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 2px solid #721c24;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <strong style="color: var(--navy);">
                        <span id="selected-count">0</span> date(s) selected
                    </strong>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="selectAllBlocked()">Select All</button>
                    <button type="button" class="btn-secondary" onclick="deselectAllBlocked()">Deselect All</button>
                    <button type="button" class="btn-delete" onclick="bulkUnblock()" style="background: #721c24;">
                        Unblock Selected
                    </button>
                </div>
            </div>
        </div>
        
        <form id="bulk-unblock-form" method="POST" action="availability.php">
            <input type="hidden" name="action" value="bulk_unblock">
            <input type="hidden" name="blocked_ids" id="blocked-ids-input">
        </form>
        
        <?php foreach ($blocked_dates_paginated as $schedule): ?>
            <div class="availability-item blocked-date-item" style="background: #fff5f5; border-color: #dc3545; position: relative;">
                <!-- Checkbox for multi-select -->
                <div class="bulk-checkbox-container" style="position: absolute; top: 50%; left: 15px; transform: translateY(-50%); display: none;">
                    <input type="checkbox" class="blocked-checkbox" value="<?php echo $schedule['id']; ?>" 
                           onchange="updateBulkActions()" 
                           style="width: 20px; height: 20px; cursor: pointer;">
                </div>
                <div class="availability-header blocked-date-content" style="margin-left: 0; transition: margin-left 0.3s ease;">
                    <div class="availability-info">
                        <div style="margin-bottom: 8px;">
                            <?php if (!empty($schedule['start_date']) && !empty($schedule['end_date'])): ?>
                                <span class="schedule-badge" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 6px 12px; border-radius: 6px;">
                                    <i class="fas fa-calendar-times"></i> BLOCKED RANGE
                                </span>
                            <?php else: ?>
                                <span class="schedule-badge" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; font-weight: 600; padding: 6px 12px; border-radius: 6px;">
                                    <i class="fas fa-ban"></i> BLOCKED DATE
                                </span>
                            <?php endif; ?>
                            <span style="color: #6c757d; font-size: 0.9rem; display: block; margin-top: 4px;">
                                <?php echo $schedule['blocked_reason'] ? htmlspecialchars($schedule['blocked_reason']) : 'Unavailable'; ?>
                            </span>
                        </div>
                        <div class="availability-details">
                            <strong>
                                <?php echo (!empty($schedule['start_date']) && !empty($schedule['end_date'])) ? 'Date Range:' : 'Date:'; ?>
                            </strong>
                            <span>
                                <?php 
                                if (!empty($schedule['specific_date'])) {
                                    echo date('l, M d, Y', strtotime($schedule['specific_date']));
                                } elseif (!empty($schedule['start_date']) && !empty($schedule['end_date'])) {
                                    echo date('M d, Y', strtotime($schedule['start_date'])) . ' - ' . date('M d, Y', strtotime($schedule['end_date']));
                                }
                                ?>
                            </span>
                            <strong>Status:</strong>
                            <span style="color: #dc3545; font-weight: 600;">UNAVAILABLE</span>
                        </div>
                    </div>
                    <div class="availability-actions">
                        <form method="POST" action="availability.php" style="display: inline;">
                            <input type="hidden" name="action" value="permanent_delete">
                            <input type="hidden" name="availability_id" value="<?php echo $schedule['id']; ?>">
                            <button type="submit" class="btn-delete">
                                Unblock
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="javascript:void(0)" onclick="loadBlockedDates(<?php echo $page - 1; ?>)" title="Previous">
                        ← Prev
                    </a>
                <?php else: ?>
                    <span class="disabled">← Prev</span>
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
    error_log("Database error in get_blocked_dates_lawyer.php: " . $e->getMessage());
    http_response_code(500);
    echo '<p style="color: #dc3545; text-align: center;">Error loading blocked dates. Please try again.</p>';
}
?>
