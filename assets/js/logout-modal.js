// Logout Modal Functionality
class LogoutModal {
    constructor() {
        this.init();
    }
    
    init() {
        // Avoid creating multiple overlays if this is called more than once
        if (document.getElementById('logoutModalOverlay')) {
            this.modal = document.getElementById('logoutModalOverlay');
            this.cancelBtn = document.getElementById('logoutCancelBtn');
            this.confirmBtn = document.getElementById('logoutConfirmBtn');
            return this.bindEvents();
        }

        this.createModal();
        this.bindEvents();
    }
    
    createModal() {
        const modalHTML = `
            <div class="logout-modal-overlay" id="logoutModalOverlay">
                <div class="logout-modal">
                    <div class="logout-modal-header">
                        <div class="logout-modal-icon">
                            <i class="fas fa-power-off"></i>
                        </div>
                        <h3>Confirm Logout</h3>
                    </div>
                    <div class="logout-modal-body">
                        <p class="logout-modal-message">
                            Are you sure you want to logout from your <span class="logout-modal-username">account</span>?
                        </p>
                    </div>
                    <div class="logout-modal-footer">
                        <button type="button" class="logout-modal-btn logout-modal-btn-cancel" id="logoutCancelBtn">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                        <button type="button" class="logout-modal-btn logout-modal-btn-confirm" id="logoutConfirmBtn">
                            <i class="fas fa-sign-out-alt"></i>
                            Yes, Logout
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        if (document.body) {
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }

        this.modal = document.getElementById('logoutModalOverlay');
        this.cancelBtn = document.getElementById('logoutCancelBtn');
        this.confirmBtn = document.getElementById('logoutConfirmBtn');
    }
    
    bindEvents() {
        if (!this.modal || !this.cancelBtn || !this.confirmBtn) return;

        // Close modal when clicking cancel or overlay
        this.cancelBtn.addEventListener('click', () => this.hide());
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.hide();
            }
        });
        
        // Confirm logout
        this.confirmBtn.addEventListener('click', () => {
            window.location.href = 'logout.php';
        });
        
        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                this.hide();
            }
        });
    }
    
    show() {
        if (!this.modal) return;
        this.modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    hide() {
        if (!this.modal) return;
        this.modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Helper to ensure the logout modal is initialized reliably
function initLogoutModal() {
    if (!window.logoutModal) {
        window.logoutModal = new LogoutModal();
    }
}

// Initialize logout modal whether the DOM is already loaded or not
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogoutModal);
} else {
    initLogoutModal();
}

// Global function to show logout confirmation
window.confirmLogout = function() {
    // Ensure modal is ready even if called very early
    if (!window.logoutModal) {
        initLogoutModal();
    }
    if (window.logoutModal) {
        window.logoutModal.show();
    } else {
        console.error('LogoutModal not initialized');
    }
};
