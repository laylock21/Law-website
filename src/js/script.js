// Mobile navigation toggle with improved animations
const navToggle = document.querySelector('.nav-toggle');
const navList = document.querySelector('.nav-list');
const navOverlay = document.getElementById('navOverlay');

if (navToggle && navList) {
	// Toggle menu function
	const toggleMenu = (forceClose = false) => {
		const isOpen = navList.classList.contains('open');
		
		if (forceClose || isOpen) {
			// Close menu
			navList.classList.remove('open');
			navToggle.classList.remove('active');
			if (navOverlay) navOverlay.classList.remove('active');
			navToggle.setAttribute('aria-expanded', 'false');
			document.body.style.overflow = '';
		} else {
			// Open menu
			navList.classList.add('open');
			navToggle.classList.add('active');
			if (navOverlay) navOverlay.classList.add('active');
			navToggle.setAttribute('aria-expanded', 'true');
			document.body.style.overflow = 'hidden';
		}
	};
	
	// Toggle button click
	navToggle.addEventListener('click', () => toggleMenu());
	
	// Overlay click - close menu
	if (navOverlay) {
		navOverlay.addEventListener('click', () => toggleMenu(true));
	}
	
	// Close menu when clicking on a link
	const navLinks = navList.querySelectorAll('a');
	navLinks.forEach(link => {
		link.addEventListener('click', () => toggleMenu(true));
	});
	
	// Close menu on escape key
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && navList.classList.contains('open')) {
			toggleMenu(true);
		}
	});
}

// Footer year
const yearEl = document.getElementById('year');
if (yearEl) yearEl.textContent = String(new Date().getFullYear());

// ============================================
// PRACTICE AREAS DYNAMIC LOADING
// ============================================

/**
 * Icon mapping for practice areas
 * Maps practice area names to appropriate Font Awesome icons
 */
const practiceAreaIcons = {
	'Criminal Defense': 'fa-gavel',
	'Criminal Law': 'fa-gavel',
	'Family Law': 'fa-users',
	'Corporate Law': 'fa-building',
	'Business Law': 'fa-building',
	'Real Estate': 'fa-home',
	'Real Estate Law': 'fa-home',
	'Health Care Law': 'fa-heartbeat',
	'Healthcare Law': 'fa-heartbeat',
	'Educational Law': 'fa-graduation-cap',
	'Education Law': 'fa-graduation-cap',
	'Immigration Law': 'fa-passport',
	'Tax Law': 'fa-file-invoice-dollar',
	'Intellectual Property': 'fa-lightbulb',
	'Employment Law': 'fa-briefcase',
	'Environmental Law': 'fa-leaf',
	'default': 'fa-balance-scale'
};

/**
 * Get icon class for a practice area
 * @param {string} areaName - Name of the practice area
 * @returns {string} Font Awesome icon class
 */
function getPracticeAreaIcon(areaName) {
	// Try exact match first
	if (practiceAreaIcons[areaName]) {
		return practiceAreaIcons[areaName];
	}
	
	// Try partial match
	for (const [key, icon] of Object.entries(practiceAreaIcons)) {
		if (areaName.toLowerCase().includes(key.toLowerCase())) {
			return icon;
		}
	}
	
	// Return default icon
	return practiceAreaIcons.default;
}

/**
 * Load and display practice areas from the database
 */
async function loadPracticeAreas() {
	const servicesGrid = document.getElementById('services-grid');
	const showMoreContainer = document.getElementById('show-more-container');
	const showMoreBtn = document.getElementById('show-more-btn');
	
	if (!servicesGrid) {
		console.error('Services grid element not found');
		return;
	}
	
	let allPracticeAreas = [];
	let isShowingAll = false;
	const INITIAL_DISPLAY_COUNT = 6;
	
	try {
		const response = await fetch('api/get_all_practice_areas.php');
		
		if (!response.ok) {
			throw new Error(`HTTP error! status: ${response.status}`);
		}
		
		const data = await response.json();
		
		if (data.success && data.practice_areas && data.practice_areas.length > 0) {
			allPracticeAreas = data.practice_areas;
			
			// Function to render practice areas
			const renderPracticeAreas = (count) => {
				// Clear grid
				servicesGrid.innerHTML = '';
				
				// Get areas to display
				const areasToDisplay = allPracticeAreas.slice(0, count);
				
				// Create service cards for each practice area
				areasToDisplay.forEach(area => {
					const iconClass = getPracticeAreaIcon(area.area_name);
					
					const serviceCard = document.createElement('div');
					serviceCard.className = 'service-card';
					serviceCard.innerHTML = `
						<div class="service-icon">
							<i class="fas ${iconClass}" style="font-size: 32px; color: white;"></i>
						</div>
						<h3>${escapeHtml(area.area_name)}</h3>
						<p>${escapeHtml(area.description || 'Expert legal services in ' + area.area_name)}</p>
					`;
					
					servicesGrid.appendChild(serviceCard);
				});
			};
			
			// Initial render - show only first 6
			renderPracticeAreas(INITIAL_DISPLAY_COUNT);
			
			// Show "Show More" button if there are more than 6 practice areas
			if (allPracticeAreas.length > INITIAL_DISPLAY_COUNT) {
				showMoreContainer.style.display = 'block';
				
				// Handle Show More button click
				showMoreBtn.addEventListener('click', () => {
					if (!isShowingAll) {
						// Show all practice areas
						renderPracticeAreas(allPracticeAreas.length);
						showMoreBtn.innerHTML = '<i class="fas fa-minus-circle me-2"></i>Show Less';
						isShowingAll = true;
					} else {
						// Show only first 6
						renderPracticeAreas(INITIAL_DISPLAY_COUNT);
						showMoreBtn.innerHTML = '<i class="fas fa-plus-circle me-2"></i>Show More Practice Areas';
						isShowingAll = false;
						
						// Scroll to services section
						document.getElementById('services').scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
				});
			}
			
			console.log(`Loaded ${allPracticeAreas.length} practice areas (showing ${Math.min(INITIAL_DISPLAY_COUNT, allPracticeAreas.length)} initially)`);
		} else {
			// No practice areas found
			servicesGrid.innerHTML = `
				<div class="service-card">
					<div class="service-icon">
						<i class="fas fa-info-circle" style="font-size: 32px; color: white;"></i>
					</div>
					<h3>No Practice Areas Available</h3>
					<p>We are currently updating our practice areas. Please check back soon.</p>
				</div>
			`;
		}
	} catch (error) {
		console.error('Error loading practice areas:', error);
		
		// Show error message
		servicesGrid.innerHTML = `
			<div class="service-card">
				<div class="service-icon">
					<i class="fas fa-exclamation-triangle" style="font-size: 32px; color: white;"></i>
				</div>
				<h3>Unable to Load Practice Areas</h3>
				<p>We're experiencing technical difficulties. Please try refreshing the page.</p>
			</div>
		`;
	}
}

/**
 * Escape HTML to prevent XSS attacks
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
function escapeHtml(text) {
	const div = document.createElement('div');
	div.textContent = text;
	return div.innerHTML;
}

// Load practice areas when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', loadPracticeAreas);
} else {
	loadPracticeAreas();
}

// ============================================
// TOAST NOTIFICATION SYSTEM
// ============================================

const toastContainer = document.getElementById('toastContainer');

// Icon mapping for different toast types
const toastIcons = {
    success: '<i class="fas fa-check-circle"></i>',
    error: '<i class="fas fa-times-circle"></i>',
    warning: '<i class="fas fa-exclamation-triangle"></i>',
    info: '<i class="fas fa-info-circle"></i>'
};

/**
 * Show a toast notification
 * @param {string} message - The message to display
 * @param {string} type - Type of toast: 'success', 'error', 'warning', 'info'
 * @param {number} duration - Duration in milliseconds (default: 5000)
 * @param {string} title - Optional title for the toast
 */
function showToast(message, type = 'info', duration = 5000, title = '') {
    if (!toastContainer) return;
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    // Build toast HTML
    const icon = toastIcons[type] || toastIcons.info;
    const titleHtml = title ? `<div class="toast-title">${title}</div>` : '';
    
    toast.innerHTML = `
        <div class="toast-icon">${icon}</div>
        <div class="toast-content">
            ${titleHtml}
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" aria-label="Close notification">×</button>
        <div class="toast-progress"></div>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Trigger show animation
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Close button functionality
    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => removeToast(toast));
    
    // Auto-remove after duration
    const autoRemoveTimer = setTimeout(() => removeToast(toast), duration);
    
    // Pause auto-remove on hover
    toast.addEventListener('mouseenter', () => clearTimeout(autoRemoveTimer));
    toast.addEventListener('mouseleave', () => {
        setTimeout(() => removeToast(toast), 1000);
    });
    
    return toast;
}

/**
 * Remove a toast notification
 * @param {HTMLElement} toast - The toast element to remove
 */
function removeToast(toast) {
    if (!toast || !toast.parentElement) return;
    
    toast.classList.add('hide');
    toast.classList.remove('show');
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.remove();
        }
    }, 400);
}

// Convenience functions for different toast types
function showSuccessToast(message, title = 'Success') {
    return showToast(message, 'success', 5000, title);
}

function showErrorToast(message, title = 'Error') {
    return showToast(message, 'error', 6000, title);
}

function showWarningToast(message, title = 'Warning') {
    return showToast(message, 'warning', 5000, title);
}

function showInfoToast(message, title = '') {
    return showToast(message, 'info', 4000, title);
}

// ============================================
// MICRO-INTERACTIONS HELPERS
// ============================================

/**
 * Show animated checkmark
 * @param {HTMLElement} container - Container to append checkmark to
 * @param {Function} callback - Optional callback after animation
 */
function showCheckmark(container, callback) {
    const checkmark = document.createElement('div');
    checkmark.innerHTML = `
        <svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
            <circle class="checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
            <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
        </svg>
    `;
    
    container.appendChild(checkmark);
    
    if (callback) {
        setTimeout(callback, 1200);
    }
    
    return checkmark;
}

/**
 * Show loading spinner
 * @param {HTMLElement} container - Container to append spinner to
 * @param {string} size - Size: 'small', 'medium', 'large'
 * @returns {HTMLElement} - The spinner element
 */
function showSpinner(container, size = 'medium') {
    const spinner = document.createElement('div');
    spinner.className = `spinner ${size === 'small' ? 'spinner-small' : size === 'large' ? 'spinner-large' : ''}`;
    container.appendChild(spinner);
    return spinner;
}

/**
 * Remove spinner
 * @param {HTMLElement} spinner - The spinner element to remove
 */
function removeSpinner(spinner) {
    if (spinner && spinner.parentElement) {
        spinner.remove();
    }
}

/**
 * Add shake animation to element (for errors)
 * @param {HTMLElement} element - Element to shake
 */
function shakeElement(element) {
    element.classList.add('shake');
    setTimeout(() => element.classList.remove('shake'), 500);
}

/**
 * Add success flash to element
 * @param {HTMLElement} element - Element to flash
 */
function flashSuccess(element) {
    element.classList.add('success-flash');
    setTimeout(() => element.classList.remove('success-flash'), 600);
}

/**
 * Add wiggle animation to element
 * @param {HTMLElement} element - Element to wiggle
 */
function wiggleElement(element) {
    element.classList.add('wiggle');
    setTimeout(() => element.classList.remove('wiggle'), 500);
}

/**
 * Animate number counting up
 * @param {HTMLElement} element - Element containing the number
 * @param {number} target - Target number
 * @param {number} duration - Animation duration in ms
 */
function animateCounter(element, target, duration = 2000) {
    const start = 0;
    const increment = target / (duration / 16);
    let current = start;
    
    element.classList.add('count-up');
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = Math.round(target);
            clearInterval(timer);
        } else {
            element.textContent = Math.round(current);
        }
    }, 16);
}

/**
 * Add ripple effect to button click
 * @param {Event} event - Click event
 */
function createRipple(event) {
    const button = event.currentTarget;
    const ripple = document.createElement('span');
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');
    
    button.appendChild(ripple);
    
    setTimeout(() => ripple.remove(), 600);
}

// ============================================
// PERFORMANCE UTILITIES
// ============================================

// Debounce function for performance
function debounce(func, wait = 10) {
	let timeout;
	return function executedFunction(...args) {
		const later = () => {
			clearTimeout(timeout);
			func(...args);
		};
		clearTimeout(timeout);
		timeout = setTimeout(later, wait);
	};
}

// Throttle function for scroll events
function throttle(func, limit = 16) {
	let inThrottle;
	return function(...args) {
		if (!inThrottle) {
			func.apply(this, args);
			inThrottle = true;
			setTimeout(() => inThrottle = false, limit);
		}
	};
}

// ============================================
// STICKY HEADER ON SCROLL (Optimized)
// ============================================

const header = document.querySelector('.site-header');
let lastScroll = 0;
let ticking = false;

// Use requestAnimationFrame for smooth performance
function updateHeader(currentScroll) {
	// Add scrolled class when scrolled down
	if (currentScroll > 50) {
		header.classList.add('scrolled');
	} else {
		header.classList.remove('scrolled');
	}
	
	// Optional: Hide header on scroll down, show on scroll up
	// Uncomment below if you want auto-hide behavior
	/*
	if (currentScroll > lastScroll && currentScroll > 200) {
		header.classList.add('hidden');
	} else {
		header.classList.remove('hidden');
	}
	*/
	
	lastScroll = currentScroll;
	ticking = false;
}

// Optimized scroll handler with requestAnimationFrame
window.addEventListener('scroll', () => {
	const currentScroll = window.pageYOffset;
	
	if (!ticking) {
		window.requestAnimationFrame(() => {
			updateHeader(currentScroll);
		});
		ticking = true;
	}
}, { passive: true }); // Passive listener for better scroll performance

// ============================================
// GET DIRECTIONS - Works on Mobile & Desktop
// ============================================

const getDirectionsBtn = document.getElementById('getDirectionsBtn');
if (getDirectionsBtn) {
	getDirectionsBtn.addEventListener('click', (e) => {
		e.preventDefault(); // Prevent default link behavior
		
		const address = '647 Gen. Luna Ave, Maly, San Mateo, 1850 Rizal, Philippines';
		const encodedAddress = encodeURIComponent(address);
		
		// Detect device type
		const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
		const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent);
		const isAndroid = /Android/.test(navigator.userAgent);
		
		if (isMobile) {
			// Mobile: Try to open in native app
			if (isIOS) {
				// iOS: Try Google Maps app first, fallback to Apple Maps
				window.location.href = `comgooglemaps://?q=${encodedAddress}`;
				setTimeout(() => {
					window.location.href = `maps://maps.google.com/maps?daddr=${encodedAddress}`;
				}, 500);
			} else if (isAndroid) {
				// Android: Open in Google Maps app with navigation
				window.location.href = `google.navigation:q=${encodedAddress}`;
			}
		} else {
			// Desktop: Open Google Maps in new tab
			const mapsUrl = `https://www.google.com/maps/dir/?api=1&destination=${encodedAddress}`;
			window.open(mapsUrl, '_blank', 'noopener,noreferrer');
		}
	});
}

