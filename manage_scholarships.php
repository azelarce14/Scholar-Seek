<?php
session_start();

// Simple authentication check - admin or staff can manage scholarships
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

// Use centralized database connection
require_once 'db_connect.php';

// Auto-fix: Add missing columns to scholarships table if they don't exist
$check_columns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'scholarships' AND TABLE_SCHEMA = DATABASE()";
$result = $conn->query($check_columns);
$existing_columns = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['COLUMN_NAME'];
    }
}

// Add missing columns
if (!in_array('min_gwa', $existing_columns)) {
    $conn->query("ALTER TABLE scholarships ADD COLUMN min_gwa DECIMAL(3, 2) DEFAULT NULL");
}
if (!in_array('required_documents', $existing_columns)) {
    $conn->query("ALTER TABLE scholarships ADD COLUMN required_documents LONGTEXT DEFAULT NULL");
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get scholarship title before deleting for notification
    $title_query = "SELECT title FROM scholarships WHERE id = ?";
    $title_stmt = $conn->prepare($title_query);
    $scholarship_title = '';
    if ($title_stmt) {
        $title_stmt->bind_param("i", $id);
        $title_stmt->execute();
        $title_result = $title_stmt->get_result();
        if ($title_result && $title_result->num_rows > 0) {
            $title_row = $title_result->fetch_assoc();
            $scholarship_title = $title_row['title'];
        }
        $title_result->free();
        $title_stmt->close();
    }
    
    // Delete the scholarship
    $delete_query = "DELETE FROM scholarships WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['notification'] = 'Scholarship "' . htmlspecialchars($scholarship_title) . '" deleted successfully!';
        $_SESSION['notification_type'] = 'success';
    }
    header("Location: manage_scholarships.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $deadline = isset($_POST['deadline']) ? trim($_POST['deadline']) : '';
    
    // Validate required fields
    if (empty($title) || empty($description) || empty($deadline)) {
        $_SESSION['notification'] = 'Please fill in all required fields.';
        $_SESSION['notification_type'] = 'error';
        header("Location: manage_scholarships.php");
        exit();
    }
    
    // Convert amount to float and remove any non-numeric characters
    $amount_raw = isset($_POST['amount']) ? trim($_POST['amount']) : '0';
    $amount = floatval(preg_replace('/[^0-9.]/', '', $amount_raw));
    
    if ($amount <= 0) {
        $_SESSION['notification'] = 'Scholarship amount must be greater than 0.';
        $_SESSION['notification_type'] = 'error';
        header("Location: manage_scholarships.php");
        exit();
    }
    
    $min_gwa = (!empty($_POST['min_gwa']) && trim($_POST['min_gwa']) !== '') ? trim($_POST['min_gwa']) : null;
    
    // Handle required documents
    $required_docs = [];
    if (isset($_POST['required_documents']) && is_array($_POST['required_documents'])) {
        $required_docs = $_POST['required_documents'];
    }
    $required_documents = json_encode($required_docs);

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Edit
        $id = $_POST['id'];
        $update_query = "UPDATE scholarships SET title = ?, description = ?, amount = ?, deadline = ?, min_gwa = ?, required_documents = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param("ssdsssi", $title, $description, $amount, $deadline, $min_gwa, $required_documents, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['notification'] = 'Scholarship updated successfully!';
            $_SESSION['notification_type'] = 'success';
        }
    } else {
        // Add
        $insert_query = "INSERT INTO scholarships (title, description, amount, deadline, min_gwa, required_documents, status) VALUES (?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $conn->prepare($insert_query);
        if ($stmt) {
            // Bind parameters with correct types: s=string, s=string, d=double, s=string, s=string (min_gwa), s=string (required_documents)
            $stmt->bind_param("ssdsss", $title, $description, $amount, $deadline, $min_gwa, $required_documents);
            if (!$stmt->execute()) {
                $error_msg = $stmt->error;
                error_log("Add scholarship error: " . $error_msg);
                error_log("Scholarship data - Title: $title, Amount: $amount, Deadline: $deadline, Min GWA: $min_gwa, Docs: $required_documents");
                $_SESSION['notification'] = 'Error creating scholarship: ' . htmlspecialchars($error_msg);
                $_SESSION['notification_type'] = 'error';
            } else {
                $_SESSION['notification'] = 'Scholarship created successfully!';
                $_SESSION['notification_type'] = 'success';
            }
            $stmt->close();
        } else {
            $error_msg = $conn->error;
            error_log("Prepare error: " . $error_msg);
            $_SESSION['notification'] = 'Database error: ' . htmlspecialchars($error_msg);
            $_SESSION['notification_type'] = 'error';
        }
    }
    header("Location: manage_scholarships.php");
    exit();
}

