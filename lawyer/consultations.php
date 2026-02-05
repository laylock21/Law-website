<?php
/**
 * Lawyer - Consultations List
 * Shows consultations assigned to the logged-in lawyer with ability to update status
 */

session_start();

// Unified authentication check
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true || $_SESSION['user_role'] !== 'lawyer') {
	header('Location: ../login.php');
	exit;
}

require_once '../config/database.php';

$lawyer_id = $_SESSION['lawyer_id'];
$lawyer_name = $_SESSION['lawyer_name'] ?? 'Lawyer';

// No more server-side bulk action handling - moved to JavaScript/API

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

try {
	$pdo = getDBConnection();

	$consultations_columns_stmt = $pdo->query("DESCRIBE consultations");
	$consultations_columns_rows = $consultations_columns_stmt ? $consultations_columns_stmt->fetchAll() : [];
	$consultations_columns = array_map(function($r) { return $r['Field']; }, $consultations_columns_rows);

	$id_column = in_array('c_id', $consultations_columns, true) ? 'c_id' : (in_array('id', $consultations_columns, true) ? 'id' : null);
	$full_name_column = in_array('c_full_name', $consultations_columns, true) ? 'c_full_name' : (in_array('full_name', $consultations_columns, true) ? 'full_name' : null);
	$email_column = in_array('c_email', $consultations_columns, true) ? 'c_email' : (in_array('email', $consultations_columns, true) ? 'email' : null);
	$phone_column = in_array('c_phone', $consultations_columns, true) ? 'c_phone' : (in_array('phone', $consultations_columns, true) ? 'phone' : null);
	$practice_area_column = in_array('c_practice_area', $consultations_columns, true) ? 'c_practice_area' : (in_array('practice_area', $consultations_columns, true) ? 'practice_area' : null);
	$case_description_column = in_array('case_description', $consultations_columns, true) ? 'case_description' : (in_array('c_case_description', $consultations_columns, true) ? 'c_case_description' : (in_array('c_case_description_old', $consultations_columns, true) ? 'c_case_description_old' : null));
	$date_column = in_array('consultation_date', $consultations_columns, true) ? 'consultation_date' : (in_array('c_consultation_date', $consultations_columns, true) ? 'c_consultation_date' : null);
	$time_column = in_array('consultation_time', $consultations_columns, true) ? 'consultation_time' : (in_array('c_consultation_time', $consultations_columns, true) ? 'c_consultation_time' : null);
	$status_column = in_array('c_status', $consultations_columns, true) ? 'c_status' : (in_array('status', $consultations_columns, true) ? 'status' : null);

	if ($id_column === null || $full_name_column === null || $email_column === null || $date_column === null || $status_column === null) {
		throw new Exception('Consultations table schema mismatch');
	}

	// Count - Only include consultations assigned to this lawyer
	$count_where = 'lawyer_id = ?';
	$count_params = [$lawyer_id];
	
	if (!empty($status_filter)) {
		$count_where .= " AND {$status_column} = ?";
		$count_params[] = $status_filter;
	}
	
	if (!empty($date_filter)) {
		$count_where .= " AND {$date_column} = ?";
		$count_params[] = $date_filter;
	}
	
	$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM consultations WHERE $count_where");
	$count_stmt->execute($count_params);
	$total_consultations = (int)$count_stmt->fetchColumn();
	$total_pages = (int)ceil($total_consultations / $limit);

	// Fetch consultations - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
	$select_time = $time_column !== null ? "c.{$time_column} as consultation_time," : "NULL as consultation_time,";
	$select_phone = $phone_column !== null ? "c.{$phone_column} as c_phone," : "NULL as c_phone,";
	$select_practice_area = $practice_area_column !== null ? "c.{$practice_area_column} as c_practice_area," : "NULL as c_practice_area,";
	$select_case_desc = $case_description_column !== null ? "c.{$case_description_column} as case_description," : "NULL as case_description,";
	
	$list_where = 'c.lawyer_id = ?';
	$list_params = [$lawyer_id];
	
	if (!empty($status_filter)) {
		$list_where .= " AND c.{$status_column} = ?";
		$list_params[] = $status_filter;
	}
	
	if (!empty($date_filter)) {
		$list_where .= " AND c.{$date_column} = ?";
		$list_params[] = $date_filter;
	}

	$list_sql = "
		SELECT
			c.{$id_column} as c_id,
			c.{$full_name_column} as c_full_name,
			c.{$email_column} as c_email,
			{$select_phone}
			{$select_practice_area}
			c.{$date_column} as consultation_date,
			{$select_time}
			c.{$status_column} as c_status,
			{$select_case_desc}
			c.created_at,
			c.lawyer_id
		FROM consultations c
		WHERE $list_where
		ORDER BY c.created_at DESC
		LIMIT ? OFFSET ?
	";
	$list_params[] = $limit;
	$list_params[] = $offset;
	$list_stmt = $pdo->prepare($list_sql);
	$list_stmt->execute($list_params);
	$consultations = $list_stmt->fetchAll();
} catch (Exception $e) {
	$error_message = 'Database error: ' . $e->getMessage();
	$consultations = [];
}
?>