// ============================================
// SCROLL ANIMATIONS
// ============================================

// ============================================
// OPTIMIZED INTERSECTION OBSERVER
// ============================================

// Intersection Observer for scroll animations (Optimized)
const observerOptions = {
    threshold: 0.1, // Trigger when 10% of element is visible
    rootMargin: '0px 0px -50px 0px' // Trigger slightly before element enters viewport
};

const animationObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            // Stop observing after animation for better performance
            animationObserver.unobserve(entry.target);
        }
    });
}, observerOptions);

// Lazy loading images observer
const lazyImageObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const img = entry.target;
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
            lazyImageObserver.unobserve(img);
        }
    });
}, {
    rootMargin: '50px' // Load images 50px before they enter viewport
});

// Initialize observers on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    // Observe animated elements
    const animatedElements = document.querySelectorAll(
        '.fade-in-up, .fade-in, .slide-in-left, .slide-in-right, .scale-in'
    );
    
    animatedElements.forEach(el => {
        animationObserver.observe(el);
    });
    
    // Observe lazy load images
    const lazyImages = document.querySelectorAll('img[data-src]');
    lazyImages.forEach(img => {
        lazyImageObserver.observe(img);
    });
    
});

// Load lawyers dynamically from database for Bootstrap carousel
async function loadLawyers() {
    const carouselInner = document.getElementById('lawyer-carousel-inner');
    const carouselIndicators = document.getElementById('lawyer-carousel-indicators');
    if (!carouselInner) {
        console.error('Carousel inner element not found');
        return;
    }

    console.log('Loading lawyers...');

    try {
        const response = await fetch('api/get_all_lawyers.php');
        console.log('Response status:', response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API Result:', result);

        if (result.success && result.lawyers.length > 0) {
            console.log(`Found ${result.lawyers.length} lawyers`);
            
            // Clear loading placeholder
            carouselInner.innerHTML = '';
            carouselIndicators.innerHTML = '';

            // Group lawyers into slides (responsive: 1 on mobile, 3 on desktop)
            const isMobile = window.innerWidth <= 768;
            const lawyersPerSlide = isMobile ? 1 : 3;
            const slides = [];
            
            for (let i = 0; i < result.lawyers.length; i += lawyersPerSlide) {
                slides.push(result.lawyers.slice(i, i + lawyersPerSlide));
            }

            console.log(`Creating ${slides.length} slides`);

            // Create carousel slides
            slides.forEach((slideData, slideIndex) => {
                const carouselItem = createCarouselSlide(slideData, slideIndex === 0);
                carouselInner.appendChild(carouselItem);

                // Create indicator
                const indicator = document.createElement('button');
                indicator.type = 'button';
                indicator.setAttribute('data-bs-target', '#lawyersCarousel');
                indicator.setAttribute('data-bs-slide-to', slideIndex.toString());
                indicator.setAttribute('aria-label', `Slide ${slideIndex + 1}`);
                if (slideIndex === 0) {
                    indicator.classList.add('active');
                    indicator.setAttribute('aria-current', 'true');
                }
                carouselIndicators.appendChild(indicator);
            });

            console.log('Lawyers loaded successfully');

            // Re-initialize book buttons after loading lawyers
            initializeBookButtons();
            
            // Initialize carousel preview effects
            initializeCarouselPreview();
        } else {
            console.warn('No lawyers found or API returned unsuccessful');
            carouselInner.innerHTML = `
                <div class="carousel-item active">
                    <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
                        <div class="no-lawyers text-center">
                            <p class="text-muted">No lawyers available at the moment.</p>
                        </div>
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading lawyers:', error);
        carouselInner.innerHTML = `
            <div class="carousel-item active">
                <div class="d-flex justify-content-center align-items-center" style="min-height: 400px;">
                    <div class="error-message text-center">
                        <p class="text-danger">Error loading legal team. Please try again later.</p>
                        <button class="btn btn-outline-primary mt-3" onclick="loadLawyers()">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
}

// Create carousel slide with multiple lawyer cards
function createCarouselSlide(lawyers, isActive = false) {
    const carouselItem = document.createElement('div');
    carouselItem.className = `carousel-item ${isActive ? 'active' : ''}`;
    
    const slideContent = `
        <div class="container">
            <div class="row justify-content-center">
                ${lawyers.map(lawyer => createLawyerCardHTML(lawyer)).join('')}
            </div>
        </div>
    `;
    
    carouselItem.innerHTML = slideContent;
    return carouselItem;
}

// Create lawyer card HTML for carousel
function createLawyerCardHTML(lawyer) {
    // Safely get profile picture URL with fallback
    const profilePicUrl = lawyer.profile_picture_url || 'src/img/default-avatar.png';
    
    // Safely get specializations with fallback
    const specializations = (lawyer.specializations && lawyer.specializations.length > 0) 
        ? lawyer.specializations 
        : ['General Practice'];
    
    // Safely get other fields with fallbacks
    const lawyerName = lawyer.name || 'Unknown Lawyer';
    const lawyerEmail = lawyer.email || 'N/A';
    const lawyerPhone = lawyer.phone || 'N/A';
    const lawyerDescRaw = lawyer.description || 'No description available';
    const lawyerId = lawyer.id || '';
    
    // Limit description to 150 characters with ellipsis
    const lawyerDesc = lawyerDescRaw.length > 150 
        ? lawyerDescRaw.substring(0, 150) + '...' 
        : lawyerDescRaw;

    // Create tags for front card (show first 2 + More)
    const frontTags = specializations.slice(0, 2).map(spec => 
        `<span class="lawprof-tag">${spec}</span>`
    ).join('');
    const moreTag = specializations.length > 2 ? '<span class="lawprof-tag lawprof-tag-more">+More</span>' : '';
    
    // Create tags for back card (show all)
    const backTags = specializations.map(spec => 
        `<span class="lawprof-tag">${spec}</span>`
    ).join('');

    return `
        <div class="col-md-4 col-sm-12 mb-4">
            <div class="lawprof-profile-card">
                <div class="lawprof-card-inner">
                    <div class="lawprof-card-front">
                        <div class="lawprof-profile-image-section">
                            <img src="${profilePicUrl}" alt="${lawyerName}" class="lawprof-profile-image"
                                 onerror="this.src='src/img/default-avatar.png'; this.onerror=null;" />
                        </div>
                        <div class="lawprof-profile-content">
                            <div class="lawprof-profile-name">${lawyerName}</div>
                            <div class="lawprof-tags-container">
                                ${frontTags}
                                ${moreTag}
                            </div>
                        </div>
                    </div>

                    <div class="lawprof-card-back">
                        <div class="lawprof-back-content">
                            <div class="lawprof-description-section">
                                <div class="lawprof-section-title">About</div>
                                <div class="lawprof-description-text">${lawyerDesc}</div>
                            </div>
                            <div class="lawprof-tags-section">
                                <div class="lawprof-tags-container">
                                    ${backTags}
                                </div>
                            </div>
                            <div class="lawprof-contact-section">
                                <div class="lawprof-section-title">Contact</div>
                                <div class="lawprof-contact-item-back">${lawyerEmail}</div>
                                <div class="lawprof-contact-item-back">${lawyerPhone}</div>
                            </div>
                            <button class="lawprof-book-btn-back book-btn" data-lawyer="${lawyerName}" data-lawyer-id="${lawyerId}">
                                Book Consultation
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Legacy function for backward compatibility (if needed elsewhere)
function createLawyerCard(lawyer) {
    const div = document.createElement('div');
    div.innerHTML = createLawyerCardHTML(lawyer);
    return div.firstElementChild;
}

// Initialize book buttons - simple direct approach
function initializeBookButtons() {
    const bookButtons = document.querySelectorAll('.book-btn');
    
    bookButtons.forEach((btn, index) => {
        const lawyer = btn.getAttribute('data-lawyer');
        const lawyerId = btn.getAttribute('data-lawyer-id');
        
        // Store data directly on the button element to avoid closure issues
        btn.dataset.lawyerName = lawyer;
        btn.dataset.lawyerIdValue = lawyerId;
        
        // Use onclick with data from dataset
        btn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get data from dataset (not from closure variables)
            const clickedLawyer = this.dataset.lawyerName || this.getAttribute('data-lawyer') || '';
            const clickedId = this.dataset.lawyerIdValue || this.getAttribute('data-lawyer-id') || '';
            
            
            handleBookConsultation(clickedLawyer, clickedId);
            return false;
        };
    });
}

// Handle book consultation logic
async function handleBookConsultation(lawyer, lawyerId) {
    // RESET FORM TO STEP 1 FIRST
    resetForm();
    
    // NEW LOGIC: With practice area first flow, we need to get lawyer's practice areas
    try {
        const lawyerSelect = document.getElementById('lawyer');
        const serviceSelect = document.getElementById('service');
        const practiceAreaBtn = document.getElementById('practiceAreaBtn');
        const practiceAreaDisplay = document.getElementById('practiceAreaDisplay');
        
        // Load all lawyers data to get this lawyer's specializations
        await loadAllLawyers();
        
        // Find the lawyer's primary practice area
        const lawyerData = allLawyersData.find(l => l.name === lawyer);
        
        if (lawyerData && lawyerData.primary_specialization) {
            // Auto-select the lawyer's primary practice area
            const primaryArea = lawyerData.primary_specialization;
            
            // Set practice area
            serviceSelect.value = primaryArea;
            practiceAreaDisplay.textContent = primaryArea;
            
            // Filter lawyers by this practice area
            await filterLawyersByPracticeArea(primaryArea);
            
            // Wait a moment for DOM to update
            setTimeout(() => {
                // Select the lawyer
                lawyerSelect.value = lawyer;
                lawyerSelect.dispatchEvent(new Event('change'));
            }, 300);
        }
    } catch (error) {
        console.error('Error pre-selecting lawyer:', error);
    }
    
    // Scroll to appointment section
    const appointmentSection = document.getElementById('appointment');
    if (appointmentSection) {
        const headerHeight = 80;
        const targetPosition = appointmentSection.offsetTop - headerHeight;
        window.scrollTo({
            top: targetPosition,
            behavior: 'smooth'
        });
    }
    
    // Update status message
    const appointmentStatus = document.getElementById('appointment-status');
    if (appointmentStatus) {
        appointmentStatus.textContent = `✓ ${lawyer} pre-selected. Complete the form and select an available date.`;
        appointmentStatus.style.color = '#28a745'; // Green color for success
    }
}

// Load lawyers on page load
document.addEventListener('DOMContentLoaded', loadLawyers);

// Reload carousel on window resize to adjust for mobile/desktop
let resizeTimeout;
let wasMobile = window.innerWidth <= 768;

window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
        const isMobile = window.innerWidth <= 768;
        // Only reload if crossing the mobile/desktop threshold
        if (wasMobile !== isMobile) {
            wasMobile = isMobile;
            loadLawyers();
        }
    }, 250);
});

// Feature: Dynamic lawyer availability from database
let lawyerAvailability = {};
let lawyersBySpecialization = {};

// Helper function to check if a date is available for a specific lawyer
function isDateAvailableForLawyer(date, lawyerName) {
    if (!lawyerName || !lawyerAvailability[lawyerName]) {
        return false;
    }
    
    const dateStr = date.toISOString().split('T')[0]; // Format: YYYY-MM-DD
    return lawyerAvailability[lawyerName].availableDays.includes(dateStr);
}

// Appointment form submit with database integration
const appointmentForm = document.getElementById('appointment-form');
const appointmentStatus = document.getElementById('appointment-status');
// Modal elements
const statusModal = document.getElementById('status-modal');
const statusModalClose = document.getElementById('status-modal-close');
const statusModalOk = document.getElementById('status-modal-ok');
const statusModalMessage = document.getElementById('status-modal-message');

// Get submit button for date validation
const submitButton = appointmentForm ? appointmentForm.querySelector('button[type="submit"]') : null;

function openStatusModal(message) {
    if (!statusModal) return;
    if (statusModalMessage) statusModalMessage.textContent = message;
    statusModal.classList.add('open');
}

function closeStatusModal() {
    if (!statusModal) return;
    statusModal.classList.remove('open');
}

// Function to update submit button state based on date selection
function updateSubmitButtonState() {
    if (!submitButton) return;
    
    const selectedDate = document.getElementById('selected-date').value;
    const calendar = document.querySelector('.calendar');
    const validationNote = document.getElementById('validation-note');
    
    if (selectedDate && selectedDate.trim() !== '') {
        submitButton.disabled = false;
        submitButton.classList.remove('btn-disabled');
        submitButton.classList.add('btn-primary');
        
        // Update calendar visual state
        if (calendar) {
            calendar.classList.remove('no-date-selected');
            calendar.classList.add('date-selected');
        }
        
        // Update validation note
        if (validationNote) {
            validationNote.textContent = 'Date selected! You can now submit your consultation request.';
            validationNote.classList.add('valid');
        }
    } else {
        submitButton.disabled = true;
        submitButton.classList.remove('btn-primary');
        submitButton.classList.add('btn-disabled');
        
        // Update calendar visual state
        if (calendar) {
            calendar.classList.remove('date-selected');
            calendar.classList.add('no-date-selected');
        }
        
        // Update validation note
        if (validationNote) {
            validationNote.textContent = 'Please select a date to enable form submission';
            validationNote.classList.remove('valid');
        }
    }
}

// Function to clear selected date and update button state
function clearSelectedDate() {
    const selectedDateDisplay = document.getElementById('selected-date-display');
    const hiddenDateInput = document.getElementById('selected-date');
    
    if (selectedDateDisplay) selectedDateDisplay.textContent = 'None';
    if (hiddenDateInput) hiddenDateInput.value = '';
    
    // Remove selected class from all calendar buttons
    const calendarButtons = document.querySelectorAll('.calendar-day button[data-date]');
    calendarButtons.forEach(btn => btn.classList.remove('selected'));
    
    // Clear status message
    if (appointmentStatus) {
        appointmentStatus.textContent = '';
        appointmentStatus.style.color = '';
    }
    
    // Update submit button state (this will also update validation note)
    updateSubmitButtonState();
}

if (statusModalClose) statusModalClose.addEventListener('click', closeStatusModal);
if (statusModalOk) statusModalOk.addEventListener('click', closeStatusModal);
if (statusModal) {
    window.addEventListener('click', (e) => {
        if (e.target === statusModal) closeStatusModal();
    });
}

if (appointmentForm && appointmentStatus) {
	appointmentForm.addEventListener('submit', async (e) => {
		e.preventDefault();
		const formData = new FormData(appointmentForm);
		// Feature: Using single full name field
		const fullName = formData.get('fullName');
		const email = formData.get('email');
		const phone = formData.get('phone');
		const service = formData.get('service');
		const lawyer = formData.get('lawyer');
		const message = formData.get('message');
		const date = formData.get('date') || '';
		const selectedTime = formData.get('selected_time') || '';
		
		
		// Feature: Enhanced validation for all required fields
		if (!fullName || !email || !phone || !service || !lawyer || !message) {
			openStatusModal('Please fill out all required fields.');
			return;
		}
		
		// Enhanced validation with detailed error messages
		const validationErrors = [];
		
		if (fullName.trim().length < 3) {
			validationErrors.push('Full name must be at least 3 characters');
		}
		if (!validateEmail(email)) {
			validationErrors.push('Please enter a valid email address');
		}
		if (!validatePhone(phone)) {
			validationErrors.push('Phone number must be exactly 11 digits');
		}
		if (message.length < 10) {
			validationErrors.push('Case description must be at least 10 characters');
		}
		
		if (validationErrors.length > 0) {
			openStatusModal('Please fix the following errors:\n• ' + validationErrors.join('\n• '));
			return;
		}
		
		// Check if date is selected
		if (!date || date.trim() === '') {
			openStatusModal('Please select a consultation date from the calendar.');
			return;
		}
		
		// Validate that the selected date is available for the chosen lawyer
		const selectedLawyer = formData.get('lawyer');
		if (selectedLawyer && !isDateAvailableForLawyer(new Date(date), selectedLawyer)) {
			openStatusModal('The selected date is not available for the chosen lawyer. Please select a different date.');
			return;
		}
		
		if (appointmentStatus) {
			appointmentStatus.textContent = 'Sending consultation request...';
			appointmentStatus.style.color = '#6c757d';
		}
		
		try {
			// Feature: Prepare data with full name field and selected time
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
			
			
			// Submit to PHP backend
			const response = await fetch('api/process_consultation.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify(submissionData)
			});
			
			const result = await response.json();
			
			if (result.success) {
				// Success message
				openStatusModal(`✅ Booking Successful!\n\nThank you, ${fullName}! Your consultation has been booked with ${lawyer} for ${service} on ${date}.\n\nWe'll send you a confirmation email shortly and contact you within 24 hours to finalize the details.`);
				
				// Trigger email processing if emails were queued
				if (result.email_queued) {
					triggerEmailProcessing();
				}
				
				appointmentForm.reset();
				document.getElementById('selected-date-display').textContent = 'None';
				document.getElementById('selected-date').value = '';
				
				// Reset to step 1
				currentStep = 1;
				showStep(currentStep);
				updateButtons();
				
				// Reset practice area and lawyer dropdowns (NEW LOGIC: practice area first, then lawyer)
				const lawyerSelect = document.getElementById('lawyer');
				const serviceSelect = document.getElementById('service');
				const practiceAreaBtn = document.getElementById('practiceAreaBtn');
				const practiceAreaDisplay = document.getElementById('practiceAreaDisplay');
				
				if (lawyerSelect) {
					lawyerSelect.innerHTML = '<option value="">First select a practice area</option>';
					lawyerSelect.disabled = true;
				}
				
				if (serviceSelect) {
					serviceSelect.value = '';
				}
				
				if (practiceAreaBtn) {
					practiceAreaBtn.disabled = false;
				}
				
				if (practiceAreaDisplay) {
					practiceAreaDisplay.textContent = 'Click to select practice area';
				}
				
				// Clear calendar
				clearSelectedDate();
				
				// Clear status message
				if (appointmentStatus) {
					appointmentStatus.textContent = '';
					appointmentStatus.style.color = '';
				}
				
				// Update submit button state after form reset
				updateSubmitButtonState();
			} else {
				openStatusModal(result.message || 'An error occurred. Please try again.');
			}
		} catch (error) {
			console.error('Error:', error);
			openStatusModal('An error occurred while submitting your request. Please try again.');
		}
	});
	
	// Add form reset event listener to handle manual form resets
	appointmentForm.addEventListener('reset', () => {
		// Clear selected date and update button state
		clearSelectedDate();
	});
}

