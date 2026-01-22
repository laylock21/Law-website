// ============================================
// CONSULTATION FORM - NO MODALS VERSION
// This file contains the complete updated code
// ============================================

// Add this code after the existing toast notification system

// ============================================
// FORM INITIALIZATION - NO MODALS
// ============================================

// Load practice areas into dropdown on page load
async function loadPracticeAreasIntoDropdown() {
	const serviceSelect = document.getElementById('service');
	if (!serviceSelect) return;
	
	try {
		const response = await fetch('api/get_all_practice_areas.php');
		const result = await response.json();
		
		if (result.success && result.practice_areas.length > 0) {
			serviceSelect.innerHTML = '<option value="">Select a practice area</option>';
			result.practice_areas.forEach(area => {
				const option = document.createElement('option');
				option.value = area.area_name;
				option.textContent = area.area_name;
				serviceSelect.appendChild(option);
			});
			console.log('Practice areas loaded:', result.practice_areas.length);
		}
	} catch (error) {
		console.error('Error loading practice areas:', error);
	}
}

// Load time slots into dropdown when date/lawyer changes
async function loadTimeSlotsIntoDropdown(date, lawyerName) {
	const timeSelect = document.getElementById('consultation-time');
	if (!timeSelect) return;
	
	timeSelect.innerHTML = '<option value="">Loading time slots...</option>';
	timeSelect.disabled = true;
	
	try {
		const lawyerNameForAPI = lawyerName.replace(/^Atty\.\s*/i, '');
		const lawyerSelectEl = document.getElementById('lawyer');
		const selectedOpt = lawyerSelectEl ? lawyerSelectEl.options[lawyerSelectEl.selectedIndex] : null;
		const lawyerIdParam = selectedOpt?.dataset?.lawyerId ? `&lawyer_id=${encodeURIComponent(selectedOpt.dataset.lawyerId)}` : '';
		
		const response = await fetch(`api/get_time_slots.php?lawyer=${encodeURIComponent(lawyerNameForAPI)}&date=${date}${lawyerIdParam}`);
		const result = await response.json();
		
		if (result.success && result.time_slots.length > 0) {
			timeSelect.innerHTML = '<option value="">Select a time slot</option>';
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
			console.log('Time slots loaded:', result.time_slots.length);
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

// Initialize form event listeners
function initializeConsultationForm() {
	// Load practice areas
	loadPracticeAreasIntoDropdown();
	
	const dateInput = document.getElementById('consultation-date');
	const lawyerSelect = document.getElementById('lawyer');
	const serviceSelect = document.getElementById('service');
	
	// When practice area changes, filter lawyers
	if (serviceSelect) {
		serviceSelect.addEventListener('change', async () => {
			const selectedPracticeArea = serviceSelect.value;
			if (selectedPracticeArea) {
				await filterLawyersByPracticeArea(selectedPracticeArea);
			} else {
				if (lawyerSelect) {
					lawyerSelect.innerHTML = '<option value="">First select a practice area</option>';
					lawyerSelect.disabled = true;
				}
			}
			
			// Reset time dropdown
			const timeSelect = document.getElementById('consultation-time');
			if (timeSelect) {
				timeSelect.innerHTML = '<option value="">First select a date</option>';
				timeSelect.disabled = true;
			}
		});
	}
	
	// When date changes, load time slots
	if (dateInput && lawyerSelect) {
		dateInput.addEventListener('change', () => {
			const selectedDate = dateInput.value;
			const selectedLawyer = lawyerSelect.value;
			if (selectedDate && selectedLawyer) {
				loadTimeSlotsIntoDropdown(selectedDate, selectedLawyer);
			}
		});
		
		// When lawyer changes, reload time slots if date is selected
		lawyerSelect.addEventListener('change', () => {
			const selectedDate = dateInput.value;
			const selectedLawyer = lawyerSelect.value;
			if (selectedDate && selectedLawyer) {
				loadTimeSlotsIntoDropdown(selectedDate, selectedLawyer);
			} else {
				const timeSelect = document.getElementById('consultation-time');
				if (timeSelect) {
					timeSelect.innerHTML = '<option value="">First select a date</option>';
					timeSelect.disabled = true;
				}
			}
		});
	}
}

// Filter lawyers by practice area (existing function - keep as is)
async function filterLawyersByPracticeArea(practiceArea) {
	const lawyerSelect = document.getElementById('lawyer');
	if (!lawyerSelect) return;
	
	try {
		lawyerSelect.innerHTML = '<option value="">Loading lawyers...</option>';
		lawyerSelect.disabled = true;
		
		const response = await fetch(`api/get_lawyers_by_specialization.php?specialization=${encodeURIComponent(practiceArea)}`);
		const result = await response.json();
		
		if (result.success && result.lawyers.length > 0) {
			lawyerSelect.innerHTML = '<option value="">Select a lawyer</option>';
			
			result.lawyers.forEach((lawyer) => {
				const option = document.createElement('option');
				const fullName = 'Atty. ' + lawyer.name;
				option.value = fullName;
				option.textContent = fullName;
				option.dataset.lawyerId = lawyer.id;
				lawyerSelect.appendChild(option);
			});
			
			lawyerSelect.disabled = false;
		} else {
			lawyerSelect.innerHTML = '<option value="">No lawyers available for this practice area</option>';
			lawyerSelect.disabled = true;
		}
	} catch (error) {
		console.error('Error filtering lawyers:', error);
		lawyerSelect.innerHTML = '<option value="">Error loading lawyers</option>';
		lawyerSelect.disabled = true;
	}
}

// Update review section to include time
function updateReviewSection() {
	// Personal Information
	const fullName = document.getElementById('fullName')?.value || '';
	document.getElementById('review-name').textContent = fullName || '-';
	document.getElementById('review-email').textContent = document.getElementById('email')?.value || '-';
	document.getElementById('review-phone').textContent = document.getElementById('phone')?.value || '-';
	
	// Consultation Details
	document.getElementById('review-lawyer').textContent = document.getElementById('lawyer')?.value || '-';
	document.getElementById('review-practice').textContent = document.getElementById('service')?.value || '-';
	
	// Date
	const selectedDate = document.getElementById('consultation-date')?.value;
	if (selectedDate) {
		const date = new Date(selectedDate + 'T00:00:00');
		document.getElementById('review-date').textContent = date.toLocaleDateString('en-US', { 
			weekday: 'long', 
			year: 'numeric', 
			month: 'long', 
			day: 'numeric' 
		});
	} else {
		document.getElementById('review-date').textContent = '-';
	}
	
	// Time
	const timeSelect = document.getElementById('consultation-time');
	if (timeSelect && timeSelect.value) {
		const selectedTimeText = timeSelect.selectedOptions[0]?.text || timeSelect.value;
		document.getElementById('review-time').textContent = selectedTimeText.replace('🟢 ', '');
	} else {
		document.getElementById('review-time').textContent = '-';
	}
	
	// Message
	const message = document.getElementById('message')?.value || '';
	document.getElementById('review-message').textContent = message.length > 100 
		? message.substring(0, 100) + '...' 
		: message || '-';
}

// Form submission handler - NO MODALS
const appointmentForm = document.getElementById('appointment-form');
if (appointmentForm) {
	appointmentForm.addEventListener('submit', async (e) => {
		e.preventDefault();
		
		const formData = new FormData(appointmentForm);
		const fullName = formData.get('fullName');
		const email = formData.get('email');
		const phone = formData.get('phone');
		const service = formData.get('service');
		const lawyer = formData.get('lawyer');
		const message = formData.get('message');
		const date = formData.get('date') || '';
		const selectedTime = formData.get('selected_time') || '';
		
		// Validation
		if (!fullName || !email || !phone || !service || !lawyer || !message) {
			showErrorToast('Please fill out all required fields.', 'Validation Error');
			return;
		}
		
		const validationErrors = [];
		if (fullName.trim().length < 3) validationErrors.push('Full name must be at least 3 characters');
		if (!validateEmail(email)) validationErrors.push('Please enter a valid email address');
		if (!validatePhone(phone)) validationErrors.push('Phone number must be exactly 11 digits');
		if (message.length < 10) validationErrors.push('Case description must be at least 10 characters');
		
		if (validationErrors.length > 0) {
			showErrorToast(validationErrors.join('\n• '), 'Validation Errors');
			return;
		}
		
		if (!date || date.trim() === '') {
			showErrorToast('Please select a consultation date.', 'Missing Date');
			return;
		}
		
		if (!selectedTime || selectedTime.trim() === '') {
			showErrorToast('Please select a consultation time.', 'Missing Time');
			return;
		}
		
		try {
			const submissionData = {
				fullName: fullName,
				email: email,
				phone: phone,
				service: service,
				message: message,
				lawyer: lawyer,
				date: date,
				selected_time: selectedTime
			};
			
			const response = await fetch('api/process_consultation.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(submissionData)
			});
			
			const result = await response.json();
			
			if (result.success) {
				showSuccessToast(
					`Thank you, ${fullName}! We've received your consultation request for ${service}. We'll contact you within 24 hours to confirm your appointment with ${lawyer} on ${date}.`,
					'Consultation Booked!'
				);
				
				if (result.email_queued) {
					triggerEmailProcessing();
				}
				
				// Reset form
				appointmentForm.reset();
				resetForm();
				
				// Reset dropdowns
				const lawyerSelect = document.getElementById('lawyer');
				const serviceSelect = document.getElementById('service');
				const timeSelect = document.getElementById('consultation-time');
				
				if (lawyerSelect) {
					lawyerSelect.innerHTML = '<option value="">First select a practice area</option>';
					lawyerSelect.disabled = true;
				}
				if (serviceSelect) {
					serviceSelect.value = '';
				}
				if (timeSelect) {
					timeSelect.innerHTML = '<option value="">First select a date</option>';
					timeSelect.disabled = true;
				}
			} else {
				showErrorToast(result.message || 'An error occurred. Please try again.', 'Submission Failed');
			}
		} catch (error) {
			console.error('Error:', error);
			showErrorToast('An error occurred while submitting your request. Please try again.', 'Submission Error');
		}
	});
}

// Helper functions (keep existing ones)
function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9]{11}$/;
    return phoneRegex.test(phone);
}

function triggerEmailProcessing() {
    console.log('Triggering email processing...');
    setTimeout(function() {
        fetch('api/process_emails_async.php', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(response => response.json())
        .then(data => {
            console.log('Email processing result:', data);
        }).catch(error => {
            console.error('Email processing error:', error);
        });
    }, 1000);
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
	initializeConsultationForm();
});


