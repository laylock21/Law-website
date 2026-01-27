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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
	$pdo = getDBConnection();

	// Count - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
	$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM consultations WHERE lawyer_id = ? OR lawyer_id IS NULL');
	$count_stmt->execute([$lawyer_id]);
	$total_consultations = (int)$count_stmt->fetchColumn();
	$total_pages = (int)ceil($total_consultations / $limit);

	// Fetch consultations - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
	$list_stmt = $pdo->prepare('
		SELECT *
		FROM consultations
		WHERE lawyer_id = ? OR lawyer_id IS NULL
		ORDER BY created_at DESC
		LIMIT ? OFFSET ?
	');
	$list_stmt->execute([$lawyer_id, $limit, $offset]);
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
		/* Make checkboxes bigger */
		input[type="checkbox"] {
			width: 16px;
			height: 16px;
			cursor: pointer;
		}
		
		#select-all {
			width: 16px;
			height: 16px;
			cursor: pointer;
		}
		
		.consultation-checkbox {
			width: 16px;
			height: 16px;
			cursor: pointer;
		}
		
		/* Bulk action select styling */
		#bulk_action {
			padding: 10px 16px;
			border: 2px solid #e9ecef;
			border-radius: 6px;
			font-family: var(--font-sans);
			font-size: 14px;
			font-weight: 500;
			background: white;
			color: var(--text-dark);
			cursor: pointer;
			display: inline-block;
			transition: all 0.3s ease;
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
			background-repeat: no-repeat;
			background-position: right 12px center;
			background-size: 16px 12px;
			padding-right: 40px;
			height: 42px;
			min-width: 150px;
		}
		
		#bulk_action:hover {
			border-color: var(--gold);
			transform: translateY(-1px);
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}
		
		#bulk_action:focus {
			outline: none;
			border-color: var(--gold);
			box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
		}
		
		/* Bulk actions container animation */
		#bulk-actions-container {
			opacity: 0;
			transform: translateY(-10px);
			transition: all 0.3s ease;
			max-height: 0;
			overflow: hidden;
		}
		
		#bulk-actions-container.show {
			opacity: 1;
			transform: translateY(0);
			max-height: 100px;
		}
		
		/* Toast Notification Styles */
		#toast-container {
			position: fixed;
			top: 20px;
			right: 20px;
			z-index: 9999;
			display: flex;
			flex-direction: column;
			gap: 10px;
			max-width: 400px;
		}
		
		.toast {
			background: white;
			border-radius: 8px;
			box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
			padding: 16px 20px;
			display: flex;
			align-items: flex-start;
			gap: 12px;
			animation: slideIn 0.3s ease-out forwards;
			border-left: 4px solid;
			min-width: 300px;
			max-width: 400px;
			opacity: 1;
			transform: translateX(0);
			position: relative;
			overflow: hidden;
		}
		
		.toast::after {
			content: '';
			position: absolute;
			bottom: 0;
			left: 0;
			height: 3px;
			background: currentColor;
			width: 100%;
			transform-origin: left;
			animation: progressBar linear forwards;
		}
		
		.toast.success::after {
			color: #28a745;
		}
		
		.toast.error::after {
			color: #dc3545;
		}
		
		@keyframes progressBar {
			from {
				transform: scaleX(1);
			}
			to {
				transform: scaleX(0);
			}
		}
		
		.toast.success {
			border-left-color: #28a745;
		}
		
		.toast.error {
			border-left-color: #dc3545;
		}
		
		.toast-icon {
			font-size: 20px;
			flex-shrink: 0;
			margin-top: 2px;
		}
		
		.toast.success .toast-icon {
			color: #28a745;
		}
		
		.toast.error .toast-icon {
			color: #dc3545;
		}
		
		.toast-content {
			flex: 1;
			display: flex;
			flex-direction: column;
			gap: 4px;
		}
		
		.toast-title {
			font-weight: 600;
			font-size: 14px;
			color: #dc3545;
		}
		
		.toast-message {
			font-size: 13px;
			color: #666;
			line-height: 1.4;
		}
		
		.toast-close {
			background: none;
			border: none;
			color: #999;
			cursor: pointer;
			font-size: 18px;
			padding: 0;
			width: 20px;
			height: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
			flex-shrink: 0;
			transition: color 0.2s;
		}
		
		.toast-close:hover {
			color: #dc3545;
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
		
		.toast.hiding {
			animation: slideOut 0.3s ease-out forwards;
		}
		
		@media (max-width: 768px) {
			#toast-container {
				top: 10px;
				right: 10px;
				left: 10px;
				max-width: none;
			}
			
			.toast {
				min-width: auto;
				max-width: none;
			}
		}
	</style>