// Enhanced input validation and sanitization
function sanitizeInput(input) {
    return input.trim().replace(/[<>]/g, '');
}

function validateEmail(email) {
    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return emailRegex.test(email);
}

function validatePhone(phone) {
    const phoneRegex = /^[0-9]{11}$/;
    return phoneRegex.test(phone);
}

function validateName(name) {
    const nameRegex = /^[a-zA-Z\s'-]{2,50}$/;
    return nameRegex.test(name);
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) existingError.remove();
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.style.color = '#dc3545';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
    
    field.style.borderColor = '#dc3545';
}

function clearFieldError(fieldId) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) existingError.remove();
    field.style.borderColor = '';
}

// Real-time validation for all form fields
function setupFieldValidation() {
    const fields = {
        'firstName': { validator: validateName, message: 'First name must be 2-50 characters, letters only' },
        'lastName': { validator: validateName, message: 'Last name must be 2-50 characters, letters only' },
        'middleName': { validator: (name) => name === '' || validateName(name), message: 'Middle name must be 2-50 characters, letters only' },
        'email': { validator: validateEmail, message: 'Please enter a valid email address' },
        'phone': { validator: validatePhone, message: 'Phone number must be exactly 11 digits' },
        'message': { validator: (msg) => msg.length >= 10, message: 'Case description must be at least 10 characters' }
    };

    Object.keys(fields).forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', () => {
                const value = sanitizeInput(field.value);
                field.value = value;
                
                if (value && !fields[fieldId].validator(value)) {
                    showFieldError(fieldId, fields[fieldId].message);
                } else {
                    clearFieldError(fieldId);
                }
            });
            
            field.addEventListener('input', () => {
                clearFieldError(fieldId);
            });
        }
    });
}

