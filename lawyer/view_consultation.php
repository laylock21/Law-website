<?php
/**
 * Lawyer - View Consultation
 * Detail page for a single consultation assigned to the logged-in lawyer
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
$consultation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$consultation_id) {
	header('Location: consultations.php');
	exit;
}

// No more server-side status update handling - moved to JavaScript/API

// Load consultation details (restricted) - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
try {
	$pdo = getDBConnection();
	$stmt = $pdo->prepare('SELECT c.c_id as id, c.c_full_name as full_name, c.c_email as email, c.c_phone as phone,
	                              c.c_practice_area as practice_area, c.c_consultation_date as consultation_date,
	                              c.c_consultation_time as consultation_time, c.c_status as status,
	                              c.c_case_description as case_description, c.c_cancellation_reason as cancellation_reason,
	                              c.c_selected_date as selected_date, c.created_at, c.lawyer_id
	                       FROM consultations c 
	                       WHERE c.c_id = ? AND (c.lawyer_id = ? OR c.lawyer_id IS NULL) LIMIT 1');
	$stmt->execute([$consultation_id, $lawyer_id]);
	$consultation = $stmt->fetch();
	if (!$consultation) {
		header('Location: consultations.php');
		exit;
	}
} catch (Exception $e) {
	$error_message = 'Database error: ' . $e->getMessage();
	$consultation = null;
}
?>

<?php
// Set page-specific variables for the header
$page_title = "View Consultations";
$active_page = "consultations";
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Consultation #<?php echo $consultation_id; ?> - <?php echo htmlspecialchars($lawyer_name); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
	<link rel="stylesheet" href="../src/lawyer/css/styles.css">
	<link rel="stylesheet" href="../src/lawyer/css/mobile-responsive.css">
	<link rel="stylesheet" href="../includes/confirmation-modal.css">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="lawyer-page">
	<div class="lawyer-dashboard">
		<?php include 'partials/sidebar.php'; ?>

		<main class="lawyer-main-content">
			<div style="margin-bottom: 16px;">
				<a href="consultations.php" class="lawyer-btn" style="text-decoration:none;">← Back to List</a>
			</div>

			<?php if ($consultation): ?>
				<div class="lawyer-availability-section">
					<h1>Consultation #<?php echo $consultation_id; ?></h1>
					<p style="margin: 5px 0 25px 0; color: #6c757d; font-size: 14px;">Assigned to: <?php echo htmlspecialchars($lawyer_name); ?></p>
					
					<h3>Client & Case Details</h3>
					<table class="admin-consultations-table" style="width:100%; border-collapse: collapse;">
						<tbody>
							<tr>
								<th style="text-align:left; padding:12px;">Client Name</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><?php echo htmlspecialchars($consultation['full_name']); ?></td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Email</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><a href="mailto:<?php echo htmlspecialchars($consultation['email']); ?>"><?php echo htmlspecialchars($consultation['email']); ?></a></td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Phone</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><a href="tel:<?php echo htmlspecialchars($consultation['phone']); ?>"><?php echo htmlspecialchars($consultation['phone']); ?></a></td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Practice Area</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><?php echo htmlspecialchars($consultation['practice_area']); ?></td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Consultation Date</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;">
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
								<th style="text-align:left; padding:12px;">Consultation Time</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;">
									<?php 
									if (!empty($consultation['consultation_time'])) {
										echo '<i class="fas fa-clock" style="color: var(--gold);"></i> ' . date('g:i A', strtotime($consultation['consultation_time']));
									} else {
										echo '<span style="color: #999;">No specific time selected</span>';
									}
									?>
								</td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Status</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;">
									<span class="lawyer-status-badge lawyer-status-<?php echo $consultation['status']; ?>"><?php echo ucfirst($consultation['status']); ?></span>
								</td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Case Description</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><div class="case-description"><?php echo nl2br(htmlspecialchars($consultation['case_description'])); ?></div></td>
							</tr>
							<tr>
								<th style="text-align:left; padding:12px;">Submitted</th>
								<td style="padding:12px; border-bottom:1px solid #e9ecef;"><?php echo date('M d, Y H:i', strtotime($consultation['created_at'])); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="lawyer-availability-section" style="margin-top: 16px;">
					<h3>Update Status</h3>
					<form id="statusUpdateForm">
						<input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
						<div class="lawyer-form-group">
							<label for="new_status">Status</label>
							<select name="new_status" id="new_status" onchange="toggleCancellationReason()">
								<option value="pending" <?php echo $consultation['status']==='pending'?'selected':''; ?>>Pending</option>
								<option value="confirmed" <?php echo $consultation['status']==='confirmed'?'selected':''; ?>>Confirmed</option>
								<option value="cancelled" <?php echo $consultation['status']==='cancelled'?'selected':''; ?>>Cancelled</option>
								<option value="completed" <?php echo $consultation['status']==='completed'?'selected':''; ?>>Completed</option>
							</select>
						</div>
						
						<div class="lawyer-form-group" id="cancellation_reason_group" style="display: none;">
							<label for="cancellation_reason">Cancellation Reason</label>
							<select name="cancellation_reason" id="cancellation_reason">
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
						
						<button type="submit" class="lawyer-btn">Update Status</button>
					</form>
				</div>
			<?php endif; ?>
		</main>
	</div>
	
	<script>
	function toggleCancellationReason() {
		const statusSelect = document.getElementById('new_status');
		const reasonGroup = document.getElementById('cancellation_reason_group');
		
		if (statusSelect.value === 'cancelled') {
			reasonGroup.style.display = 'block';
		} else {
			reasonGroup.style.display = 'none';
		}
	}
	
	// Show reason field if cancelled is already selected
	document.addEventListener('DOMContentLoaded', function() {
		toggleCancellationReason();
		
		// If status is cancelled, show the reason field immediately
		const statusSelect = document.getElementById('new_status');
		if (statusSelect.value === 'cancelled') {
			document.getElementById('cancellation_reason_group').style.display = 'block';
		}
		
		// Handle form submission via API
		const statusForm = document.getElementById('statusUpdateForm');
		if (statusForm) {
			statusForm.addEventListener('submit', async function(e) {
				e.preventDefault();
				
				const formData = new FormData(statusForm);
				const submitBtn = statusForm.querySelector('button[type="submit"]');
				const originalText = submitBtn.textContent;
				
				submitBtn.disabled = true;
				submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
				
				try {
					const response = await fetch('../api/lawyer/update_consultation_status.php', {
						method: 'POST',
						body: formData
					});
					
					const data = await response.json();
					
					if (data.success) {
						// Update status badge on page
						const statusBadge = document.querySelector('.lawyer-status-badge');
						if (statusBadge && data.new_status) {
							statusBadge.className = `lawyer-status-badge lawyer-status-${data.new_status}`;
							statusBadge.textContent = data.new_status.charAt(0).toUpperCase() + data.new_status.slice(1);
						}
						
						// Show success modal
						if (typeof ConfirmModal !== 'undefined') {
							await ConfirmModal.alert({
								title: 'Success',
								message: data.message,
								type: 'success'
							});
						} else {
							alert(data.message);
						}
						
						// Trigger async email processing
						fetch('../process_emails_async.php', {
							method: 'POST',
							headers: {'X-Requested-With': 'XMLHttpRequest'}
						}).catch(error => {
							console.log('Email processing error:', error);
						});
						
						submitBtn.disabled = false;
						submitBtn.textContent = originalText;
					} else {
						// Show error modal
						if (typeof ConfirmModal !== 'undefined') {
							await ConfirmModal.alert({
								title: 'Error',
								message: data.message,
								type: 'error'
							});
						} else {
							alert(data.message);
						}
						
						submitBtn.disabled = false;
						submitBtn.textContent = originalText;
					}
				} catch (error) {
					console.error('Error:', error);
					
					if (typeof ConfirmModal !== 'undefined') {
						await ConfirmModal.alert({
							title: 'Error',
							message: 'Error updating status. Please try again.',
							type: 'error'
						});
					} else {
						alert('Error updating status. Please try again.');
					}
					
					submitBtn.disabled = false;
					submitBtn.textContent = originalText;
				}
			});
		}
	});
	</script>
	
	<?php 
	// Output async email script if present
	if (isset($_SESSION['async_email_script'])) {
		echo $_SESSION['async_email_script'];
		unset($_SESSION['async_email_script']);
	}
	// Show consultation message via unified modal if set
	if (isset($_SESSION['consultation_message'])) {
		$consultation_message = $_SESSION['consultation_message'];
		unset($_SESSION['consultation_message']);
		echo "\n<script>\n";
		echo "document.addEventListener('DOMContentLoaded', async function(){\n";
		echo "  var msg = " . json_encode($consultation_message) . ";\n";
		echo "  if (typeof ConfirmModal !== 'undefined') {\n";
		echo "    await ConfirmModal.alert({\n";
		echo "      title: 'Success',\n";
		echo "      message: msg,\n";
		echo "      type: 'success'\n";
		echo "    });\n";
		echo "  } else {\n";
		echo "    alert(msg);\n";
		echo "  }\n";
		echo "});\n";
		echo "</script>\n";
	}
	?>

	<!-- Load confirmation modal system -->
	<script src="../includes/confirmation-modal.js"></script>
</body>
</html>


