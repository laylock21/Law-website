// ============================================
// UPDATED CONSULTATION FORM - NO MODALS
// ============================================

// This file contains the updated code to replace modal-based interactions
// with direct form interactions

// Remove all modal-related code and replace with direct form interactions

// 1. PRACTICE AREA - Load directly into dropdown (no modal)
async function loadPracticeAreasIntoDropdown() {
	const serviceSelect = document.getElementById('service');
	if (!serviceSelect) return;
	
	try {
		const response = await fetch('api/get_all_practice_areas.php');
		const result = await response.json();
		
		if (result.success && result.practice_areas.length > 0) {
			// Clear existing options except the first one
			serviceSelect.innerHTML = '<option value="">Select a practice area</option>';
			
			// Add all practice areas
			result.practice_areas.forEach(area => {
				const option = document.createElement('option');
				option.value = area.area_name;
				option.textContent = area.area_name;
				serviceSelect.appendChild(option);
			});
		}
	} catch (error) {
		console.error('Error loading practice areas:', error);
	}
}

// 2. TIME SLOTS - Load directly into dropdown when date changes (no modal)
async function loadTimeSlotsIntoDropdown(date, lawyerName) {
	const timeSelect = document.getElementById('consultation-time');
	if (!timeSelect) return;
	
	// Show loading state
	timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
	timeSelect.disabled = true;
	
	try {
		// Remove "Atty. " prefix for API call
		const lawyerNameForAPI = lawyerName.replace(/^Atty\.\s*/i, '');
		
		// Get lawyer ID
		const lawyerSelectEl = document.getElementById('lawyer');
		const selectedOpt = lawyerSelectEl ? lawyerSelectEl.options[lawyerSelectEl.selectedIndex] : null;
		const lawyerIdParam = selectedOpt?.dataset?.lawyerId ? `&lawyer_id=${encodeURIComponent(selectedOpt.dataset.lawyerId)}` : '';
		
		const response = await fetch(`api/get_time_slots.php?lawyer=${encodeURIComponent(lawyerNameForAPI)}&date=${date}${lawyerIdParam}`);
		const result = await response.json();
		
		if (result.success && result.time_slots.length > 0) {
			timeSelect.innerHTML = '<option value="">Select a time slot</option>';
			
			// Add all time slots
			result.time_slots.forEach(slot => {
				const option = document.createElement('option');
				if (slot.available) {
					option.value = slot.time_24h || slot.time || slot.start_time;
					option.textContent = `🟢 ${slot.display}`;
				} else {
					option.value = '';
					option.textContent = `🔴 ${slot.display} (Unavailable)`;
					option.disabled = true;
				}
				timeSelect.appendChild(option);
			});
			
			timeSelect.disabled = false;
		} else {
			timeSelect.innerHTML = '<option value="">No time slots available</option>';
			timeSelect.disabled = true;
		}
	} catch (error) {
		console.error('Error loading time slots:', error);
		timeSelect.innerHTML = '<option value="">Error loading time slots</option>';
		timeSelect.disabled = true;
	}
}

// 3. FORM SUBMISSION - Direct submission without modal confirmation
async function handleDirectFormSubmission(formData) {
	const appointmentStatus = document.getElementById('appointment-status');
	
	try {
		const response = await fetch('process_consultation.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(formData)
		});
		
		const result = await response.json();
		
		if (result.success) {
			// Show success message using toast instead of modal
			showSuccessToast(
				`Thank you, ${formData.fullName}! We've received your consultation request. We'll contact you within 24 hours.`,
				'Consultation Booked'
			);
			
			// Trigger email processing if emails were queued
			if (result.email_queued) {
				triggerEmailProcessing();
			}
			
			// Reset form
			const form = document.getElementById('appointment-form');
			if (form) {
				form.reset();
				resetForm();
			}
			
			return true;
		} else {
			showErrorToast(result.message || 'An error occurred. Please try again.', 'Submission Failed');
			return false;
		}
	} catch (error) {
		console.error('Error:', error);
		showErrorToast('An error occurred while submitting your request. Please try again.', 'Submission Error');
		return false;
	}
}

// 4. INITIALIZE - Set up event listeners
function initializeNoModalForm() {
	// Load practice areas into dropdown
	loadPracticeAreasIntoDropdown();
	
	// When date changes, load time slots
	const dateInput = document.getElementById('consultation-date');
	const lawyerSelect = document.getElementById('lawyer');
	
	if (dateInput && lawyerSelect) {
		dateInput.addEventListener('change', () => {
			const selectedDate = dateInput.value;
			const selectedLawyer = lawyerSelect.value;
			
			if (selectedDate && selectedLawyer) {
				loadTimeSlotsIntoDropdown(selectedDate, selectedLawyer);
			}
		});
		
		// Also reload time slots when lawyer changes
		lawyerSelect.addEventListener('change', () => {
			const selectedDate = dateInput.value;
			const selectedLawyer = lawyerSelect.value;
			
			if (selectedDate && selectedLawyer) {
				loadTimeSlotsIntoDropdown(selectedDate, selectedLawyer);
			} else {
				// Reset time dropdown
				const timeSelect = document.getElementById('consultation-time');
				if (timeSelect) {
					timeSelect.innerHTML = '<option value="">First select a date</option>';
					timeSelect.disabled = true;
				}
			}
		});
	}
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initializeNoModalForm);
} else {
	initializeNoModalForm();
}