// Enforce numeric-only phone input (blocks non-digits on type and paste)
const phoneInput = document.getElementById('phone');
if (phoneInput) {
    // On input: strip non-digits and cap to maxlength
    phoneInput.addEventListener('input', () => {
        const max = phoneInput.getAttribute('maxlength') ? parseInt(phoneInput.getAttribute('maxlength'), 10) : null;
        let digitsOnly = phoneInput.value.replace(/\D+/g, '');
        if (max && digitsOnly.length > max) digitsOnly = digitsOnly.slice(0, max);
        if (phoneInput.value !== digitsOnly) phoneInput.value = digitsOnly;
    });

    // On paste: prevent non-digit paste
    phoneInput.addEventListener('paste', (e) => {
        const text = (e.clipboardData || window.clipboardData).getData('text');
        if (/\D/.test(text)) {
            e.preventDefault();
            const cleaned = text.replace(/\D+/g, '').slice(0, phoneInput.maxLength || 999);
            document.execCommand('insertText', false, cleaned);
        }
    });

    // On keydown: allow only control keys and digits
    phoneInput.addEventListener('keydown', (e) => {
        const allowedKeys = ['Backspace','Delete','Tab','ArrowLeft','ArrowRight','Home','End'];
        if (allowedKeys.includes(e.key)) return;
        // Allow Ctrl/Cmd + A/C/V/X
        if ((e.ctrlKey || e.metaKey) && ['a','c','v','x'].includes(e.key.toLowerCase())) return;
        if (!/^[0-9]$/.test(e.key)) e.preventDefault();
    });
}

// Initialize field validation when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    setupFieldValidation();
    
    // Initialize form auto-save
    const appointmentForm = document.getElementById('appointment-form');
    if (appointmentForm) {
        new FormAutoSave('appointment-form', {
            fields: ['lastName', 'firstName', 'middleName', 'email', 'phone', 'service', 'lawyer', 'message'],
            saveInterval: 30000 // Save every 30 seconds
        });
    }
    
    // Ensure form elements are interactive
    ensureFormInteractivity();
});

// Function to ensure form elements are interactive
function ensureFormInteractivity() {
    const formElements = [
        'lastName', 'firstName', 'middleName', 'email', 'phone', 
        'service', 'lawyer', 'message'
    ];
    
    formElements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            // Remove any disabled state except for lawyer select (which should be enabled by service selection)
            if (id !== 'lawyer') {
                element.disabled = false;
            }
            
            // Ensure pointer events are enabled
            element.style.pointerEvents = 'auto';
            
        } else {
            console.warn(`Form element ${id} not found`);
        }
    });
    
}

// Feature: PRACTICE AREA FIRST - Practice area to lawyer selection logic with database integration
const serviceSelect = document.getElementById('service');
const lawyerSelect = document.getElementById('lawyer');
const practiceAreaBtn = document.getElementById('practiceAreaBtn');
const practiceAreaDisplay = document.getElementById('practiceAreaDisplay');
let practiceAreaModal;
let availablePracticeAreas = [];
let allLawyersData = []; // Store all lawyers data for filtering

// Initialize practice area modal
document.addEventListener('DOMContentLoaded', () => {
	const modalElement = document.getElementById('practiceAreaModal');
	if (modalElement && typeof bootstrap !== 'undefined') {
		practiceAreaModal = new bootstrap.Modal(modalElement);
		
		// Setup search functionality
		const searchInput = document.getElementById('practiceAreaSearch');
		if (searchInput) {
			searchInput.addEventListener('input', filterPracticeAreas);
		}
	}
	
	// Load all practice areas on page load
	loadAllPracticeAreas();
});

// Open practice area modal
if (practiceAreaBtn) {
	practiceAreaBtn.addEventListener('click', () => {
		if (!practiceAreaBtn.disabled && availablePracticeAreas.length > 0) {
			populatePracticeAreaModal();
			practiceAreaModal.show();
		}
	});
}

// Load all practice areas from database
async function loadAllPracticeAreas() {
	try {
		const response = await fetch('api/get_all_practice_areas.php');
		const result = await response.json();
		
		if (result.success && result.practice_areas.length > 0) {
			availablePracticeAreas = result.practice_areas.map(area => area.area_name);
			console.log('Loaded practice areas:', availablePracticeAreas);
		} else {
			availablePracticeAreas = [];
			console.error('No practice areas found');
		}
	} catch (error) {
		console.error('Error loading practice areas:', error);
		availablePracticeAreas = [];
	}
}

// Populate practice area modal with available areas
function populatePracticeAreaModal() {
	const listContainer = document.getElementById('practiceAreaList');
	const noResultsMsg = document.getElementById('noPracticeAreasMessage');
	const searchInput = document.getElementById('practiceAreaSearch');
	
	if (!listContainer) return;
	
	// Clear search
	if (searchInput) searchInput.value = '';
	
	listContainer.innerHTML = '';
	
	if (availablePracticeAreas.length === 0) {
		noResultsMsg.style.display = 'block';
		listContainer.style.display = 'none';
		return;
	}
	
	noResultsMsg.style.display = 'none';
	listContainer.style.display = 'block';
	
	availablePracticeAreas.forEach(area => {
		const item = document.createElement('div');
		item.className = 'practice-area-item';
		if (serviceSelect.value === area) {
			item.classList.add('selected');
		}
		item.innerHTML = `<i class="fas fa-balance-scale"></i>${area}`;
		item.addEventListener('click', () => selectPracticeArea(area));
		listContainer.appendChild(item);
	});
}

// Filter practice areas based on search
function filterPracticeAreas() {
	const searchInput = document.getElementById('practiceAreaSearch');
	const listContainer = document.getElementById('practiceAreaList');
	const noResultsMsg = document.getElementById('noPracticeAreasMessage');
	
	if (!searchInput || !listContainer) return;
	
	const searchTerm = searchInput.value.toLowerCase();
	const items = listContainer.querySelectorAll('.practice-area-item');
	let visibleCount = 0;
	
	items.forEach(item => {
		const text = item.textContent.toLowerCase();
		if (text.includes(searchTerm)) {
			item.style.display = 'block';
			visibleCount++;
		} else {
			item.style.display = 'none';
		}
	});
	
	if (visibleCount === 0) {
		noResultsMsg.style.display = 'block';
		listContainer.style.display = 'none';
	} else {
		noResultsMsg.style.display = 'none';
		listContainer.style.display = 'block';
	}
}

// Select a practice area and filter lawyers
async function selectPracticeArea(area) {
	serviceSelect.value = area;
	practiceAreaDisplay.textContent = area;
	
	// Update selected state in modal
	const items = document.querySelectorAll('.practice-area-item');
	items.forEach(item => {
		if (item.textContent.trim() === area) {
			item.classList.add('selected');
		} else {
			item.classList.remove('selected');
		}
	});
	
	// Close modal
	practiceAreaModal.hide();
	
	// Filter lawyers by selected practice area
	await filterLawyersByPracticeArea(area);
	
	// Clear calendar and date selection
	clearSelectedDate();
	
	// Update calendar to show combined availability of filtered lawyers
	await updateCalendarForPracticeArea(area);
}

