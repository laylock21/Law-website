/**
 * Reusable Modal Container JavaScript
 * Usage: Include this file and call ModalContainer methods
 * Uses kiro- prefix to avoid conflicts with existing modal styles
 */

const ModalContainer = {
    modal: null,
    overlay: null,
    content: null,
    title: null,
    body: null,
    footer: null,
    closeBtn: null,

    init() {
        this.modal = document.getElementById('kiro-modal-container');
        if (!this.modal) {
            console.error('Modal container not found');
            return;
        }

        this.overlay = this.modal.querySelector('.kiro-modal-overlay');
        this.content = this.modal.querySelector('.kiro-modal-content');
        this.title = this.modal.querySelector('.kiro-modal-title');
        this.body = this.modal.querySelector('.kiro-modal-body');
        this.footer = this.modal.querySelector('.kiro-modal-footer');
        this.closeBtn = this.modal.querySelector('.kiro-modal-close');

        // Event listeners
        this.closeBtn.addEventListener('click', () => this.close());
        this.overlay.addEventListener('click', () => this.close());
        
        // ESC key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.style.display !== 'none') {
                this.close();
            }
        });
    },

    open(options = {}) {
        if (!this.modal) this.init();

        const {
            title = '',
            body = '',
            footer = '',
            width = 'auto',
            onClose = null
        } = options;

        // Set content
        this.title.textContent = title;
        
        if (typeof body === 'string') {
            this.body.innerHTML = body;
        } else if (body instanceof HTMLElement) {
            this.body.innerHTML = '';
            this.body.appendChild(body);
        }

        if (footer) {
            if (typeof footer === 'string') {
                this.footer.innerHTML = footer;
            } else if (footer instanceof HTMLElement) {
                this.footer.innerHTML = '';
                this.footer.appendChild(footer);
            }
        } else {
            this.footer.innerHTML = '';
        }

        // Set width
        if (width !== 'auto') {
            this.content.style.width = width;
        }

        // Store onClose callback
        this.onCloseCallback = onClose;

        // Show modal
        this.modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    },

    close() {
        if (!this.modal) return;

        this.modal.style.display = 'none';
        document.body.style.overflow = '';

        // Call onClose callback if provided
        if (typeof this.onCloseCallback === 'function') {
            this.onCloseCallback();
        }
        this.onCloseCallback = null;
    },

    setTitle(title) {
        if (this.title) {
            this.title.textContent = title;
        }
    },

    setBody(body) {
        if (!this.body) return;

        if (typeof body === 'string') {
            this.body.innerHTML = body;
        } else if (body instanceof HTMLElement) {
            this.body.innerHTML = '';
            this.body.appendChild(body);
        }
    },

    setFooter(footer) {
        if (!this.footer) return;

        if (typeof footer === 'string') {
            this.footer.innerHTML = footer;
        } else if (footer instanceof HTMLElement) {
            this.footer.innerHTML = '';
            this.footer.appendChild(footer);
        }
    },

    showLoading(message = 'Loading...') {
        this.open({
            title: 'Please Wait',
            body: `<div style="text-align: center; padding: 20px;">
                      <div class="spinner"></div>
                      <p>${message}</p>
                   </div>`
        });
    },

    showSuccess(message, title = 'Success') {
        this.open({
            title: title,
            body: `<div style="text-align: center; padding: 20px;">
                      <div style="color: #28a745; font-size: 48px; margin-bottom: 15px;">✓</div>
                      <p>${message}</p>
                   </div>`,
            footer: `<button onclick="ModalContainer.close()" class="btn btn-primary">OK</button>`
        });
    },

    showError(message, title = 'Error') {
        this.open({
            title: title,
            body: `<div style="text-align: center; padding: 20px;">
                      <div style="color: #dc3545; font-size: 48px; margin-bottom: 15px;">✕</div>
                      <p>${message}</p>
                   </div>`,
            footer: `<button onclick="ModalContainer.close()" class="btn btn-primary">OK</button>`
        });
    },

    confirm(message, onConfirm, title = 'Confirm') {
        this.open({
            title: title,
            body: `<p style="padding: 20px;">${message}</p>`,
            footer: `
                <button onclick="ModalContainer.close()" class="btn btn-secondary">Cancel</button>
                <button onclick="ModalContainer.handleConfirm()" class="btn btn-primary">Confirm</button>
            `
        });
        this.confirmCallback = onConfirm;
    },

    handleConfirm() {
        if (typeof this.confirmCallback === 'function') {
            this.confirmCallback();
        }
        this.confirmCallback = null;
        this.close();
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ModalContainer.init());
} else {
    ModalContainer.init();
}
