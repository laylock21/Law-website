/**
 * Unified Confirmation Modal System
 * Provides Promise-based modal dialogs for confirmations and alerts
 * Uses kiro- prefix to avoid conflicts with existing modal styles
 */

const ConfirmModal = {
    /**
     * Show a confirmation dialog with Yes/No buttons
     * @param {Object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.confirmText - Text for confirm button (default: "Confirm")
     * @param {string} options.cancelText - Text for cancel button (default: "Cancel")
     * @param {string} options.type - Button type: 'danger', 'success', 'warning', 'info' (default: 'info')
     * @returns {Promise<boolean>} - Resolves to true if confirmed, false if cancelled
     */
    confirm: function(options) {
        return new Promise((resolve) => {
            const {
                title = 'Confirm Action',
                message = 'Are you sure?',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                type = 'info'
            } = options;

            // Remove any existing modal
            this._removeModal();

            // Create modal HTML with kiro- prefixed classes
            const modalHTML = `
                <div class="kiro-modal-wrapper" id="kiroConfirmModal">
                    <div class="kiro-modal-backdrop"></div>
                    <div class="kiro-modal-dialog">
                        <div class="kiro-modal-header">
                            <h3 class="kiro-modal-title">${this._escapeHtml(title)}</h3>
                            <button class="kiro-modal-close" data-action="close">&times;</button>
                        </div>
                        <div class="kiro-modal-body">
                            <p>${this._escapeHtml(message)}</p>
                        </div>
                        <div class="kiro-modal-footer">
                            <button class="kiro-btn kiro-btn-secondary" data-action="cancel">${this._escapeHtml(cancelText)}</button>
                            <button class="kiro-btn kiro-btn-${type}" data-action="confirm">${this._escapeHtml(confirmText)}</button>
                        </div>
                    </div>
                </div>
            `;

            // Insert modal into DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = document.getElementById('kiroConfirmModal');

            // Handle button clicks
            const handleClick = (e) => {
                const action = e.target.getAttribute('data-action');
                if (action === 'confirm') {
                    this._removeModal();
                    resolve(true);
                } else if (action === 'cancel' || action === 'close') {
                    this._removeModal();
                    resolve(false);
                }
            };

            // Handle backdrop click
            const handleBackdropClick = (e) => {
                if (e.target.classList.contains('kiro-modal-backdrop')) {
                    this._removeModal();
                    resolve(false);
                }
            };

            // Handle escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    this._removeModal();
                    resolve(false);
                }
            };

            modal.addEventListener('click', handleClick);
            modal.addEventListener('click', handleBackdropClick);
            document.addEventListener('keydown', handleEscape);

            // Store cleanup function
            modal._cleanup = () => {
                modal.removeEventListener('click', handleClick);
                modal.removeEventListener('click', handleBackdropClick);
                document.removeEventListener('keydown', handleEscape);
            };
        });
    },

    /**
     * Show an alert dialog with only OK button
     * @param {Object} options - Configuration options
     * @param {string} options.title - Modal title
     * @param {string} options.message - Modal message
     * @param {string} options.okText - Text for OK button (default: "OK")
     * @param {string} options.type - Button type: 'danger', 'success', 'warning', 'info' (default: 'info')
     * @returns {Promise<void>} - Resolves when OK is clicked
     */
    alert: function(options) {
        return new Promise((resolve) => {
            const {
                title = 'Notice',
                message = 'Information',
                okText = 'OK',
                type = 'info'
            } = options;

            // Remove any existing modal
            this._removeModal();

            // Create modal HTML with kiro- prefixed classes
            const modalHTML = `
                <div class="kiro-modal-wrapper" id="kiroConfirmModal">
                    <div class="kiro-modal-backdrop"></div>
                    <div class="kiro-modal-dialog">
                        <div class="kiro-modal-header">
                            <h3 class="kiro-modal-title">${this._escapeHtml(title)}</h3>
                            <button class="kiro-modal-close" data-action="close">&times;</button>
                        </div>
                        <div class="kiro-modal-body">
                            <p>${this._escapeHtml(message)}</p>
                        </div>
                        <div class="kiro-modal-footer">
                            <button class="kiro-btn kiro-btn-${type}" data-action="ok">${this._escapeHtml(okText)}</button>
                        </div>
                    </div>
                </div>
            `;

            // Insert modal into DOM
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            const modal = document.getElementById('kiroConfirmModal');

            // Handle button clicks
            const handleClick = (e) => {
                const action = e.target.getAttribute('data-action');
                if (action === 'ok' || action === 'close') {
                    this._removeModal();
                    resolve();
                }
            };

            // Handle backdrop click
            const handleBackdropClick = (e) => {
                if (e.target.classList.contains('kiro-modal-backdrop')) {
                    this._removeModal();
                    resolve();
                }
            };

            // Handle escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    this._removeModal();
                    resolve();
                }
            };

            modal.addEventListener('click', handleClick);
            modal.addEventListener('click', handleBackdropClick);
            document.addEventListener('keydown', handleEscape);

            // Store cleanup function
            modal._cleanup = () => {
                modal.removeEventListener('click', handleClick);
                modal.removeEventListener('click', handleBackdropClick);
                document.removeEventListener('keydown', handleEscape);
            };
        });
    },

    /**
     * Remove modal from DOM and cleanup event listeners
     * @private
     */
    _removeModal: function() {
        const modal = document.getElementById('kiroConfirmModal');
        if (modal) {
            if (modal._cleanup) {
                modal._cleanup();
            }
            modal.remove();
        }
    },

    /**
     * Escape HTML to prevent XSS
     * @private
     */
    _escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Make it globally available
window.ConfirmModal = ConfirmModal;