// Filter lawyers by practice area
async function filterLawyersByPracticeArea(practiceArea) {
	if (!lawyerSelect) return;
	
	try {
		lawyerSelect.innerHTML = '<option value="">Loading lawyers...</option>';
		lawyerSelect.disabled = true;
		
		const response = await fetch(`api/get_lawyers_by_specialization.php?specialization=${encodeURIComponent(practiceArea)}`);
		const result = await response.json();
		
		if (result.success && result.lawyers.length > 0) {
			lawyerSelect.innerHTML = '<option value="">Select a lawyer</option>';
			
			// Fetch full lawyer details for each lawyer
			const lawyerDetailsPromises = result.lawyers.map(async (lawyer) => {
				try {
					const detailResponse = await fetch('api/get_all_lawyers.php');
					const detailResult = await detailResponse.json();
					if (detailResult.success) {
						return detailResult.lawyers.find(l => l.id === lawyer.id);
					}
				} catch (error) {
					console.error('Error fetching lawyer details:', error);
				}
				return null;
			});
			
			const lawyerDetails = await Promise.all(lawyerDetailsPromises);
			
			result.lawyers.forEach((lawyer, index) => {
				const option = document.createElement('option');
				// Use the name from API which already includes the prefix from database
				const fullName = lawyer.name;
				option.value = fullName;
				option.textContent = fullName;
				option.dataset.lawyerId = lawyer.id;
				
				// Add specialization data if available
				const details = lawyerDetails[index];
				if (details) {
					option.dataset.specialization = details.primary_specialization;
					option.dataset.specializations = JSON.stringify(details.specializations || [details.primary_specialization]);
				}
				
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

// Update calendar to show combined availability for practice area
async function updateCalendarForPracticeArea(practiceArea) {
	try {
		// Get all lawyers for this practice area
		const response = await fetch(`api/get_lawyers_by_specialization.php?specialization=${encodeURIComponent(practiceArea)}`);
		const result = await response.json();
		
		if (result.success && result.lawyers.length > 0) {
			// Fetch availability for all lawyers
			const availabilityPromises = result.lawyers.map(async (lawyer) => {
				try {
					const lawyerName = lawyer.name;
					const availResponse = await fetch(`api/get_lawyer_availability.php?lawyer=${encodeURIComponent(lawyerName)}&lawyer_id=${encodeURIComponent(lawyer.id)}`);
					const availResult = await availResponse.json();
					
					if (availResult.success) {
						return {
							lawyer: lawyerName, // Use name as-is from API (already includes prefix)
							dates: availResult.available_dates,
							dateStatusMap: availResult.date_status_map || {}
						};
					}
				} catch (error) {
					console.error('Error fetching availability for lawyer:', lawyer.name, error);
				}
				return null;
			});
			
			const availabilities = await Promise.all(availabilityPromises);
			
			// Combine all available dates from all lawyers
			const combinedDates = new Set();
			availabilities.forEach(avail => {
				if (avail && avail.dates) {
					avail.dates.forEach(date => combinedDates.add(date));
				}
			});
			
			// Store availability for each lawyer with their status maps
			availabilities.forEach(avail => {
				if (avail) {
					lawyerAvailability[avail.lawyer] = {
						availableDays: avail.dates,
						dateStatusMap: avail.dateStatusMap
					};
				}
			});
			
			// Store combined availability for the practice area
			lawyerAvailability['_practiceArea_' + practiceArea] = {
				availableDays: Array.from(combinedDates),
				dateStatusMap: {} // Combined view doesn't show detailed status
			};
			
			console.log('Combined availability for', practiceArea, ':', Array.from(combinedDates));
			
			// Re-render calendar with combined availability
			window.renderCalendar();
		}
	} catch (error) {
		console.error('Error updating calendar for practice area:', error);
	}
}

// Load all lawyers on page load (for reference, not displayed initially)
async function loadAllLawyers() {
	try {
		const response = await fetch('api/get_all_lawyers.php');
		const result = await response.json();
		
		if (result.success && result.lawyers.length > 0) {
			allLawyersData = result.lawyers;
			console.log('Loaded all lawyers data:', allLawyersData.length);
		}
	} catch (error) {
		console.error('Error loading lawyers:', error);
	}
}

// When lawyer is selected, update calendar with that specific lawyer's availability
if (lawyerSelect) {
	lawyerSelect.addEventListener('change', async () => {
		const selectedOption = lawyerSelect.options[lawyerSelect.selectedIndex];
		const selectedLawyer = lawyerSelect.value;
		
		if (selectedOption && selectedLawyer) {
			// Clear previous date selection
			clearSelectedDate();
			
			// Show loading state for calendar
			const calendarContainer = document.querySelector('.appointment-calendar');
			const loaderId = loadingManager.show(calendarContainer, 'Loading availability...');
			
			try {
				// Use the full lawyer name as-is (includes prefix from database)
				const lawyerNameForAPI = selectedLawyer;
				
				// Fetch lawyer availability from database
				const lawyerIdForAPI = selectedOption.dataset.lawyerId || '';
				
				console.log('Fetching availability for:', lawyerNameForAPI, 'ID:', lawyerIdForAPI);
				
				const response = await fetch(`api/get_lawyer_availability.php?lawyer=${encodeURIComponent(lawyerNameForAPI)}&lawyer_id=${encodeURIComponent(lawyerIdForAPI)}`);
				const result = await response.json();
				
				// Hide loading state
				loadingManager.hide(loaderId);
				
				if (result.success) {
					// Update lawyer availability data with detailed status map
					lawyerAvailability[selectedLawyer] = {
						availableDays: result.available_dates,
						dateStatusMap: result.date_status_map || {} // NEW: Store complete status map
					};
					
					console.log('Stored availability for', selectedLawyer, ':', lawyerAvailability[selectedLawyer]);
					
					// Re-render calendar with lawyer's availability
					window.renderCalendar();
				} else {
					console.error('Error fetching availability:', result.message);
					// Re-render calendar with no availability
					lawyerAvailability[selectedLawyer] = { 
						availableDays: [],
						dateStatusMap: {}
					};
					window.renderCalendar();
				}
			} catch (error) {
				console.error('Error fetching lawyer availability:', error);
				// Re-render calendar with no availability
				lawyerAvailability[selectedLawyer] = { 
					availableDays: [],
					dateStatusMap: {}
				};
				window.renderCalendar();
			}
		} else {
			// No lawyer selected, show practice area availability
			const selectedPracticeArea = serviceSelect.value;
			if (selectedPracticeArea) {
				// Show combined availability for practice area
				window.renderCalendar();
			}
		}
	});
}

// Load all lawyers data when page loads (for filtering)
loadAllLawyers();

// Note: Lawyer booking buttons are now handled dynamically in initializeBookButtons() function

// Feature: Calendar generation with lawyer availability
(function initCalendar() {
	const bodyEl = document.getElementById('calendar-body');
	const titleEl = document.getElementById('calendar-title');
	const selectedDateEl = document.getElementById('selected-date-display');
	const hiddenDateInput = document.getElementById('selected-date');
	if (!bodyEl || !titleEl || !selectedDateEl || !hiddenDateInput) return;

	let current = new Date();
	current.setDate(1);

// Feature: Global render function for calendar updates
window.renderCalendar = function() {
		const month = current.getMonth();
		const year = current.getFullYear();
		titleEl.textContent = current.toLocaleString('default', { month: 'long', year: 'numeric' });

		const firstDay = new Date(year, month, 1).getDay();
		const daysInMonth = new Date(year, month + 1, 0).getDate();
		const selectedLawyer = document.getElementById('lawyer')?.value;
		const selectedPracticeArea = document.getElementById('service')?.value;

		const fragments = [];
		for (let i = 0; i < firstDay; i++) {
			fragments.push(`<div class="calendar-day" aria-disabled="true"></div>`);
		}
		
		for (let d = 1; d <= daysInMonth; d++) {
			const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
			
			// Check date status based on selection
			let dateStatus = 'unavailable'; // Default status
			let isClickable = false;
			
			// Get today's date at midnight for comparison
			const today = new Date();
			today.setHours(0, 0, 0, 0);
			const dateToCheck = new Date(dateStr + 'T00:00:00');
			
			// Disable today and past dates
			const isPast = dateToCheck <= today;
			
			if (isPast) {
				dateStatus = 'past';
			} else if (selectedLawyer) {
				// If lawyer is selected, use their specific date status map
				const lawyerData = lawyerAvailability[selectedLawyer];
				if (lawyerData && lawyerData.dateStatusMap && lawyerData.dateStatusMap[dateStr]) {
					const status = lawyerData.dateStatusMap[dateStr].status;
					dateStatus = status;
					isClickable = (status === 'available');
				} else if (lawyerData && lawyerData.availableDays && lawyerData.availableDays.includes(dateStr)) {
					// Fallback to old logic if status map not available
					dateStatus = 'available';
					isClickable = true;
				}
			} else if (selectedPracticeArea) {
				// If only practice area is selected, show combined availability
				const practiceAreaKey = '_practiceArea_' + selectedPracticeArea;
				if (lawyerAvailability[practiceAreaKey]?.availableDays.includes(dateStr)) {
					dateStatus = 'available';
					isClickable = true;
				}
			}
			
			// Feature: Highlight dates with different colors based on status
			const buttonClass = dateStatus;
			const disabled = !isClickable;
			
			fragments.push(
				`<div class="calendar-day ${buttonClass}" role="gridcell">
					<button type="button" data-date="${dateStr}" ${disabled ? 'disabled' : ''}>${d}</button>
				</div>`
			);
		}
		bodyEl.innerHTML = fragments.join('');

		// Feature: Add click handlers for available dates - syncs with date input field
		Array.from(bodyEl.querySelectorAll('button[data-date]:not([disabled])')).forEach((btn) => {
			btn.addEventListener('click', () => {
				const selectedDate = btn.getAttribute('data-date') || '';
				
				// Update the date input field
				const dateInput = document.getElementById('consultation-date');
				if (dateInput) {
					dateInput.value = selectedDate;
					// Trigger change event to load time slots
					dateInput.dispatchEvent(new Event('change', { bubbles: true }));
				}
				
				// Update the hidden date input for review section
				const hiddenDateInput = document.getElementById('selected-date');
				if (hiddenDateInput) {
					hiddenDateInput.value = selectedDate;
				}
				
				// Update the display text
				const displayEl = document.getElementById('selected-date-display');
				if (displayEl) {
					const date = new Date(selectedDate + 'T00:00:00');
					displayEl.textContent = date.toLocaleDateString('en-US', { 
						weekday: 'short', 
						year: 'numeric', 
						month: 'short', 
						day: 'numeric' 
					});
				}
				
				// Visual feedback - highlight selected date
				bodyEl.querySelectorAll('button[data-date]').forEach(b => b.classList.remove('selected'));
				btn.classList.add('selected');
				
				// Check form completion
				if (typeof checkCurrentStepCompletion === 'function') {
					checkCurrentStepCompletion();
				}
			});
		});
	};

	// Initial render
	window.renderCalendar();

	// Feature: Navigation with availability updates
	const navButtons = document.querySelectorAll('.cal-nav');
	navButtons.forEach((b) => {
		b.addEventListener('click', () => {
			const dir = b.getAttribute('data-dir');
			if (dir === 'prev') {
				current.setMonth(current.getMonth() - 1);
			} else {
				current.setMonth(current.getMonth() + 1);
			}
			window.renderCalendar(); // Use global function
		});
	});

	// Initialize submit button state
	updateSubmitButtonState();
})();

// Smooth scrolling for all internal links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
	anchor.addEventListener('click', function (e) {
		e.preventDefault();
		const target = document.querySelector(this.getAttribute('href'));
		if (target) {
			const headerHeight = 80;
			const targetPosition = target.offsetTop - headerHeight;
			window.scrollTo({
				top: targetPosition,
				behavior: 'smooth'
			});
		}
	});
});

// Scroll effect is now handled by throttled version below

// Close mobile menu when clicking outside
document.addEventListener('click', (e) => {
	if (!navToggle.contains(e.target) && !navList.contains(e.target)) {
		navList.classList.remove('open');
		navToggle.setAttribute('aria-expanded', 'false');
	}
});

// Add loading animation to form submission
if (appointmentForm) {
	appointmentForm.addEventListener('submit', () => {
		const submitBtn = appointmentForm.querySelector('button[type="submit"]');
		if (submitBtn) {
			submitBtn.innerHTML = '<span>Sending...</span>';
			submitBtn.disabled = true;
			
			// Re-enable button after form processing and update state
			setTimeout(() => {
				submitBtn.innerHTML = 'Schedule Consultation';
				updateSubmitButtonState(); // This will properly set the button state based on date selection
			}, 2000);
		}
	});
}

// Loading States and Progress Indicators
class LoadingManager {
    constructor() {
        this.activeLoaders = new Set();
    }
    
    show(element, message = 'Loading...') {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        
        if (!element) return;
        
        const loaderId = element.id || 'loader_' + Date.now();
        this.activeLoaders.add(loaderId);
        
        // Create loading overlay
        const overlay = document.createElement('div');
        overlay.className = 'loading-overlay';
        overlay.id = loaderId + '_overlay';
        overlay.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <div class="loading-text">${message}</div>
            </div>
        `;
        
        // Add styles if not already present
        if (!document.getElementById('loading-styles')) {
            const styles = document.createElement('style');
            styles.id = 'loading-styles';
            styles.textContent = `
                .loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(255, 255, 255, 0.9);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    border-radius: inherit;
                }
                .loading-spinner {
                    text-align: center;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid var(--gold, #C5A253);
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 10px;
                }
                .loading-text {
                    color: var(--navy, #0B1D3A);
                    font-weight: 500;
                    font-size: 14px;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(styles);
        }
        
        // Make parent element relative positioned
        const computedStyle = window.getComputedStyle(element);
        if (computedStyle.position === 'static') {
            element.style.position = 'relative';
        }
        
        element.appendChild(overlay);
        return loaderId;
    }
    
    hide(loaderId) {
        if (typeof loaderId === 'string') {
            const overlay = document.getElementById(loaderId + '_overlay');
            if (overlay) {
                overlay.remove();
                this.activeLoaders.delete(loaderId);
            }
        }
    }
    
    hideAll() {
        this.activeLoaders.forEach(loaderId => {
            this.hide(loaderId);
        });
    }
}

// Global loading manager instance
const loadingManager = new LoadingManager();

// Initialize carousel with smooth slide transitions
function initializeCarouselPreview() {
    const carousel = document.getElementById('lawyersCarousel');
    if (!carousel) return;

    // Configure Bootstrap carousel for slide transitions (NO AUTO-CYCLE)
    const bsCarousel = new bootstrap.Carousel(carousel, {
        interval: false, // Disable auto-cycling
        wrap: true,
        touch: true,
        pause: false, // No need to pause since there's no auto-cycle
        keyboard: true
    });

    // Add smooth slide transition event handling
    carousel.addEventListener('slide.bs.carousel', function (e) {
        const activeItem = carousel.querySelector('.carousel-item.active');
        const nextItem = e.relatedTarget;
        
        if (activeItem && nextItem) {
            // Ensure 1-second slide transition
            activeItem.style.transition = 'transform 1s ease-in-out';
            nextItem.style.transition = 'transform 1s ease-in-out';
        }
    });

    carousel.addEventListener('slid.bs.carousel', function (e) {
        // Clean up after transition
        const items = carousel.querySelectorAll('.carousel-item');
        items.forEach(item => {
            item.style.transition = '';
        });
        
        // Re-initialize book buttons for the new active slide
        initializeBookButtons();
    });

    // Auto-cycle removed - carousel is now manual only
}

// Form Auto-Save System
class FormAutoSave {
    constructor(formId, options = {}) {
        this.form = document.getElementById(formId);
        this.storageKey = options.storageKey || `form_autosave_${formId}`;
        this.saveInterval = options.saveInterval || 30000; // 30 seconds
        this.fields = options.fields || [];
        this.saveTimer = null;
        
        if (this.form) {
            this.init();
        }
    }
    
    init() {
        // Load saved data
        this.loadSavedData();
        
        // Set up auto-save timer
        this.startAutoSave();
        
        // Save on form change
        this.form.addEventListener('input', () => {
            this.saveData();
        });
        
        // Clear saved data on successful submission
        this.form.addEventListener('submit', () => {
            this.clearSavedData();
        });
        
        // Save on page unload
        window.addEventListener('beforeunload', () => {
            this.saveData();
        });
    }
    
    saveData() {
        const formData = new FormData(this.form);
        const data = {};
        
        // Save only specified fields or all fields
        const fieldsToSave = this.fields.length > 0 ? this.fields : Array.from(formData.keys());
        
        fieldsToSave.forEach(fieldName => {
            const field = this.form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                data[fieldName] = field.value;
            }
        });
        
        // Add timestamp
        data._timestamp = Date.now();
        
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(data));
            this.showSaveIndicator();
        } catch (e) {
            console.warn('Could not save form data:', e);
        }
    }
    
    loadSavedData() {
        try {
            const savedData = localStorage.getItem(this.storageKey);
            if (savedData) {
                const data = JSON.parse(savedData);
                
                // Check if data is not too old (24 hours)
                const maxAge = 24 * 60 * 60 * 1000; // 24 hours
                if (Date.now() - data._timestamp < maxAge) {
                    Object.keys(data).forEach(fieldName => {
                        if (fieldName !== '_timestamp') {
                            const field = this.form.querySelector(`[name="${fieldName}"]`);
                            if (field && !field.value) { // Only fill empty fields
                                field.value = data[fieldName];
                            }
                        }
                    });
                    
                    this.showRestoreIndicator();
                } else {
                    this.clearSavedData();
                }
            }
        } catch (e) {
            console.warn('Could not load saved form data:', e);
        }
    }
    
    clearSavedData() {
        localStorage.removeItem(this.storageKey);
    }
    
    startAutoSave() {
        this.saveTimer = setInterval(() => {
            this.saveData();
        }, this.saveInterval);
    }
    
    stopAutoSave() {
        if (this.saveTimer) {
            clearInterval(this.saveTimer);
            this.saveTimer = null;
        }
    }
    
    showSaveIndicator() {
        // Silent auto-save - no popup notification
        // Data is still being saved to localStorage
    }
    
    showRestoreIndicator() {
        // Silent restore - no popup notification
        // Data is still being restored from localStorage
    }
}

// Performance optimizations
// Debounce function for API calls
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for scroll events
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

// Optimize scroll event with throttling
window.addEventListener('scroll', throttle(() => {
    const header = document.querySelector('.site-header');
    if (header) {
        if (window.scrollY > 100) {
            header.style.boxShadow = '0 4px 30px rgba(11, 29, 58, 0.15)';
        } else {
            header.style.boxShadow = '0 2px 20px rgba(11, 29, 58, 0.1)';
        }
    }
}, 100));

// Service Worker registration for caching
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/Law-website/src/js/sw.js')
            .then(registration => {
            })
            .catch(registrationError => {
            });
    });
}