<?php
// Set page-specific variables for the header
$page_title = "My Consultations";
$active_page = "consultations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Consultations - <?php echo htmlspecialchars($lawyer_name); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
	<link rel="stylesheet" href="../src/lawyer/css/styles.css">
	<link rel="stylesheet" href="../src/lawyer/css/mobile-responsive.css">
	<link rel="stylesheet" href="../includes/confirmation-modal.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		/* Minimal styles - most moved to styles.css */
	</style>
</head>
<body class="lawyer-page">
	<?php include 'partials/sidebar.php'; ?>
	<!-- Toast notifications - Direct child of body for proper stacking -->
	<div id="toast-container" class="lw-toast-container"></div>
	
	<div class="lawyer-dashboard">

		<main class="lawyer-main-content">
			
			<div class="lawyer-availability-section">
				<div class="lw-consultation-header">
					<h3>Consultation Requests</h3>
					<div class="lw-consultation-controls">
						<!-- Status Filter -->
						<select id="status_filter" 
							name="status_filter" 
							onchange="applyFilters()"
							class="lw-filter-select">
							<option value="">All Statuses</option>
							<option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
							<option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
							<option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
							<option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
						</select>
						
						<!-- Bulk Actions -->
						<div class="lw-bulk-actions-container" id="bulk-actions-container">
							<form method="POST" id="bulk-form" class="lw-bulk-form">
								<select name="bulk_action" id="bulk_action" class="lw-bulk-action-select">
									<option value="confirm">Confirm</option>
									<option value="complete">Complete</option>
									<option value="cancel">Cancel</option>
								</select>
								<button type="submit" class="lawyer-btn btn-apply-selected">Apply to Selected</button>
							</form>
						</div>
						
						<!-- Export Button -->
						<button type="button" class="lawyer-btn btn-export" onclick="openExportModal()" style="background: #c5a253; color: white; padding: 14px 20px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 16px;">
							<i class="fas fa-file-export"></i>
							Export
						</button>
					</div>
				</div>
				<div class="lw-consultation-table-container">
					<form method="POST" id="consultations-form">
					<table class="lw-consultation-table">
						<thead>
							<tr>
								<th><input type="checkbox" id="select-all" class="lw-consultation-select-all" onchange="toggleSelectAll()"></th>
								<th>ID</th>
								<th>Client</th>
								<th>Practice Area</th>
								<th>Date</th>
								<th>Status</th>
								<th>Created</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($consultations as $row): ?>
								<tr>
									<td><input type="checkbox" name="selected_consultations[]" value="<?php echo $row['c_id']; ?>" class="lw-consultation-checkbox"></td>
									<td>#<?php echo (int)$row['c_id']; ?></td>
									<td>
										<div class="lw-consultation-client-cell">
											<div class="lw-consultation-client-name"><?php echo htmlspecialchars($row['c_full_name']); ?></div>
											<div class="lw-consultation-client-email"><?php echo htmlspecialchars($row['c_email']); ?></div>
										</div>
									</td>
									<td><?php echo !empty($row['c_practice_area']) ? htmlspecialchars($row['c_practice_area']) : '—'; ?></td>
									<td>
										<div class="lw-consultation-date-cell">
											<?php 
											if ($row['consultation_date']) {
												echo '<div class="lw-consultation-date-main">' . date('M d, Y', strtotime($row['consultation_date'])) . '</div>';
												if (!empty($row['consultation_time'])) {
													echo '<div class="lw-consultation-date-time"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($row['consultation_time'])) . '</div>';
												}
											} else {
												echo '<div class="lw-consultation-date-main">—</div>';
											}
											?>
										</div>
									</td>
									<td>
										<span class="lawyer-status-badge lawyer-status-<?php echo $row['c_status']; ?>"><?php echo ucfirst($row['c_status']); ?></span>
									</td>
									<td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
									<td data-id="#<?php echo (int)$row['c_id']; ?>">
										<div>
											<button onclick="openConsultationModal(<?php echo (int)$row['c_id']; ?>); return false;" class="lawyer-btn btn-view-details">View Details</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</form>
				</div>
				<?php if (($total_pages ?? 1) > 1): ?>
					<div class="lw-consultation-pagination">
						<?php 
						$filter_params = '';
						if (!empty($status_filter)) {
							$filter_params .= '&status=' . urlencode($status_filter);
						}
						?>
						<?php if ($page > 1): ?>
							<a href="?page=<?php echo $page - 1; ?><?php echo $filter_params; ?>" class="pagination-btn pagination-prev"><i class="fas fa-chevron-left"></i></a>
						<?php else: ?>
							<span class="pagination-btn pagination-prev pagination-disabled"><i class="fas fa-chevron-left"></i></span>
						<?php endif; ?>

						<span class="lw-consultation-pagination-info">
							<?php echo $page; ?>
						</span>

						<?php if ($page < $total_pages): ?>
							<a href="?page=<?php echo $page + 1; ?><?php echo $filter_params; ?>" class="pagination-btn pagination-next"><i class="fas fa-chevron-right"></i></a>
						<?php else: ?>
							<span class="pagination-btn pagination-next pagination-disabled"><i class="fas fa-chevron-right"></i></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</main>
	</div>
	
	<?php 
	// Output async email script if present
	if (isset($_SESSION['async_email_script'])) {
		echo $_SESSION['async_email_script'];
		unset($_SESSION['async_email_script']);
	}
	?>
	
	<!-- Load confirmation modal system BEFORE inline scripts -->
	<script src="../includes/confirmation-modal.js"></script>
	
	<script>
	function updatePanelInfo(title, description) {
		document.getElementById('panel-title').textContent = 'MD Law - ' + title;
		document.getElementById('panel-description').textContent = description;
	}
	
	function applyFilters() {
		const statusFilter = document.getElementById('status_filter').value;
		
		// Build URL with filters
		let url = '?page=1';
		if (statusFilter) {
			url += '&status=' + encodeURIComponent(statusFilter);
		}
		
		window.location.href = url;
	}
	
	function toggleSelectAll() {
		const selectAllCheckbox = document.getElementById('select-all');
		const consultationCheckboxes = document.querySelectorAll('.lw-consultation-checkbox');
		
		consultationCheckboxes.forEach(checkbox => {
			checkbox.checked = selectAllCheckbox.checked;
		});
	}
	
	// Wrap event listeners in DOMContentLoaded to ensure elements exist
	document.addEventListener('DOMContentLoaded', function() {
		// Bulk action confirmation using unified modal system
		const bulkForm = document.getElementById('bulk-form');
		if (bulkForm) {
			bulkForm.addEventListener('submit', async function(e) {
				e.preventDefault();

				const selectedCheckboxes = document.querySelectorAll('.lw-consultation-checkbox:checked');
				const bulkAction = document.getElementById('bulk_action').value;

				if (selectedCheckboxes.length === 0) {
					await ConfirmModal.alert({
						title: 'No Selection',
						message: 'Please select at least one consultation.',
						type: 'warning'
					});
					return;
				}

				if (!bulkAction) {
					await ConfirmModal.alert({
						title: 'No Action Selected',
						message: 'Please select an action.',
						type: 'warning'
					});
					return;
				}

				const actionText = bulkAction === 'confirm' ? 'confirm' : (bulkAction === 'cancel' ? 'cancel' : 'complete');
				const count = selectedCheckboxes.length;
				const message = `Are you sure you want to ${actionText} ${count} consultation(s)? This will send email notifications to clients.`;

				const confirmed = await ConfirmModal.confirm({
					title: 'Confirm Bulk Action',
					message: message,
					confirmText: 'Yes, Proceed',
					cancelText: 'Cancel',
					type: 'info'
				});

				if (confirmed) {
					// Process bulk action via API
					await processBulkAction(selectedCheckboxes, bulkAction);
				}
			});
		}
	});

	// Process bulk action using single API call
	async function processBulkAction(checkboxes, action) {
		const statusMap = {
			'confirm': 'confirmed',
			'complete': 'completed',
			'cancel': 'cancelled'
		};
		const newStatus = statusMap[action];
		
		// Extract consultation IDs
		const consultationIds = Array.from(checkboxes).map(checkbox => parseInt(checkbox.value));
		
		try {
			const response = await fetch('../api/lawyer/bulk_update_consultation_status.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: JSON.stringify({
					consultation_ids: consultationIds,
					new_status: newStatus,
					cancellation_reason: 'Bulk action by lawyer'
				})
			});
			
			const data = await response.json();
			
			if (data.success || data.results.success_count > 0) {
				// Build detailed result message
				let resultMessage = data.message;
				
				// Add details for skipped items if any
				if (data.results.skipped_count > 0 && data.results.skipped_reasons.length > 0) {
					resultMessage += '\n\nSkipped:\n' + data.results.skipped_reasons.join('\n');
				}
				
				// Add details for errors if any
				if (data.results.error_count > 0 && data.results.error_reasons.length > 0) {
					resultMessage += '\n\nErrors:\n' + data.results.error_reasons.join('\n');
				}
				
				// Show result as toast
				const toastType = data.results.success_count > 0 ? 'success' : 
								 (data.results.error_count > 0 ? 'error' : 'warning');
				showToast(resultMessage, toastType);
				
				// Reload page to show updated statuses if any were successful
				if (data.results.success_count > 0) {
					setTimeout(() => {
						location.reload();
					}, 2000);
				}
			} else {
				showToast(data.message || 'Failed to process bulk action', 'error');
			}
			
		} catch (error) {
			console.error('Bulk action error:', error);
			showToast('Network error occurred while processing bulk action', 'error');
		}
	}
	
	// Toast notification function
	function showToast(message, type = 'info') {
		const container = document.getElementById('toast-container');
		if (!container) {
			console.error('Toast container not found!');
			return;
		}
		
		// Create toast element
		const toast = document.createElement('div');
		toast.className = `lw-toast ${type}`;
		
		const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
		const title = type === 'success' ? 'Success' : 'Error';
		
		// Set icon color based on type
		const iconColor = type === 'success' ? '#28a745' : '#dc3545';
		
		toast.innerHTML = `
			<i class="fas ${icon} lw-toast-icon" style="color: ${iconColor};"></i>
			<div class="lw-toast-content">
				<div class="lw-toast-title">${title}</div>
				<div class="lw-toast-message">${message}</div>
			</div>
			<button class="lw-toast-close" onclick="closeToast(this)">×</button>
		`;
		
		// Set the progress bar animation duration
		const duration = 5000;
		toast.style.setProperty('--duration', duration + 'ms');
		const style = document.createElement('style');
		const toastId = Date.now();
		style.textContent = `
			.lw-toast[data-id="${toastId}"]::after {
				animation-duration: ${duration}ms;
			}
		`;
		toast.dataset.id = toastId;
		document.head.appendChild(style);
		
		container.appendChild(toast);
		
		// Force a reflow to ensure the toast is visible
		toast.offsetHeight;
		
		// Auto-remove after duration
		const timeoutId = setTimeout(() => {
			closeToast(toast.querySelector('.lw-toast-close'));
		}, duration);
		
		// Store timeout ID on toast element so we can cancel it if needed
		toast.dataset.timeoutId = timeoutId;
	}
	
	function closeToast(button) {
		const toast = button.closest ? button.closest('.lw-toast') : button;
		if (!toast) {
			console.error('Toast element not found');
			return;
		}
		
		// Clear the auto-close timeout if it exists
		if (toast.dataset && toast.dataset.timeoutId) {
			clearTimeout(parseInt(toast.dataset.timeoutId));
		}
		
		toast.classList.add('hiding');
		setTimeout(() => {
			toast.remove();
		}, 300);
	}

	// Modal functionality for consultation details
	function openConsultationModal(consultationId) {
		event.preventDefault(); // Prevent any default behavior
		event.stopPropagation(); // Stop event bubbling
		
		const modal = document.getElementById('consultationModal');
		const modalContent = document.getElementById('modalConsultationContent');
		
		// Show modal with loading state
		modal.style.display = 'block';
		modalContent.innerHTML = '<div class="modal-loading"><i class="fas fa-spinner fa-spin"></i> Loading consultation details...</div>';
		
		// Fetch consultation details via AJAX
		fetch(`../api/lawyer/get_consultation_details.php?id=${consultationId}`)
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					modalContent.innerHTML = data.html;
					// Initialize any JavaScript needed for the modal content
					initializeModalScripts();
				} else {
					modalContent.innerHTML = '<div class="modal-error">Error loading consultation details: ' + data.message + '</div>';
				}
			})
			.catch(error => {
				console.error('Error:', error);
				modalContent.innerHTML = '<div class="modal-error">Error loading consultation details. Please try again.</div>';
			});
	}

	function closeConsultationModal() {
		document.getElementById('consultationModal').style.display = 'none';
	}

	function initializeModalScripts() {
		// Initialize cancellation reason toggle
		const statusSelect = document.getElementById('modal_new_status');
		const reasonGroup = document.getElementById('modal_cancellation_reason_group');
		
		if (statusSelect && reasonGroup) {
			function toggleCancellationReason() {
				if (statusSelect.value === 'cancelled') {
					reasonGroup.style.display = 'block';
				} else {
					reasonGroup.style.display = 'none';
				}
			}
			
			statusSelect.addEventListener('change', toggleCancellationReason);
			toggleCancellationReason(); // Initialize on load
		}

		// Handle status update form submission
		const statusForm = document.getElementById('modalStatusForm');
		if (statusForm) {
			statusForm.addEventListener('submit', function(e) {
				e.preventDefault();
				
				const formData = new FormData(statusForm);
				const submitBtn = statusForm.querySelector('button[type="submit"]');
				const originalText = submitBtn.textContent;
				
				submitBtn.disabled = true;
				submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
				
				fetch('../api/lawyer/update_consultation_status.php', {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Update the status badge in the modal
						const statusBadge = document.querySelector('.modal-status-badge');
						if (statusBadge && data.new_status) {
							statusBadge.className = `lawyer-status-badge lawyer-status-${data.new_status} modal-status-badge`;
							statusBadge.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
						}
						
						// Show success toast
						showToast(data.message, 'success');
						
						// Close modal and refresh after a short delay
						setTimeout(() => {
							closeConsultationModal();
							location.reload();
						}, 1500);
					} else {
						// Show error toast
						showToast(data.message, 'error');
						submitBtn.disabled = false;
						submitBtn.textContent = originalText;
					}
				})
				.catch(error => {
					console.error('Error:', error);
					showToast('Error updating status. Please try again.', 'error');
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				});
			});
		}
	}

	// Close modal when clicking outside of it
	document.addEventListener('click', function(event) {
		const consultationModal = document.getElementById('consultationModal');
		
		if (consultationModal && event.target === consultationModal) {
			closeConsultationModal();
		}
	});

	// Close modal with Escape key
	document.addEventListener('keydown', function(event) {
		if (event.key === 'Escape') {
			const modal = document.getElementById('consultationModal');
			if (modal.style.display === 'block') {
				closeConsultationModal();
			}
		}
	});

	// Prevent modal content clicks from closing the modal
	document.addEventListener('DOMContentLoaded', function() {
		const consultationModal = document.getElementById('consultationModal');
		if (consultationModal) {
			const modalContent = consultationModal.querySelector('.modal-content');
			if (modalContent) {
				modalContent.addEventListener('click', function(event) {
					event.stopPropagation();
				});
			}
		}
	});
	</script>

	<!-- Consultation Details Modal -->
	<div id="consultationModal" class="consultation-modal" style="display: none;">
		<div class="modal-content">
			<div class="modal-header">
				<h2>Consultation Details</h2>
				<span class="modal-close" onclick="closeConsultationModal()">&times;</span>
			</div>
			<div class="modal-body" id="modalConsultationContent">
				<!-- Content will be loaded here via AJAX -->
			</div>
		</div>
	</div>

	<!-- Export Modal -->
	<div id="exportModal" class="consultation-modal" style="display: none;">
		<div class="modal-content" style="max-width: 500px; border-radius: 12px; overflow: hidden;">
			<div class="modal-header" style="background: #3a3a3a; color: white; padding: 20px 24px; border-bottom: none;">
				<h2 style="margin: 0; font-size: 22px; font-weight: 700;">Export Consultations</h2>
				<span class="modal-close" onclick="closeExportModal()" style="color: white; opacity: 0.8; font-size: 28px;">&times;</span>
			</div>
			<div class="modal-body" style="padding: 28px 24px;">
				<form id="exportForm" action="export.php" method="POST">
					<!-- Status Filter -->
					<div style="margin-bottom: 24px;">
						<label style="display: block; font-weight: 600; margin-bottom: 12px; color: #4a5568; font-size: 15px; text-align: center;">Filter by Status</label>
						<select name="export_status" id="export_status" style="width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #2d3748; background: white;">
							<option value="">All Statuses</option>
							<option value="pending">Pending</option>
							<option value="confirmed">Confirmed</option>
							<option value="completed">Completed</option>
							<option value="cancelled">Cancelled</option>
						</select>
					</div>

					<!-- Export Format -->
					<div style="margin-bottom: 24px;">
						<label style="display: block; font-weight: 600; margin-bottom: 12px; color: #4a5568; font-size: 15px; text-align: center;">Export Format</label>
						<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
							<label class="export-format-option" style="cursor: pointer;">
								<input type="radio" name="export_format" value="excel" checked style="display: none;">
								<div class="format-card" style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 24px 16px; text-align: center; transition: all 0.3s ease; background: white;">
									<i class="fas fa-file-excel" style="font-size: 36px; color: #c5a253; margin-bottom: 10px; display: block;"></i>
									<div style="font-weight: 600; color: #2d3748; margin-bottom: 4px; font-size: 15px;">Excel</div>
									<div style="font-size: 12px; color: #a0aec0;">Spreadsheet format</div>
								</div>
							</label>
							<label class="export-format-option" style="cursor: pointer;">
								<input type="radio" name="export_format" value="pdf" style="display: none;">
								<div class="format-card" style="border: 2px solid #e2e8f0; border-radius: 10px; padding: 24px 16px; text-align: center; transition: all 0.3s ease; background: white;">
									<i class="fas fa-file-pdf" style="font-size: 36px; color: #c5a253; margin-bottom: 10px; display: block;"></i>
									<div style="font-weight: 600; color: #2d3748; margin-bottom: 4px; font-size: 15px;">PDF</div>
									<div style="font-size: 12px; color: #a0aec0;">Professional reports</div>
								</div>
							</label>
						</div>
					</div>

					<!-- Export Button -->
					<button type="submit" class="lawyer-btn" style="width: 100%; background: #c5a253; color: white; padding: 13px; border: none; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.3s ease;">
						<i class="fas fa-download" style="font-size: 16px;"></i>
						Export Consultations
					</button>
				</form>
			</div>
		</div>
	</div>

	<style>
		.export-format-option input[type="radio"]:checked + .format-card {
			border-color: #c5a253 !important;
			background: linear-gradient(135deg, #fff9e6, #fffbf0) !important;
			box-shadow: 0 4px 12px rgba(197, 162, 83, 0.25);
		}

		.export-format-option .format-card:hover {
			border-color: #c5a253;
			transform: translateY(-2px);
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
		}

		@media (max-width: 768px) {
			#exportModal .modal-content {
				max-width: 85%;
				margin: 15px auto;
			}
			
			#exportModal .modal-body {
				padding: 20px 18px !important;
			}
			
			#exportModal .modal-header {
				padding: 16px 18px !important;
			}
			
			#exportModal .modal-header h2 {
				font-size: 18px !important;
			}
			
			#exportModal label {
				font-size: 13px !important;
				margin-bottom: 10px !important;
			}
			
			#exportModal select {
				padding: 10px 12px !important;
				font-size: 13px !important;
			}
			
			#exportModal .format-card {
				padding: 18px 12px !important;
			}
			
			#exportModal .format-card i {
				font-size: 28px !important;
				margin-bottom: 8px !important;
			}
			
			#exportModal .format-card > div:first-of-type {
				font-size: 14px !important;
			}
			
			#exportModal .format-card > div:last-of-type {
				font-size: 11px !important;
			}
			
			#exportModal button[type="submit"] {
				padding: 11px !important;
				font-size: 14px !important;
			}
			
			#exportModal button[type="submit"] i {
				font-size: 14px !important;
			}
			
			#exportModal > div > div {
				margin-bottom: 18px !important;
			}
		}
	</style>

	<script>
		function openExportModal() {
			document.getElementById('exportModal').style.display = 'block';
		}

		function closeExportModal() {
			document.getElementById('exportModal').style.display = 'none';
		}

		// Close modal when clicking outside
		window.addEventListener('click', function(event) {
			const exportModal = document.getElementById('exportModal');
			if (event.target === exportModal) {
				closeExportModal();
			}
		});

		// Handle export form submission
		document.getElementById('exportForm').addEventListener('submit', function(e) {
			const btn = this.querySelector('button[type="submit"]');
			const originalHTML = btn.innerHTML;
			btn.disabled = true;
			btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
			
			// Reset button after 3 seconds
			setTimeout(function() {
				btn.innerHTML = originalHTML;
				btn.disabled = false;
				closeExportModal();
			}, 3000);
		});
	</script>
</body>
</html>