// Get search parameter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch scholarships with search functionality
$scholarships = [];
if (!empty($search)) {
    $query = "SELECT * FROM scholarships WHERE title LIKE ? OR description LIKE ? ORDER BY deadline DESC";
    $search_param = '%' . $search . '%';
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("ss", $search_param, $search_param);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $scholarships[] = $row;
            }
            $result->free();
        }
        $stmt->close();
    }
} else {
    $query = "SELECT * FROM scholarships ORDER BY deadline DESC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $scholarships[] = $row;
        }
        $result->free();
    }
}

// Get scholarship for edit
$edit_scholarship = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $query = "SELECT * FROM scholarships WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $edit_scholarship = $result->fetch_assoc();
        }
        $result->free();
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSeek</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/manage_scholarships.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo" />
        </div>
        <nav class="sidebar-nav">
            <a href="<?php echo $_SESSION['user_type'] == 'admin' ? 'admin_dashboard.php' : 'staff_dashboard.php'; ?>" class="sidebar-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_scholarships.php" class="sidebar-item active">
                <i class="fas fa-graduation-cap"></i>
                Scholarships
            </a>
            <a href="manage_applications.php" class="sidebar-item">
                <i class="fas fa-file-alt"></i>
                Applications
            </a>
            <a href="manage_students.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                Students
            </a>
            <?php if ($_SESSION['user_type'] == 'admin'): ?>
            <a href="manage_staff.php" class="sidebar-item">
                <i class="fas fa-user-tie"></i>
                Staff
            </a>
            <?php endif; ?>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-name"><?php echo $_SESSION['user_type'] == 'admin' ? 'Admin' : 'Staff'; ?></div>
            </div>
            <a href="#" onclick="confirmLogout(); return false;" class="compact-logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header-compact">
            <div class="header-left">
                <h2><i class="fas fa-graduation-cap"></i> Manage Scholarships</h2>
                <p>Create and manage scholarship opportunities for students</p>
            </div>
            <div class="header-right">
                <button class="btn-search-toggle" onclick="toggleSearch()" title="Search Scholarships">
                    <i class="fas fa-search"></i>
                </button>
                <!-- Statistics Pills -->
                <div class="stats-cards">
                    <button class="stat-pill stat-total">
                        <span class="stat-label">TOTAL</span>
                        <span class="stat-value"><?php echo count($scholarships); ?></span>
                    </button>
                    <button class="stat-pill stat-active">
                        <span class="stat-label">ACTIVE</span>
                        <span class="stat-value"><?php echo count(array_filter($scholarships, function($s) { return $s['status'] == 'active'; })); ?></span>
                    </button>
                    <button class="stat-pill stat-inactive">
                        <span class="stat-label">INACTIVE</span>
                        <span class="stat-value"><?php echo count(array_filter($scholarships, function($s) { return $s['status'] == 'inactive'; })); ?></span>
                    </button>
                </div>
                <button class="btn-add-new-small" onclick="showAddModal()">
                    <i class="fas fa-plus"></i>
                    Add New
                </button>
            </div>
        </div>

        <!-- Message display -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert-message <?php echo $_SESSION['message_type'] ?? 'success'; ?>">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['message']); ?></span>
                <button class="close-alert" onclick="this.parentElement.remove()">×</button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Toast Notification -->
        <?php if (isset($_SESSION['notification'])): ?>
            <div class="toast-notification toast-<?php echo $_SESSION['notification_type'] ?? 'success'; ?>" id="toastNotification">
                <div class="toast-content">
                    <i class="fas fa-<?php echo $_SESSION['notification_type'] === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($_SESSION['notification']); ?></span>
                </div>
                <div class="toast-progress"></div>
            </div>
            <?php unset($_SESSION['notification'], $_SESSION['notification_type']); ?>
        <?php endif; ?>

        <!-- Compact Search Section (Hidden by default) -->
        <div class="search-section-compact" id="searchSection" style="display: <?php echo !empty($search) ? 'block' : 'none'; ?>;">
            <form method="GET" class="search-form-compact">
                <div class="search-input-group-compact">
                    <input type="text" name="search" placeholder="Search scholarships..." value="<?php echo htmlspecialchars($search); ?>" class="search-input-compact" id="searchInput">
                    <button type="submit" class="search-btn-compact">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="manage_scholarships.php" class="clear-search-btn-compact" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            <?php if (!empty($search)): ?>
                <div class="search-results-compact">
                    <span><i class="fas fa-search"></i> <?php echo count($scholarships); ?> result(s) for "<?php echo htmlspecialchars($search); ?>"</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Scholarships Grid -->
        <?php if (!empty($scholarships)): ?>
            <div class="scholarships-grid">
                <?php foreach ($scholarships as $scholarship): ?>
                    <div class="scholarship-card" onclick="viewScholarship(<?php echo $scholarship['id']; ?>)" style="cursor: pointer;">
                        <div class="scholarship-header">
                            <h3 class="scholarship-title"><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                        </div>
                        <div class="card-content">
                            <div class="scholarship-info">
                                <div class="info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo date('M d, Y', strtotime($scholarship['deadline'])); ?></span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <span>₱<?php echo number_format($scholarship['amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button class="action-btn btn-edit" onclick="event.stopPropagation(); editScholarship(<?php echo $scholarship['id']; ?>)" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="event.stopPropagation(); deleteScholarship(<?php echo $scholarship['id']; ?>, '<?php echo htmlspecialchars(addslashes($scholarship['title'])); ?>')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-container">
                <div class="empty-state-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>No Scholarships Yet</h3>
                <p>Start by creating your first scholarship opportunity for students</p>
                <button class="btn-add-new" onclick="showAddModal()">
                    <i class="fas fa-plus-circle"></i>
                    Add First Scholarship
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add/Edit Modal -->
    <div id="scholarshipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Scholarship</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="scholarshipForm" method="POST">
                    <input type="hidden" name="id" id="scholarshipId">
                    
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" required>

                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" required></textarea>

                    <label for="amount">Amount (₱)</label>
                    <input type="text" id="amount" name="amount" required>

                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline" required>

                    <label for="min_gwa">Minimum GWA <span class="optional">(Optional)</span></label>
                    <input type="number" id="min_gwa" name="min_gwa" step="0.1" min="1.0" max="2.5" placeholder="e.g., 1.5">
                    <small style="color: var(--gray-600); display: block; margin-top: 0.25rem;">
                        Enter minimum GWA required (1.0 = highest, 2.5 = lowest, 5.0 = fail). Leave empty if not required.
                    </small>

                    <label>Additional Required Documents</label>
                    <div class="core-documents-info">
                        <p><strong>Core Documents (Always Required):</strong></p>
                        <ul>
                            <li>✓ Certificate of Enrollment</li>
                            <li>✓ Certificate of Good Moral Character</li>
                            <li>✓ Report Card (Grades)</li>
                            <li>✓ Study Load</li>
                        </ul>
                        <p><em>Select additional documents required for this scholarship:</em></p>
                    </div>
                    <div class="document-checkboxes">
                        <div class="checkbox-group">
                            <input type="checkbox" id="doc_transcript" name="required_documents[]" value="Official Transcript of Records">
                            <label for="doc_transcript">Official Transcript of Records</label>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="doc_recommendation" name="required_documents[]" value="Letter of Recommendation">
                            <label for="doc_recommendation">Letter of Recommendation</label>
                        </div>
                        <!-- Removed: Scholarship Essay, Family Income Certificate, Valid ID, Birth Certificate -->
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary">Save Scholarship</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
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

    <style>
        .confirm-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .confirm-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border: none;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .confirm-modal-content h3 {
            margin: 0 0 15px 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }

        .confirm-modal-content p {
            margin: 0 0 25px 0;
            color: #666;
            line-height: 1.5;
        }

        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-cancel, .btn-confirm {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            min-width: 80px;
            transition: all 0.2s ease;
        }

        .btn-cancel {
            background-color: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .btn-cancel:hover {
            background-color: #e5e7eb;
        }

        .btn-confirm {
            background-color: #0A06D3;
            color: white;
            border: 1px solid #0A06D3;
        }

        .btn-confirm:hover {
            background-color: #0805b0;
            border-color: #0805b0;
        }
    </style>

    <script>
        const modal = document.getElementById('scholarshipModal');
        const form = document.getElementById('scholarshipForm');

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Scholarship';
            document.getElementById('scholarshipId').value = '';
            form.reset();
            modal.style.display = 'block';
        }

        function viewScholarship(id) {
            // For now, redirect to edit - can be changed to a view-only modal later
            window.location.href = 'manage_scholarships.php?edit=' + id;
        }

        function editScholarship(id) {
            window.location.href = 'manage_scholarships.php?edit=' + id;
        }

        function viewApplications(id) {
            window.location.href = 'manage_applications.php?scholarship_id=' + id;
        }

        function deleteScholarship(id, title) {
            showConfirmModal(
                'Delete Scholarship',
                'Delete "' + title + '"?\n\nThis action cannot be undone.',
                function() {
                    window.location.href = 'manage_scholarships.php?delete=' + id;
                }
            );
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Logout function now handled by custom-modal.js

        // Custom modal functions
        function showConfirmModal(title, message, onConfirm) {
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmModal').style.display = 'block';
            
            document.getElementById('confirmOk').onclick = function() {
                document.getElementById('confirmModal').style.display = 'none';
                onConfirm();
            };
            
            document.getElementById('confirmCancel').onclick = function() {
                document.getElementById('confirmModal').style.display = 'none';
            };
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        <?php if ($edit_scholarship): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('modalTitle').textContent = 'Edit Scholarship';
                document.getElementById('scholarshipId').value = '<?php echo $edit_scholarship['id']; ?>';
                document.getElementById('title').value = '<?php echo addslashes($edit_scholarship['title']); ?>';
                document.getElementById('description').value = '<?php echo addslashes($edit_scholarship['description']); ?>';
                document.getElementById('amount').value = '<?php echo $edit_scholarship['amount']; ?>';
                document.getElementById('deadline').value = '<?php echo $edit_scholarship['deadline']; ?>';
                document.getElementById('min_gwa').value = '<?php echo $edit_scholarship['min_gwa'] ?? ''; ?>';
                
                // Set required documents checkboxes
                const requiredDocs = <?php echo $edit_scholarship['required_documents'] ?? '[]'; ?>;
                requiredDocs.forEach(doc => {
                    const checkbox = document.querySelector(`input[value="${doc}"]`);
                    if (checkbox) checkbox.checked = true;
                });
                
                modal.style.display = 'block';
            });
        <?php endif; ?>
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const message = document.querySelector('.message');
            if (message) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    setTimeout(() => message.remove(), 500);
                }, 4000);
            }
        });

        form.addEventListener('submit', function(e) {
            // Allow form to submit normally
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }
            form.classList.add('loading');
        });

        // Toggle search functionality
        function toggleSearch() {
            const searchSection = document.getElementById('searchSection');
            const searchInput = document.getElementById('searchInput');
            
            if (searchSection.style.display === 'none' || searchSection.style.display === '') {
                searchSection.style.display = 'block';
                searchInput.focus();
            } else {
                searchSection.style.display = 'none';
            }
        }

        // Toast notification auto-dismiss
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toastNotification');
            if (toast) {
                // Auto-dismiss after 3 seconds
                setTimeout(function() {
                    toast.classList.add('hide');
                    setTimeout(function() {
                        toast.remove();
                    }, 300);
                }, 3000);
            }
        });
    </script>
    <script src="assets/js/custom-modal.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // Logout Modal Functionality
        class LogoutModal {
            constructor() {
                this.init();
            }
            
            init() {
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
                this.cancelBtn.addEventListener('click', () => this.hide());
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) this.hide();
                });
                this.confirmBtn.addEventListener('click', () => {
                    window.location.href = 'logout.php';
                });
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

        function initLogoutModal() {
            if (!window.logoutModal) {
                window.logoutModal = new LogoutModal();
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLogoutModal);
        } else {
            initLogoutModal();
        }
        window.confirmLogout = function() {
            if (!window.logoutModal) initLogoutModal();
            if (window.logoutModal) window.logoutModal.show();
        };
    </script>
</body>
</html>