// ============================================
// TIME SLOT SELECTION FUNCTIONALITY
// ============================================

let selectedTimeSlot = null;
let currentSelectedDate = null;
let currentSelectedLawyer = null;

// Open time slot selection modal
async function openTimeSlotModal(date, lawyerName) {
    currentSelectedDate = date;
    currentSelectedLawyer = lawyerName;
    selectedTimeSlot = null;
    
    // Update modal display info
    document.getElementById('selectedDateDisplay').textContent = new Date(date).toLocaleDateString();
    document.getElementById('selectedLawyerDisplay').textContent = lawyerName;
    
    // Reset dropdown and selected slot info
    const selectElement = document.getElementById('timeSlotSelect');
    if (selectElement) {
        selectElement.value = '';
    }
    const selectedSlotInfo = document.getElementById('selectedSlotInfo');
    if (selectedSlotInfo) {
        selectedSlotInfo.style.display = 'none';
    }
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('timeSlotModal'));
    modal.show();
    
    // Show loading state
    document.getElementById('timeSlotsLoading').style.display = 'block';
    document.getElementById('timeSlotsContainer').style.display = 'none';
    document.getElementById('noTimeSlotsMessage').style.display = 'none';
    document.getElementById('confirmTimeSlot').disabled = true;
    
    try {
        // Use the full lawyer name as-is (includes prefix from database)
        const lawyerNameForAPI = lawyerName;
        
        console.log('Fetching time slots for:', lawyerNameForAPI, 'on date:', date);
        
        // Fetch time slots from API (include lawyer_id for precise matching)
        const lawyerSelectEl = document.getElementById('lawyer');
        const selectedOpt = lawyerSelectEl ? lawyerSelectEl.options[lawyerSelectEl.selectedIndex] : null;
        const lawyerIdParam = selectedOpt?.dataset?.lawyerId ? `&lawyer_id=${encodeURIComponent(selectedOpt.dataset.lawyerId)}` : '';
        const response = await fetch(`api/get_time_slots.php?lawyer=${encodeURIComponent(lawyerNameForAPI)}&date=${date}${lawyerIdParam}`);
        const result = await response.json();
        
        console.log('Time slots response:', result);
        
        if (result.success && result.time_slots.length > 0) {
            displayTimeSlots(result.time_slots, {
                remaining: typeof result.slots_remaining === 'number' ? result.slots_remaining : undefined,
                total: typeof result.max_appointments === 'number' ? result.max_appointments : undefined
            });
        } else {
            showNoTimeSlotsMessage(result.message || 'No time slots available');
        }
    } catch (error) {
        console.error('Error fetching time slots:', error);
        showNoTimeSlotsMessage('Error loading time slots. Please try again.');
    }
}

// Display time slots in the modal - Dropdown version
function displayTimeSlots(timeSlots, meta = {}) {
    document.getElementById('timeSlotsLoading').style.display = 'none';
    document.getElementById('timeSlotsContainer').style.display = 'block';
    document.getElementById('noTimeSlotsMessage').style.display = 'none';

    const selectElement = document.getElementById('timeSlotSelect');
    selectElement.innerHTML = '<option value="">Select a time slot</option>';

    // Update header count preferring aggregate values from API response
    let remainingFromAPI;
    let totalFromAPI;
    if (typeof meta.remaining === 'number' && typeof meta.total === 'number') {
        remainingFromAPI = meta.remaining;
        totalFromAPI = meta.total;
    } else {
        // Fallback to per-slot fields if meta not provided
        const firstSlot = timeSlots[0] || {};
        remainingFromAPI = (typeof firstSlot.slots_remaining === 'number')
            ? firstSlot.slots_remaining
            : timeSlots.filter(s => s.available).length; // fallback
        totalFromAPI = (typeof firstSlot.max_appointments === 'number')
            ? firstSlot.max_appointments
            : remainingFromAPI; // fallback
    }
    const countEl = document.getElementById('availableSlotsCount');
    if (countEl) {
        countEl.textContent = `(${remainingFromAPI} of ${totalFromAPI} available)`;
    }

    // Populate dropdown with both available and not-available slots
    // Use green dot (🟢) for available and red dot (🔴) for not available
    timeSlots.forEach(slot => {
        const option = document.createElement('option');
        option.value = slot.available ? JSON.stringify(slot) : '';
        const indicator = slot.available ? '🟢' : '🔴';
        option.textContent = `${indicator} ${slot.display}`;
        if (!slot.available) {
            option.disabled = true; // cannot select unavailable slots
        }
        selectElement.appendChild(option);
    });

    // Ensure only one change listener is active
    selectElement.onchange = handleTimeSlotSelection;
}

// Handle time slot selection from dropdown
function handleTimeSlotSelection(event) {
    const selectElement = event.target;
    const selectedValue = selectElement.value;
    
    if (selectedValue) {
        selectedTimeSlot = JSON.parse(selectedValue);
        
        // Show selected slot info
        const infoDisplay = document.getElementById('selectedSlotInfo');
        const infoText = document.getElementById('selectedSlotText');
        infoText.textContent = `Selected: ${selectedTimeSlot.display}`;
        infoDisplay.style.display = 'flex';
        infoDisplay.style.alignItems = 'center';
        
        // Enable confirm button
        document.getElementById('confirmTimeSlot').disabled = false;
    } else {
        selectedTimeSlot = null;
        document.getElementById('selectedSlotInfo').style.display = 'none';
        document.getElementById('confirmTimeSlot').disabled = true;
    }
}

// Legacy function for button-based selection (kept for compatibility)
function selectTimeSlot(slot, buttonElement) {
    // Remove previous selection
    document.querySelectorAll('.time-slot-button.selected').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Select current slot
    buttonElement.classList.add('selected');
    selectedTimeSlot = slot;
    
    // Enable confirm button
    document.getElementById('confirmTimeSlot').disabled = false;
}

// Show no time slots message
function showNoTimeSlotsMessage(message) {
    document.getElementById('timeSlotsLoading').style.display = 'none';
    document.getElementById('timeSlotsContainer').style.display = 'none';
    document.getElementById('noTimeSlotsMessage').style.display = 'block';
    document.getElementById('noTimeSlotsMessage').innerHTML = `
        <i class="fas fa-exclamation-triangle"></i>
        <strong>No available time slots</strong><br>
        ${message}
    `;
}

// Confirm time slot selection
document.getElementById('confirmTimeSlot').addEventListener('click', async () => {
    if (selectedTimeSlot && currentSelectedDate) {
        // Update the calendar display
        const calendarButtons = document.querySelectorAll('.calendar-day button[data-date]');
        calendarButtons.forEach(btn => btn.classList.remove('selected'));
        
        const selectedButton = document.querySelector(`button[data-date="${currentSelectedDate}"]`);
        if (selectedButton) {
            selectedButton.classList.add('selected');
        }
        
        // Update form fields
        const selectedDateEl = document.getElementById('selected-date-display');
        const hiddenDateInput = document.getElementById('selected-date');
        const hiddenTimeInput = document.getElementById('selected-time') || createHiddenTimeInput();
        
        if (selectedDateEl) {
            selectedDateEl.textContent = new Date(currentSelectedDate).toLocaleDateString() + 
                                       ' at ' + selectedTimeSlot.display;
        }
        
        if (hiddenDateInput) {
            hiddenDateInput.value = currentSelectedDate;
        }
        
        if (hiddenTimeInput) {
            // Try different possible field names from API
            const timeValue = selectedTimeSlot.time_24h || selectedTimeSlot.time || selectedTimeSlot.start_time;
            hiddenTimeInput.value = timeValue;
            console.log('Setting time value:', timeValue, 'from slot:', selectedTimeSlot);
        }
        
        // Close modal first
        const modal = bootstrap.Modal.getInstance(document.getElementById('timeSlotModal'));
        modal.hide();
        
        // Now submit the form directly
        const appointmentForm = document.getElementById('appointment-form');
        const formData = new FormData(appointmentForm);
        
        // Get form values
        const lastName = formData.get('lastName');
        const fullName = formData.get('fullName');
        const email = formData.get('email');
        const phone = formData.get('phone');
        const service = formData.get('service');
        const lawyer = formData.get('lawyer');
        const message = formData.get('message');
        const date = hiddenDateInput.value;
        const selectedTime = hiddenTimeInput.value;
        
        // Validate all required fields
        if (!fullName || !email || !phone || !service || !lawyer || !message) {
            openStatusModal('Please fill out all required fields.');
            return;
        }
        
        // Enhanced validation with detailed error messages
        const validationErrors = [];
        
        if (fullName.trim().length < 3) {
            validationErrors.push('Full name must be at least 3 characters');
        }
        if (!validateEmail(email)) {
            validationErrors.push('Please enter a valid email address');
        }
        if (!validatePhone(phone)) {
            validationErrors.push('Phone number must be exactly 11 digits');
        }
        if (message.length < 10) {
            validationErrors.push('Case description must be at least 10 characters');
        }
        
        if (validationErrors.length > 0) {
            openStatusModal('Please fix the following errors:\n• ' + validationErrors.join('\n• '));
            return;
        }
        
        // Check if date is selected
        if (!date || date.trim() === '') {
            openStatusModal('Please select a consultation date from the calendar.');
            return;
        }
        
        try {
            // Prepare data with full name field and selected time
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
            
            // Submit to PHP backend
            const response = await fetch('api/process_consultation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(submissionData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Show success message
                openStatusModal(`Thank you, ${fullName}! We've received your consultation request for ${service}. We'll contact you within 24 hours to confirm your appointment with ${lawyer} on ${date}.`);
                
                // Trigger email processing if emails were queued
                if (result.email_queued) {
                    triggerEmailProcessing();
                }
                
                // Reset form
                appointmentForm.reset();
                document.getElementById('selected-date-display').textContent = 'None';
                document.getElementById('selected-date').value = '';
                
                // Reset to step 1
                currentStep = 1;
                showStep(currentStep);
                
                // Reset practice area and lawyer dropdowns
                const lawyerSelect = document.getElementById('lawyer');
                const serviceSelect = document.getElementById('service');
                const practiceAreaBtn = document.getElementById('practiceAreaBtn');
                const practiceAreaDisplay = document.getElementById('practiceAreaDisplay');
                
                if (lawyerSelect) {
                    lawyerSelect.innerHTML = '<option value="">First select a practice area</option>';
                    lawyerSelect.disabled = true;
                }
                if (serviceSelect) {
                    serviceSelect.value = '';
                }
                if (practiceAreaBtn) {
                    practiceAreaBtn.disabled = false;
                }
                if (practiceAreaDisplay) {
                    practiceAreaDisplay.textContent = 'Click to select practice area';
                }
                
                // Clear calendar selection
                const calendarButtons = document.querySelectorAll('.calendar-day button[data-date]');
                calendarButtons.forEach(btn => btn.classList.remove('selected'));
                currentSelectedDate = null;
                
            } else {
                openStatusModal('Error: ' + (result.message || 'Failed to submit consultation request. Please try again.'));
            }
        } catch (error) {
            console.error('Submission error:', error);
            openStatusModal('An error occurred while submitting your request. Please try again later.');
        }
    }
});

