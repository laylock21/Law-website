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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selected_consultations = $_POST['selected_consultations'] ?? [];
    
    if (!empty($selected_consultations) && in_array($action, ['confirm', 'complete', 'cancel'])) {
        try {
            $pdo = getDBConnection();
            require_once '../includes/EmailNotification.php';
            $emailNotification = new EmailNotification($pdo);
            
            $updated_count = 0;
            $email_count = 0;
            
            foreach ($selected_consultations as $consultation_id) {
                $consultation_id = (int)$consultation_id;
                
                // Ensure this consultation belongs to the lawyer OR is designated as 'Any'
                $check_stmt = $pdo->prepare('SELECT id, status, lawyer_id FROM consultations WHERE id = ? AND (lawyer_id = ? OR lawyer_id IS NULL)');
                $check_stmt->execute([$consultation_id, $lawyer_id]);
                $current_consultation = $check_stmt->fetch();
                
                if ($current_consultation) {
                    $old_status = $current_consultation['status'];
                    $new_status = ($action === 'confirm') ? 'confirmed' : (($action === 'cancel') ? 'cancelled' : 'completed');
                    
                    // Only update if status is different
                    if ($old_status !== $new_status) {
                        // Update status
                        $update_stmt = $pdo->prepare('UPDATE consultations SET status = ? WHERE id = ?');
                        $update_stmt->execute([$new_status, $consultation_id]);
                        
                        if ($update_stmt->rowCount() > 0) {
                            $updated_count++;
                            
                            // If completing and no lawyer assigned, assign current lawyer
                            if ($new_status === 'completed' && !$current_consultation['lawyer_id']) {
                                $assign_stmt = $pdo->prepare('UPDATE consultations SET lawyer_id = ? WHERE id = ?');
                                $assign_stmt->execute([$lawyer_id, $consultation_id]);
                            }
                            
                            // Send email notification
                            $queued = false;
                            if ($new_status === 'confirmed') {
                                $queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
                            } elseif ($new_status === 'completed') {
                                $queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
                            } elseif ($new_status === 'cancelled') {
                                $queued = $emailNotification->notifyAppointmentCancelled($consultation_id);
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
            
            $action_name = ($action === 'confirm') ? 'confirmed' : (($action === 'cancel') ? 'cancelled' : 'completed');
            $_SESSION['bulk_message'] = "Successfully {$action_name} {$updated_count} consultation(s). {$email_count} email(s) sent.";
            
        } catch (Exception $e) {
            $_SESSION['bulk_error'] = "Error processing bulk action: " . $e->getMessage();
        }
        
        header('Location: consultations.php?page=' . ($_GET['page'] ?? 1));
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
	<link rel="stylesheet" href="../src/css/styles.css">
	<link rel="stylesheet" href="../src/lawyer/css/styles.css">
	<link rel="stylesheet" href="../includes/confirmation-modal.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<style>
		/* Make checkboxes bigger */
		input[type="checkbox"] {
			width: 166x;
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
	</style>
</head>
<body class="lawyer-page">
	<?php include 'partials/sidebar.php'; ?>
	<div class="lawyer-dashboard">

		<main class="lawyer-main-content">
			<?php if (isset($success_message)): ?>
				<div class="lawyer-alert lawyer-alert-success"><?php echo htmlspecialchars($success_message); ?></div>
			<?php endif; ?>
			<?php if (isset($error_message)): ?>
				<div class="lawyer-alert lawyer-alert-error"><?php echo htmlspecialchars($error_message); ?></div>
			<?php endif; ?>

			<div class="lawyer-availability-section">
				<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
					<h3>Consultation Requests</h3>
					<div class="bulk-actions" style="margin: 10px 0;">
						<form method="POST" id="bulk-form" style="display: flex; gap: 10px; align-items: center;">
							<select name="bulk_action" id="bulk_action" style="padding: 8px; border-radius: 6px; border: 1px solid #e9ecef;">
								<option value="confirm">Confirm</option>
								<option value="complete">Complete</option>
								<option value="cancel">Cancel</option>
							</select>
							<button type="submit" class="lawyer-btn btn-apply-selected">Apply to Selected</button>
					</div>
					</form>
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
									<td style="padding: 0px; border-bottom: 1px solid #e9ecef; text-align: center;">
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
	}
	
	// Wrap event listeners in DOMContentLoaded to ensure elements exist
	document.addEventListener('DOMContentLoaded', function() {
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
					// Create and submit form
					const form = document.createElement('form');
					form.method = 'POST';
					form.style.display = 'none';

					const actionInput = document.createElement('input');
					actionInput.type = 'hidden';
					actionInput.name = 'bulk_action';
					actionInput.value = bulkAction;
			form.appendChild(actionInput);

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
		}
	});

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
						// Show success message
						const successDiv = document.createElement('div');
						successDiv.className = 'lawyer-alert lawyer-alert-success';
						successDiv.textContent = data.message;
						statusForm.insertBefore(successDiv, statusForm.firstChild);
						
						// Update the status badge in the modal
						const statusBadge = document.querySelector('.modal-status-badge');
						if (statusBadge && data.new_status) {
							statusBadge.className = `lawyer-status-badge lawyer-status-${data.new_status} modal-status-badge`;
							statusBadge.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
						}
						
						// Refresh the consultations table after a short delay
						setTimeout(() => {
							location.reload();
						}, 2000);
					} else {
						// Show error message
						const errorDiv = document.createElement('div');
						errorDiv.className = 'lawyer-alert lawyer-alert-error';
						errorDiv.textContent = data.message;
						statusForm.insertBefore(errorDiv, statusForm.firstChild);
					}
				})
				.catch(error => {
					console.error('Error:', error);
					const errorDiv = document.createElement('div');
					errorDiv.className = 'lawyer-alert lawyer-alert-error';
					errorDiv.textContent = 'Error updating status. Please try again.';
					statusForm.insertBefore(errorDiv, statusForm.firstChild);
				})
				.finally(() => {
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				});
			});
		}
	}

	// Close modal when clicking outside of it
	document.addEventListener('click', function(event) {
		const consultationModal = document.getElementById('consultationModal');
		const bulkWarningModal = document.getElementById('bulkWarningModal');
		
		if (consultationModal && event.target === consultationModal) {
			closeConsultationModal();
		}
		if (bulkWarningModal && event.target === bulkWarningModal) {
			closeBulkWarningModal();
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

	// Prevent modal content clicks from closing the modals
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
		
		const bulkWarningModal = document.getElementById('bulkWarningModal');
		if (bulkWarningModal) {
			const bulkContent = bulkWarningModal.querySelector('.modal-content');
			if (bulkContent) {
				bulkContent.addEventListener('click', function(event) {
					event.stopPropagation();
				});
			}
		}
	});
	</script>

	<!-- Old modals removed - now using unified ConfirmModal system -->

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

	<!-- Bulk selection warning modal -->
	<div id="bulkWarningModal" class="consultation-modal" style="display: none;">
		<div class="modal-content">
			<div class="modal-header">
				<h2>Nothing selected</h2>
				<span class="modal-close" onclick="closeBulkWarningModal()">&times;</span>
			</div>
			<div class="modal-body">
				<p>Please select at least one consultation before applying a bulk action.</p>
				<div style="margin-top: 15px; display:flex; justify-content:flex-end;">
					<button type="button" class="lawyer-btn" onclick="closeBulkWarningModal()">OK</button>
				</div>
			</div>
		</div>
	</div>
</body>
</html>

