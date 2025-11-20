// Custom Modal Functions - Minimalist Design
function showConfirmModal(title, message, onConfirm) {
    // Create modal if it doesn't exist
    if (!document.getElementById('confirmModal')) {
        createConfirmModal();
    }
    
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmModal').style.display = 'block';
    
    // Remove previous event listeners
    const confirmBtn = document.getElementById('confirmOk');
    const cancelBtn = document.getElementById('confirmCancel');
    
    // Clone buttons to remove old event listeners
    const newConfirmBtn = confirmBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    // Add new event listeners
    newConfirmBtn.onclick = function() {
        document.getElementById('confirmModal').style.display = 'none';
        onConfirm();
    };
    
    newCancelBtn.onclick = function() {
        document.getElementById('confirmModal').style.display = 'none';
    };
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('confirmModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
}

function createConfirmModal() {
    const modalHTML = `
        <div id="confirmModal" class="confirm-modal">
            <div class="confirm-modal-content">
                <h3 id="confirmTitle">Confirm Action</h3>
                <p id="confirmMessage">Are you sure?</p>
                <div class="confirm-buttons">
                    <button id="confirmCancel" class="btn-cancel">Cancel</button>
                    <button id="confirmOk" class="btn-confirm">OK</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// Logout warning modal function
function confirmLogout() {
    showLogoutModal();
}

function showLogoutModal() {
    // Create logout modal if it doesn't exist
    if (!document.getElementById('logoutModal')) {
        createLogoutModal();
    }
    
    document.getElementById('logoutModal').style.display = 'flex';
    
    // Add event listeners
    document.getElementById('logoutCancel').onclick = function() {
        document.getElementById('logoutModal').style.display = 'none';
    };
    
    document.getElementById('logoutConfirm').onclick = function() {
        document.getElementById('logoutModal').style.display = 'none';
        window.location.href = 'logout.php';
    };
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('logoutModal');
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    };
}

function createLogoutModal() {
    const modalHTML = `
        <div id="logoutModal" class="logout-modal">
            <div class="logout-modal-content">
                <h3 class="logout-modal-title">Log out</h3>
                <p class="logout-modal-message">You will be returned to the login screen.</p>
                <div class="logout-modal-actions">
                    <button id="logoutCancel" class="logout-cancel-btn">Cancel</button>
                    <button id="logoutConfirm" class="logout-confirm-btn">Log out</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}