// Reset modal when closed
const timeSlotModal = document.getElementById('timeSlotModal');
if (timeSlotModal) {
    timeSlotModal.addEventListener('hidden.bs.modal', () => {
        // Reset dropdown
        const selectElement = document.getElementById('timeSlotSelect');
        if (selectElement) {
            selectElement.value = '';
        }
        
        // Hide selected slot info
        const selectedSlotInfo = document.getElementById('selectedSlotInfo');
        if (selectedSlotInfo) {
            selectedSlotInfo.style.display = 'none';
        }
        
        // Reset selected time slot
        selectedTimeSlot = null;
        
        // Disable confirm button
        const confirmButton = document.getElementById('confirmTimeSlot');
        if (confirmButton) {
            confirmButton.disabled = true;
        }
    });
}

// Create hidden time input if it doesn't exist
function createHiddenTimeInput() {
    const existingInput = document.getElementById('selected-time');
    if (existingInput) return existingInput;
    
    const timeInput = document.createElement('input');
    timeInput.type = 'hidden';
    timeInput.id = 'selected-time';
    timeInput.name = 'selected_time';
    
    const form = document.getElementById('appointment-form');
    if (form) {
        form.appendChild(timeInput);
    }
    
    return timeInput;
}

// Function to trigger email processing
function triggerEmailProcessing() {
    console.log('Triggering email processing...');
    setTimeout(function() {
        fetch('api/process_emails_async.php', {
            method: 'POST',
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        }).then(response => response.json())
        .then(data => {
            console.log('Email processing result:', data);
            if (data.sent > 0) {
                console.log(`Successfully sent ${data.sent} emails`);
            }
            if (data.failed > 0) {
                console.warn(`Failed to send ${data.failed} emails`);
            }
        }).catch(error => {
            console.error('Email processing error:', error);
        });
    }, 1000);
}


// ============================================
// MULTI-STEP FORM NAVIGATION
// ============================================

let currentStep = 1;
const totalSteps = 3; // Changed from 4 to 3

const persBtn = document.getElementById('persBtn');
const lawBtn = document.getElementById('lawBtn');
const sumBtn = document.getElementById('sumBtn');
const submitBtn = document.getElementById('submitBtn');
const validationNote = document.getElementById('validation-note');

// Reset form to initial state
function resetForm() {
	// Reset to step 1
	currentStep = 1;
	showStep(1);
	
	// Clear all form inputs
	const form = document.getElementById('appointment-form');
	if (form) {
		form.reset();
	}
	
	// Clear all input borders
	document.querySelectorAll('.form-step input, .form-step select, .form-step textarea').forEach(input => {
		input.style.borderColor = '#e0e0e0';
	});
	
	// Reset validation note
	if (validationNote) {
		validationNote.textContent = 'Please complete all required fields';
		validationNote.style.color = '#666666';
		validationNote.classList.remove('valid');
	}
	
	// Scroll to top of form
	const formContainer = document.querySelector('.appointment-single-column');
	if (formContainer) {
		formContainer.scrollIntoView({ 
			behavior: 'smooth', 
			block: 'start' 
		});
	}
}

// Initialize form
function initMultiStepForm() {
	showStep(currentStep);
	updateButtons();
	// Check initial completion status
	checkCurrentStepCompletion();
}