</head>
<body class="lawyer-page">
	<?php include 'partials/sidebar.php'; ?>
	<div class="lawyer-dashboard">

		<main class="lawyer-main-content">
			<!-- Toast notifications will appear here -->
			<div id="toast-container"></div>
			
			<div class="lawyer-availability-section">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
					<h3>Consultation Requests</h3>
					<div class="bulk-actions" id="bulk-actions-container" style="margin: 10px 0; display: none;">
						<form method="POST" id="bulk-form" style="display: flex; gap: 10px; align-items: center;">
							<select name="bulk_action" id="bulk_action">
								<option value="confirm">Confirm</option>
								<option value="complete">Complete</option>
								<option value="cancel">Cancel</option>
							</select>
							<button type="submit" class="lawyer-btn btn-apply-selected">Apply to Selected</button>
						</form>
					</div>
				</div>
				<div style="overflow-x: auto;">
					<form method="POST" id="consultations-form">
					<table class="admin-consultations-table" style="width: 100%; border-collapse: collapse;">
						<thead>
							<tr>
								<th style="text-align:left; padding: 12px;"><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
								<th style="text-align:left; padding: 12px;">ID</th>
								<th style="text-align:left; padding: 12px;">Client</th>
								<th style="text-align:left; padding: 12px;">Practice Area</th>
								<th style="text-align:left; padding: 12px;">Date</th>
								<th style="text-align:left; padding: 12px;">Status</th>
								<th style="text-align:left; padding: 12px;">Created</th>
								<th style="text-align:center; padding: 12px; width: 120px;">Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($consultations as $row): ?>
								<tr>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef;"><input type="checkbox" name="selected_consultations[]" value="<?php echo $row['id']; ?>" class="consultation-checkbox"></td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef;">#<?php echo (int)$row['id']; ?></td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef;"><?php echo htmlspecialchars($row['full_name']); ?><br><small><?php echo htmlspecialchars($row['email']); ?></small></td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef; "><?php echo htmlspecialchars($row['practice_area']); ?></td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef; ">
										<?php 
										if ($row['consultation_date']) {
											echo date('M d, Y', strtotime($row['consultation_date']));
											if (!empty($row['consultation_time'])) {
												echo '<br><small style="color: #666;"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($row['consultation_time'])) . '</small>';
											}
										} elseif ($row['selected_date']) {
											echo date('M d, Y', strtotime($row['selected_date']));
											if (!empty($row['consultation_time'])) {
												echo '<br><small style="color: #666;"><i class="fas fa-clock"></i> ' . date('g:i A', strtotime($row['consultation_time'])) . '</small>';
											}
										} else {
											echo '—';
										}
										?>
									</td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef;">
										<span class="lawyer-status-badge lawyer-status-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span>
									</td>
									<td style="padding: 12px; border-bottom: 1px solid #e9ecef; "><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
									<td style="padding: 0px; border-bottom: 1px solid #e9ecef; text-align: center;" data-id="#<?php echo (int)$row['id']; ?>">
										<div style="display:flex; gap:8px; align-items:center; flex-wrap: wrap; justify-content: center;">
											<button onclick="openConsultationModal(<?php echo (int)$row['id']; ?>); return false;" class="lawyer-btn btn-view-details" style="text-decoration:none; padding:8px 12px; border: none; cursor: pointer;">View Details</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</form>
				</div>
				<?php if (($total_pages ?? 1) > 1): ?>
					<div style="display:flex; gap:8px; justify-content:center; align-items:center; margin-top:16px;">
						<?php if ($page > 1): ?>
							<a href="?page=<?php echo $page - 1; ?>" class="pagination-btn pagination-prev"><i class="fas fa-chevron-left"></i></a>
						<?php else: ?>
							<span class="pagination-btn pagination-prev pagination-disabled"><i class="fas fa-chevron-left"></i></span>
						<?php endif; ?>

						<span style="font-size:14px; color:#666; font-weight:500;">
							<?php echo $page; ?>
						</span>

						<?php if ($page < $total_pages): ?>
							<a href="?page=<?php echo $page + 1; ?>" class="pagination-btn pagination-next"><i class="fas fa-chevron-right"></i></a>
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
	
	function toggleSelectAll() {
		const selectAllCheckbox = document.getElementById('select-all');
		const consultationCheckboxes = document.querySelectorAll('.consultation-checkbox');
		
		consultationCheckboxes.forEach(checkbox => {
			checkbox.checked = selectAllCheckbox.checked;
		});
		
		// Update bulk actions visibility
		updateBulkActionsVisibility();
	}
	
	function updateBulkActionsVisibility() {
		const checkedBoxes = document.querySelectorAll('.consultation-checkbox:checked');
		const bulkActionsContainer = document.getElementById('bulk-actions-container');
		
		if (checkedBoxes.length > 0) {
			bulkActionsContainer.style.display = 'block';
			// Trigger reflow to ensure transition works
			bulkActionsContainer.offsetHeight;
			bulkActionsContainer.classList.add('show');
		} else {
			bulkActionsContainer.classList.remove('show');
			// Hide after animation completes
			setTimeout(() => {
				if (!bulkActionsContainer.classList.contains('show')) {
					bulkActionsContainer.style.display = 'none';
				}
			}, 300);
		}
	}
	
	// Wrap event listeners in DOMContentLoaded to ensure elements exist
	document.addEventListener('DOMContentLoaded', function() {
		// Add change event listeners to all consultation checkboxes
		const consultationCheckboxes = document.querySelectorAll('.consultation-checkbox');
		consultationCheckboxes.forEach(checkbox => {
			checkbox.addEventListener('change', updateBulkActionsVisibility);
		});
		
		// Bulk action confirmation using unified modal system
		const bulkForm = document.getElementById('bulk-form');
		if (bulkForm) {
			bulkForm.addEventListener('submit', async function(e) {
				e.preventDefault();

				const selectedCheckboxes = document.querySelectorAll('.consultation-checkbox:checked');
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

	// Process bulk action by calling API for each consultation
	async function processBulkAction(checkboxes, action) {
		const statusMap = {
			'confirm': 'confirmed',
			'complete': 'completed',
			'cancel': 'cancelled'
		};
		const newStatus = statusMap[action];
		
		let successCount = 0;
		let skippedCount = 0;
		let errorCount = 0;
		const skippedReasons = [];
		const errorReasons = [];
		
		// Process each consultation
		for (const checkbox of checkboxes) {
			const consultationId = checkbox.value;
			
			try {
				const formData = new FormData();
				formData.append('consultation_id', consultationId);
				formData.append('new_status', newStatus);
				formData.append('cancellation_reason', 'Bulk action by lawyer');
				
				const response = await fetch('../api/lawyer/update_consultation_status.php', {
					method: 'POST',
					body: formData
				});
				
				const data = await response.json();
				
				if (data.success) {
					successCount++;
				} else {
					skippedCount++;
					skippedReasons.push(`#${consultationId}: ${data.message}`);
				}
			} catch (error) {
				errorCount++;
				errorReasons.push(`#${consultationId}: Network error`);
			}
		}
		
		// Trigger async email processing
		if (successCount > 0) {
			fetch('../process_emails_async.php', {
				method: 'POST',
				headers: {'X-Requested-With': 'XMLHttpRequest'}
			}).catch(error => {
				console.log('Email processing error:', error);
			});
		}
		
		// Build result message
		const actionName = action === 'confirm' ? 'confirmed' : (action === 'cancel' ? 'cancelled' : 'completed');
		let resultMessage = '';
		
		if (successCount > 0) {
			resultMessage += `✅ Successfully ${actionName} ${successCount} consultation(s).\n`;
		}
		if (skippedCount > 0) {
			resultMessage += `⚠️ ${skippedCount} consultation(s) skipped:\n${skippedReasons.join('\n')}\n`;
		}
		if (errorCount > 0) {
			resultMessage += `❌ ${errorCount} error(s):\n${errorReasons.join('\n')}`;
		}
		
		// Show result as toast
		const toastType = successCount > 0 ? 'success' : (errorCount > 0 ? 'error' : 'warning');
		showToast(resultMessage.trim(), toastType);
		
		// Reload page to show updated statuses
		if (successCount > 0) {
			setTimeout(() => {
				location.reload();
			}, 2000);
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
		toast.className = `toast ${type}`;
		
		const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
		const title = type === 'success' ? 'Success' : 'Error';
		
		// Set icon color based on type
		const iconColor = type === 'success' ? '#28a745' : '#dc3545';
		
		toast.innerHTML = `
			<i class="fas ${icon} toast-icon" style="color: ${iconColor};"></i>
			<div class="toast-content">
				<div class="toast-title">${title}</div>
				<div class="toast-message">${message}</div>
			</div>
			<button class="toast-close" onclick="closeToast(this)">×</button>
		`;
		
		// Set the progress bar animation duration
		const duration = 5000;
		toast.style.setProperty('--duration', duration + 'ms');
		const style = document.createElement('style');
		const toastId = Date.now();
		style.textContent = `
			.toast[data-id="${toastId}"]::after {
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
			closeToast(toast.querySelector('.toast-close'));
		}, duration);
		
		// Store timeout ID on toast element so we can cancel it if needed
		toast.dataset.timeoutId = timeoutId;
	}
	
	function closeToast(button) {
		const toast = button.closest ? button.closest('.toast') : button;
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
</body>
</html>

