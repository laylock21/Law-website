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

// Handle status update (restricted to this lawyer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
	$new_status = $_POST['new_status'] ?? '';
	$cancellation_reason = $_POST['cancellation_reason'] ?? 'Lawyer decision';
	try {
		$pdo = getDBConnection();
		$check = $pdo->prepare('SELECT id, status, lawyer_id FROM consultations WHERE id = ? AND (lawyer_id = ? OR lawyer_id IS NULL)');
		$check->execute([$consultation_id, $lawyer_id]);
		$current_consultation = $check->fetch();
		
		if ($current_consultation && in_array($new_status, ['pending','confirmed','cancelled','completed'], true)) {
			$old_status = $current_consultation['status'];
			
			// Update status and cancellation reason if applicable
			if ($new_status === 'cancelled') {
				$upd = $pdo->prepare('UPDATE consultations SET status = ?, cancellation_reason = ? WHERE id = ?');
				$upd->execute([$new_status, $cancellation_reason, $consultation_id]);
			} else {
				// Clear cancellation reason if status is not cancelled
				$upd = $pdo->prepare('UPDATE consultations SET status = ?, cancellation_reason = NULL WHERE id = ?');
				$upd->execute([$new_status, $consultation_id]);
			}
			
			// Send email notifications for status changes
			require_once '../includes/EmailNotification.php';
			$emailNotification = new EmailNotification($pdo);
			$queued = false;
			
			if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
				$queued = $emailNotification->notifyAppointmentConfirmed($consultation_id);
			} elseif ($new_status === 'cancelled' && $old_status !== 'cancelled') {
				$queued = $emailNotification->notifyAppointmentCancelled($consultation_id, $cancellation_reason);
			} elseif ($new_status === 'completed' && $old_status !== 'completed') {
				// If consultation has no assigned lawyer, assign current lawyer
				if (!$current_consultation['lawyer_id']) {
					$assign_stmt = $pdo->prepare('UPDATE consultations SET lawyer_id = ? WHERE id = ?');
					$assign_stmt->execute([$lawyer_id, $consultation_id]);
				}
				$queued = $emailNotification->notifyAppointmentCompleted($consultation_id);
			}
			
			if ($queued) {
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
							console.log('Email sent successfully');
						}
					}).catch(error => {
						console.log('Email processing error:', error);
					});
				}, 100);
				</script>";
				
				$_SESSION['async_email_script'] = $async_script;
				$email_type = ($new_status === 'confirmed') ? 'Confirmation' : 
			             (($new_status === 'cancelled') ? 'Cancellation' : 'Completion');
				$_SESSION['consultation_message'] = "Status updated successfully! {$email_type} email sent to client.";
			} else {
				$_SESSION['consultation_message'] = 'Status updated successfully!';
			}
		} else {
			$error_message = 'You are not authorized to update this consultation.';
		}
	} catch (Exception $e) {
		$error_message = 'Error updating status: ' . $e->getMessage();
	}
}

// Load consultation details (restricted) - Include consultations assigned to this lawyer OR designated as 'Any' (lawyer_id IS NULL)
try {
	$pdo = getDBConnection();
	$stmt = $pdo->prepare('SELECT * FROM consultations WHERE id = ? AND (lawyer_id = ? OR lawyer_id IS NULL) LIMIT 1');
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
	<link rel="stylesheet" href="../styles.css">
	<link rel="stylesheet" href="styles.css">
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

			<?php if (isset($success_message)): ?>
				<div class="lawyer-alert lawyer-alert-success"><?php echo htmlspecialchars($success_message); ?></div>
			<?php endif; ?>
			<?php if (isset($error_message)): ?>
				<div class="lawyer-alert lawyer-alert-error"><?php echo htmlspecialchars($error_message); ?></div>
			<?php endif; ?>

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
					<form method="POST">
						<input type="hidden" name="action" value="update_status">
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
						});
						</script>
					</form>
				</div>
			<?php endif; ?>
		</main>
	</div>
	
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