// Update review section with form data
function updateReviewSection() {
	// Personal Information
	const fullName = document.getElementById('fullName')?.value || '';
	
	document.getElementById('review-name').textContent = fullName || '-';
	document.getElementById('review-email').textContent = document.getElementById('email')?.value || '-';
	document.getElementById('review-phone').textContent = document.getElementById('phone')?.value || '-';
	
	// Consultation Details
	document.getElementById('review-lawyer').textContent = document.getElementById('lawyer')?.value || '-';
	document.getElementById('review-practice').textContent = document.getElementById('service')?.value || '-';
	
	const selectedDate = document.getElementById('selected-date')?.value;
	if (selectedDate) {
		const date = new Date(selectedDate);
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
	const selectedTime = document.getElementById('consultation-time')?.value;
	if (selectedTime) {
		document.getElementById('review-time').textContent = selectedTime;
	} else {
		document.getElementById('review-time').textContent = '-';
	}
	
	const message = document.getElementById('message')?.value || '';
	document.getElementById('review-message').textContent = message || '-';
}

// Show specific step
function showStep(step) {
	const steps = document.querySelectorAll('.form-step');
	const progressSteps = document.querySelectorAll('.progress-step');
	
	// Update current step FIRST
	currentStep = step;
	
	// Hide all steps
	steps.forEach(s => s.classList.remove('active'));
	
	// Show current step
	const currentStepEl = document.querySelector(`.form-step[data-step="${step}"]`);
	if (currentStepEl) {
		currentStepEl.classList.add('active');
	}
	
	// Update progress indicator
	progressSteps.forEach((ps, index) => {
		const stepNum = index + 1;
		ps.classList.remove('active', 'completed');
		
		if (stepNum < step) {
			ps.classList.add('completed');
		} else if (stepNum === step) {
			ps.classList.add('active');
		}
	});
	
	// If moving to step 3 (review), update review section
	if (step === 3) {
		updateReviewSection();
		// For review step, show a different message
		const validationNote = document.getElementById('validation-note');
		if (validationNote) {
			validationNote.innerHTML = '<i class="fas fa-info-circle"></i> Review your information and click Confirm & Submit';
			validationNote.classList.remove('valid', 'warning', 'error');
		}
	} else {
		// For other steps, check completion status
		checkCurrentStepCompletion();
	}
	
	updateButtons();
}

// Validate current step
function validateStep(step) {
	const currentStepEl = document.querySelector(`.form-step[data-step="${step}"]`);
	if (!currentStepEl) return false;
	
	const inputs = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
	let isValid = true;
	let errorMessages = [];
	
	inputs.forEach(input => {
		// Skip disabled inputs and hidden inputs
		if (input.disabled || input.type === 'hidden') return;
		
		// Check if empty
		if (!input.value.trim()) {
			isValid = false;
			input.style.borderColor = '#dc3545';
			const label = currentStepEl.querySelector(`label[for="${input.id}"]`);
			if (label) {
				errorMessages.push(label.textContent.replace('*', '').trim());
			}
		} else {
			// Validate pattern if exists
			if (input.pattern) {
				const regex = new RegExp(input.pattern);
				if (!regex.test(input.value)) {
					isValid = false;
					input.style.borderColor = '#dc3545';
				} else {
					input.style.borderColor = '#C5A253';
				}
			} else {
				input.style.borderColor = '#C5A253';
			}
		}
	});
	
	// Special validation for step 2 - check if date and time are selected
	if (step === 2) {
		const dateInput = document.getElementById('consultation-date');
		const timeInput = document.getElementById('consultation-time');
		
		// Check date
		if (!dateInput || !dateInput.value.trim()) {
			isValid = false;
			if (!errorMessages.includes('Consultation Date')) {
				errorMessages.push('Consultation Date');
			}
		}
		
		// Check time (hidden input that gets populated by time slot selection)
		if (!timeInput || !timeInput.value.trim()) {
			isValid = false;
			if (!errorMessages.includes('Consultation Time')) {
				errorMessages.push('Consultation Time');
			}
		}
	}
	
	// Update validation note
	if (!isValid) {
		validationNote.innerHTML = `<i class="fas fa-times-circle"></i> Please complete: ${errorMessages.join(', ')}`;
		validationNote.classList.remove('valid', 'warning');
		validationNote.classList.add('error');
	} else {
		validationNote.innerHTML = '<i class="fas fa-check-circle"></i> Step completed! Click Next to continue.';
		validationNote.classList.remove('error', 'warning');
		validationNote.classList.add('valid');
	}
	
	return isValid;
}

// Next button click
if (lawBtn) {
	lawBtn.addEventListener('click', () => {
		if (validateStep(currentStep)) {
			if (currentStep < totalSteps) {
				showStep(currentStep + 1);
				// Scroll to top of form
				const container = document.querySelector('.appointment-single-column');
				if (container) {
					container.scrollIntoView({ 
						behavior: 'smooth', 
						block: 'start' 
					});
				}
			}
		} else {
			// Shake the form to indicate error
			const formContainer = document.querySelector('.appointment-single-column');
			if (formContainer) {
				formContainer.classList.add('shake');
				setTimeout(() => formContainer.classList.remove('shake'), 500);
			}
		}
	});
}

// Lawyer and date button click //
if (sumBtn) {
	sumBtn.addEventListener('click', () => {
		if (validateStep(currentStep)) {
			if (currentStep < totalSteps) {
				showStep(currentStep + 1);
				// Scroll to top of form
				const container = document.querySelector('.appointment-single-column');
				if (container) {
					container.scrollIntoView({ 
						behavior: 'smooth', 
						block: 'start' 
					});
				}
			}
		} else {
			// Shake the form to indicate error
			const formContainer = document.querySelector('.appointment-single-column');
			if (formContainer) {
				formContainer.classList.add('shake');
				setTimeout(() => formContainer.classList.remove('shake'), 500);
			}
		}
	});
}

// Personal info button click
if (persBtn) {
	persBtn.addEventListener('click', () => {
		if (currentStep > 1) {
			showStep(currentStep - 1);
			// Scroll to top of form
			const container = document.querySelector('.appointment-single-column');
			if (container) {
				container.scrollIntoView({ 
					behavior: 'smooth', 
					block: 'start' 
					});
			}
		}
	});
}

// New Navigation Buttons
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');

if (prevBtn) {
	prevBtn.addEventListener('click', () => {
		if (currentStep > 1) {
			showStep(currentStep - 1);
			updateButtons();
			// Scroll to top of form
			const container = document.querySelector('.appointment-single-column');
			if (container) {
				container.scrollIntoView({ 
					behavior: 'smooth', 
					block: 'start' 
				});
			}
		}
	});
}

if (nextBtn) {
	nextBtn.addEventListener('click', () => {
		if (validateStep(currentStep)) {
			if (currentStep < totalSteps) {
				showStep(currentStep + 1);
				updateButtons();
				// Scroll to top of form
				const container = document.querySelector('.appointment-single-column');
				if (container) {
					container.scrollIntoView({ 
						behavior: 'smooth', 
						block: 'start' 
					});
				}
			} else if (currentStep === totalSteps) {
				// On final step, submit the form
				const appointmentForm = document.getElementById('appointment-form');
				if (appointmentForm) {
					appointmentForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
				}
			}
		} else {
			// Shake the form to indicate error
			const formContainer = document.querySelector('.appointment-single-column');
			if (formContainer) {
				formContainer.classList.add('shake');
				setTimeout(() => formContainer.classList.remove('shake'), 500);
			}
		}
	});
}

// Update button states
function updateButtons() {
	if (prevBtn) {
		prevBtn.disabled = currentStep === 1;
	}
	if (nextBtn) {
		nextBtn.disabled = false;
		nextBtn.textContent = currentStep === totalSteps ? 'Submit' : 'Next';
	}
}

// Clear error styling on input and validate in real-time
document.querySelectorAll('.form-step input, .form-step select, .form-step textarea').forEach(input => {
	input.addEventListener('input', () => {
		if (input.value.trim()) {
			input.style.borderColor = '#e0e0e0';
		}
		// Real-time validation check
		checkCurrentStepCompletion();
	});
	
	input.addEventListener('focus', () => {
		input.style.borderColor = '#C5A253';
	});
	
	input.addEventListener('blur', () => {
		if (!input.value.trim() && input.required) {
			input.style.borderColor = '#e0e0e0';
		}
		// Check completion on blur as well
		checkCurrentStepCompletion();
	});
	
	// Also check on change for select elements
	if (input.tagName === 'SELECT') {
		input.addEventListener('change', () => {
			checkCurrentStepCompletion();
		});
	}
});

// Real-time validation check function
function checkCurrentStepCompletion() {
	const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
	if (!currentStepEl) {
		console.log('No current step element found for step:', currentStep);
		return;
	}
	
	const validationNote = document.getElementById('validation-note');
	if (!validationNote) {
		console.log('Validation note element not found');
		return;
	}
	
	// Reset all state classes and inline styles first
	validationNote.classList.remove('valid', 'warning', 'error');
	validationNote.style.color = '';
	validationNote.style.fontWeight = '';
	
	const inputs = currentStepEl.querySelectorAll('input[required], select[required], textarea[required]');
	let emptyFields = [];
	let allFilled = true;
	let totalRequired = 0;
	
	inputs.forEach(input => {
		// Skip disabled inputs and hidden inputs
		if (input.disabled || input.type === 'hidden') return;
		
		totalRequired++;
		
		if (!input.value.trim()) {
			allFilled = false;
			const label = currentStepEl.querySelector(`label[for="${input.id}"]`);
			if (label) {
				const fieldName = label.textContent.replace('*', '').replace(':', '').trim();
				emptyFields.push(fieldName);
			}
		}
	});
	
	// Special check for step 2 - date and time selection
	if (currentStep === 2) {
		const dateInput = document.getElementById('consultation-date');
		const timeInput = document.getElementById('consultation-time');
		
		if (dateInput && !dateInput.value.trim()) {
			allFilled = false;
			if (!emptyFields.includes('Consultation Date')) {
				emptyFields.push('Consultation Date');
				totalRequired++;
			}
		}
		
		if (timeInput && !timeInput.value.trim()) {
			allFilled = false;
			if (!emptyFields.includes('Consultation Time')) {
				emptyFields.push('Consultation Time');
				totalRequired++;
			}
		}
	}
	
	console.log(`Step ${currentStep}: ${emptyFields.length} empty fields out of ${totalRequired} total`);
	
	// Update validation note based on completion
	if (allFilled) {
		validationNote.innerHTML = '<i class="fas fa-check-circle"></i> All fields completed! Click Next to continue.';
		validationNote.classList.add('valid');
	} else if (emptyFields.length > 0) {
		const remaining = emptyFields.length;
		const filled = totalRequired - remaining;
		
		if (filled === 0) {
			// No fields filled yet
			validationNote.innerHTML = '<i class="fas fa-info-circle"></i> Please complete all required fields';
		} else {
			// Some fields filled - show progress
			validationNote.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${remaining} field${remaining > 1 ? 's' : ''} remaining: ${emptyFields.slice(0, 2).join(', ')}${emptyFields.length > 2 ? '...' : ''}`;
			validationNote.classList.add('warning');
		}
	}
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
	initMultiStepForm();
	
	// Reset form when navigating to appointment section
	const appointmentSection = document.getElementById('appointment');
	if (appointmentSection) {
		// Create an intersection observer to detect when section is visible
		const observer = new IntersectionObserver((entries) => {
			entries.forEach(entry => {
				if (entry.isIntersecting) {
					// Section is visible, reset the form
					resetForm();
				}
			});
		}, {
			threshold: 0.3 // Trigger when 30% of section is visible
		});
		
		observer.observe(appointmentSection);
	}
	
	// Also reset when clicking "Book Consultation" links
	document.querySelectorAll('a[href="#appointment"]').forEach(link => {
		link.addEventListener('click', () => {
			setTimeout(() => {
				resetForm();
			}, 300); // Small delay to allow smooth scroll
		});
	});
});


// ============================================
// SYNC CALENDAR WITH DATE INPUT FIELD
// ============================================

// Sync date input field with calendar selection
document.addEventListener('DOMContentLoaded', () => {
	const dateInput = document.getElementById('consultation-date');
	const lawyerSelect = document.getElementById('lawyer');
	
	if (dateInput) {
		// When user types or selects a date in the input field
		dateInput.addEventListener('change', () => {
			const selectedDate = dateInput.value;
			
			if (selectedDate) {
				// Highlight the selected date in the calendar
				const calendarButtons = document.querySelectorAll('.calendar-day button[data-date]');
				calendarButtons.forEach(btn => {
					btn.classList.remove('selected');
					if (btn.getAttribute('data-date') === selectedDate) {
						btn.classList.add('selected');
					}
				});
				
				// Update the display text
				const displayEl = document.getElementById('selected-date-display');
				if (displayEl) {
					const date = new Date(selectedDate + 'T00:00:00');
					displayEl.textContent = date.toLocaleDateString('en-US', { 
						weekday: 'short', 
						year: 'numeric', 
						month: 'short', 
						day: 'numeric' 
					});
				}
				
				// Load time slots if lawyer is selected
				const selectedLawyer = lawyerSelect?.value;
				if (selectedLawyer) {
					// Check if loadTimeSlotsIntoDropdown function exists
					if (typeof window.loadTimeSlotsIntoDropdown === 'function') {
						window.loadTimeSlotsIntoDropdown(selectedDate, selectedLawyer);
					}
				}
				
				// Check form completion
				if (typeof checkCurrentStepCompletion === 'function') {
					checkCurrentStepCompletion();
				}
			}
		});
		
		// Also trigger on input (for manual typing)
		dateInput.addEventListener('input', () => {
			if (dateInput.value && dateInput.value.length === 10) {
				dateInput.dispatchEvent(new Event('change'));
			}
		});
	}
});

console.log('✅ Calendar and date input synchronization initialized');


// ============================================
// VISUAL TIME SLOT SELECTOR
// ============================================

// Load and display time slots as clickable buttons
async function loadVisualTimeSlots(date, lawyerName) {
	const container = document.getElementById('time-slots-container');
	const message = document.getElementById('time-slots-message');
	const grid = document.getElementById('time-slots-grid');
	const hiddenInput = document.getElementById('consultation-time');
	
	if (!container || !message || !grid) return;
	
	// Show loading state
	message.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading available time slots...';
	message.classList.add('loading');
	message.classList.remove('error');
	message.style.display = 'flex';
	grid.style.display = 'none';
	grid.innerHTML = '';
	
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
			// Hide message, show grid
			message.style.display = 'none';
			grid.style.display = 'grid';
			
			// Create time slot buttons
			result.time_slots.forEach(slot => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'time-slot-button';
				
				if (slot.available) {
					button.classList.add('available');
					button.innerHTML = `
						<span class="slot-icon">🟢</span>
						<span>${slot.display}</span>
					`;
					
					// Click handler for available slots
					button.addEventListener('click', () => {
						// Remove selected class from all buttons
						grid.querySelectorAll('.time-slot-button').forEach(btn => {
							btn.classList.remove('selected');
						});
						
						// Add selected class to clicked button
						button.classList.add('selected');
						
						// Update hidden input with the display text (includes time in - time out)
						hiddenInput.value = slot.display;
						
						console.log('✅ Time slot selected:', slot.display);
						console.log('Hidden input value:', hiddenInput.value);
						
						// Trigger change event for validation
						hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
						
						// Check form completion
						if (typeof checkCurrentStepCompletion === 'function') {
							checkCurrentStepCompletion();
						}
					});
				} else {
					button.disabled = true;
					button.innerHTML = `
						<span class="slot-icon">🔴</span>
						<span>${slot.display}</span>
					`;
				}
				
				grid.appendChild(button);
			});
			
			console.log('Time slots loaded:', result.time_slots.length);
		} else {
			// No time slots available
			message.innerHTML = '<i class="fas fa-calendar-times"></i> No available time slots for this date';
			message.classList.remove('loading');
			message.classList.add('error');
			message.style.display = 'flex';
			grid.style.display = 'none';
		}
	} catch (error) {
		console.error('Error loading time slots:', error);
		message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error loading time slots. Please try again.';
		message.classList.remove('loading');
		message.classList.add('error');
		message.style.display = 'flex';
		grid.style.display = 'none';
	}
}

// Update the date/lawyer change handlers to use visual time slots
document.addEventListener('DOMContentLoaded', () => {
	const dateInput = document.getElementById('consultation-date');
	const lawyerSelect = document.getElementById('lawyer');
	
	// Function to load time slots when both date and lawyer are selected
	function checkAndLoadTimeSlots() {
		const selectedDate = dateInput?.value;
		const selectedLawyer = lawyerSelect?.value;
		
		if (selectedDate && selectedLawyer) {
			loadVisualTimeSlots(selectedDate, selectedLawyer);
		} else {
			// Reset time slots display
			const message = document.getElementById('time-slots-message');
			const grid = document.getElementById('time-slots-grid');
			
			if (message && grid) {
				message.innerHTML = '<i class="fas fa-info-circle"></i> Select a date and lawyer to view available time slots';
				message.classList.remove('loading', 'error');
				message.style.display = 'flex';
				grid.style.display = 'none';
				grid.innerHTML = '';
			}
		}
	}
	
	// Listen for date changes
	if (dateInput) {
		dateInput.addEventListener('change', () => {
			// Update hidden date input for review section
			const hiddenDateInput = document.getElementById('selected-date');
			if (hiddenDateInput && dateInput.value) {
				hiddenDateInput.value = dateInput.value;
			}
			
			// Load time slots
			checkAndLoadTimeSlots();
		});
	}
	
	// Listen for lawyer changes
	if (lawyerSelect) {
		lawyerSelect.addEventListener('change', checkAndLoadTimeSlots);
	}
});

console.log('✅ Visual time slot selector initialized');
