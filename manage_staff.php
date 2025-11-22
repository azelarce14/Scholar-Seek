<?php
session_start();

// Simple authentication check - only admin can manage staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Use centralized database connection
require_once 'db_connect.php';

// Initialize messages
$success_message = '';
$error_message = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $delete_query = "DELETE FROM staff WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $success_message = "Staff member deleted successfully!";
        } else {
            $error_message = "Error deleting staff member.";
        }
        $stmt->close();
    } else {
        $error_message = "Error deleting staff member.";
    }
    header("Location: manage_staff.php");
    exit();
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize inputs
    $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Validate required fields
    if (empty($fullname) || empty($email)) {
        $error_message = 'Please fill in all required fields (Name and Email).';
        header("Location: manage_staff.php");
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
        header("Location: manage_staff.php");
        exit();
    }

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Edit existing staff
        $id = $_POST['id'];
        
        // Enhanced security: Check for duplicate email across all tables (excluding current record)
        
        // Check in staff table (case-insensitive)
        $check_query = "SELECT id FROM staff WHERE LOWER(email) = LOWER(?) AND id != ?";
        $stmt = $conn->prepare($check_query);
        if ($stmt) {
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $error_message = "This email address is already registered in the staff system.";
            } else {
                // Check in students table (case-insensitive)
                $check_students = "SELECT id FROM students WHERE LOWER(email) = LOWER(?)";
                $stmt2 = $conn->prepare($check_students);
                if ($stmt2) {
                    $stmt2->bind_param("s", $email);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    
                    if ($result2 && $result2->num_rows > 0) {
                        $error_message = "This email address is already registered as a student account.";
                    } else {
                        // Check in users table (case-insensitive)
                        $check_users = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
                        $stmt3 = $conn->prepare($check_users);
                        if ($stmt3) {
                            $stmt3->bind_param("s", $email);
                            $stmt3->execute();
                            $result3 = $stmt3->get_result();
                            
                            if ($result3 && $result3->num_rows > 0) {
                                $error_message = "This email address is already registered in the admin system.";
                            }
                            $result3->free();
                            $stmt3->close();
                        }
                    }
                    $result2->free();
                    $stmt2->close();
                }
            }
            $result->free();
            $stmt->close();
        }
        
        // Only proceed with update if no duplicate email found
        if (empty($error_message)) {
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $update_query = "UPDATE staff SET fullname = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                if ($stmt) {
                    $stmt->bind_param("sssi", $fullname, $email, $hashed_password, $id);
                    if ($stmt->execute()) {
                        $success_message = "Staff member updated successfully!";
                    } else {
                        $error_message = "Error updating staff member.";
                    }
                    $stmt->close();
                }
            } else {
                $update_query = "UPDATE staff SET fullname = ?, email = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                if ($stmt) {
                    $stmt->bind_param("ssi", $fullname, $email, $id);
                    if ($stmt->execute()) {
                        $success_message = "Staff member updated successfully!";
                    } else {
                        $error_message = "Error updating staff member.";
                    }
                    $stmt->close();
                }
            }
        }
    } else {
        // Add new staff
        if (empty($password)) {
            $error_message = "Password is required for new staff members.";
        } else {
            // Enhanced security: Check for duplicate email across all tables (case-insensitive)
            
            // Check in staff table
            $check_query = "SELECT id FROM staff WHERE LOWER(email) = LOWER(?)";
            $stmt = $conn->prepare($check_query);
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0) {
                    $error_message = "This email address is already registered in the staff system.";
                } else {
                    // Check in students table
                    $check_students = "SELECT id FROM students WHERE LOWER(email) = LOWER(?)";
                    $stmt2 = $conn->prepare($check_students);
                    if ($stmt2) {
                        $stmt2->bind_param("s", $email);
                        $stmt2->execute();
                        $result2 = $stmt2->get_result();
                        
                        if ($result2 && $result2->num_rows > 0) {
                            $error_message = "This email address is already registered as a student account.";
                        } else {
                            // Check in users table
                            $check_users = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
                            $stmt3 = $conn->prepare($check_users);
                            if ($stmt3) {
                                $stmt3->bind_param("s", $email);
                                $stmt3->execute();
                                $result3 = $stmt3->get_result();
                                
                                if ($result3 && $result3->num_rows > 0) {
                                    $error_message = "This email address is already registered in the admin system.";
                                }
                                $result3->free();
                                $stmt3->close();
                            }
                        }
                        $result2->free();
                        $stmt2->close();
                    }
                }
                $result->free();
                $stmt->close();
            }
            
            // Only proceed with insert if no duplicate email found
            if (empty($error_message)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $insert_query = "INSERT INTO staff (fullname, email, password) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($insert_query);
                if ($stmt) {
                    $stmt->bind_param("sss", $fullname, $email, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $success_message = "Staff member created successfully! They can now login with email: <strong>$email</strong>";
                    } else {
                        $error_msg = $stmt->error;
                        error_log("Add staff error: " . $error_msg);
                        error_log("Staff data - Name: $fullname, Email: $email");
                        $error_message = "Error creating staff member: " . htmlspecialchars($error_msg);
                    }
                    $stmt->close();
                } else {
                    $error_msg = $conn->error;
                    error_log("Prepare error: " . $error_msg);
                    $error_message = "Database error: " . htmlspecialchars($error_msg);
                }
            }
        }
    }
}

// Fetch staff
$staff_members = [];
$query = "SELECT * FROM staff ORDER BY fullname ASC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $staff_members[] = $row;
    }
    $result->free();
}

// Get staff for edit
$edit_staff = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $query = "SELECT * FROM staff WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $edit_staff = $result->fetch_assoc();
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
    <link rel="stylesheet" href="assets/css/manage_staff.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo" />
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_scholarships.php" class="sidebar-item">
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
            <a href="manage_staff.php" class="sidebar-item active">
                <i class="fas fa-user-tie"></i>
                Staff
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="user-name">Admin</div>
            </div>
            <a href="#" onclick="confirmLogout(); return false;" class="compact-logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
        <!-- Alert Messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <h2>All Staff Members</h2>
                <p>Manage your staff members and their roles</p>
            </div>
            <button class="btn btn-add-new" onclick="showAddModal()">
                <i class="fas fa-plus"></i>
                Add New Staff
            </button>
        </div>

        <div class="scholarships-grid">
            <?php if (!empty($staff_members)): ?>
                <?php foreach ($staff_members as $staff): ?>
                    <div class="scholarship-card">
                        <div class="card-header-badge">
                            <div class="staff-info">
                                <div class="staff-avatar">
                                    <?php echo strtoupper(substr(htmlspecialchars($staff['fullname']), 0, 1)); ?>
                                </div>
                                <div class="staff-details">
                                    <h3 class="staff-name"><?php echo htmlspecialchars($staff['fullname']); ?></h3>
                                    <p class="staff-email"><?php echo htmlspecialchars($staff['email']); ?></p>
                                </div>
                            </div>
                            <span class="status-badge status-active">Staff</span>
                        </div>
                        
                        <div class="card-content">
                            <div class="staff-meta">
                                <div class="meta-item">
                                    <span class="meta-label">
                                        <i class="fas fa-envelope"></i>
                                        Email
                                    </span>
                                    <span class="meta-value"><?php echo array_key_exists('email', $staff) && !empty($staff['email']) ? htmlspecialchars($staff['email']) : 'N/A'; ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">
                                        <i class="fas fa-id-badge"></i>
                                        Staff ID
                                    </span>
                                    <span class="meta-value">#<?php echo $staff['id']; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <button class="action-btn btn-edit" onclick="editStaff(<?php echo $staff['id']; ?>)" title="Edit Staff">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn btn-delete" onclick="deleteStaff(<?php echo $staff['id']; ?>, '<?php echo htmlspecialchars($staff['fullname']); ?>')" title="Delete Staff">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-container">
                    <div class="empty-state">
                        <i class="fas fa-user-tie"></i>
                        <h3>No Staff Members Found</h3>
                        <p>Add your first staff member to get started.</p>
                        <button class="btn btn-primary" onclick="showAddModal()">
                            <i class="fas fa-plus"></i>
                            Add New Staff
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Staff</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p><strong>Note:</strong> Staff members will be able to login using their email and password. Make sure to provide them with their login credentials.</p>
                </div>
                
                <form id="staffForm" method="POST">
                    <input type="hidden" name="id" id="staffId">
                    
                    <label for="fullname">Full Name <span class="required">*</span></label>
                    <input type="text" id="fullname" name="fullname" placeholder="Enter full name" required>

                    <label for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="Enter email address" required>

                    <label for="password">Password <span class="required" id="passwordRequired">*</span></label>
                    <input type="password" id="password" name="password" placeholder="Enter password" minlength="6">
                    <small id="passwordHint" style="color: var(--gray-600); display: block; margin-top: 0.25rem;">
                        Required for new staff. Leave blank when editing to keep current password.
                    </small>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Staff
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
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
        const modal = document.getElementById('staffModal');
        const form = document.getElementById('staffForm');

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Staff';
            document.getElementById('staffId').value = '';
            form.reset();
            document.getElementById('password').required = true;
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('passwordHint').textContent = 'Required for new staff.';
            modal.style.display = 'block';
        }

        function editStaff(id) {
            // Redirect to edit with GET
            window.location.href = 'manage_staff.php?edit=' + id;
        }

        function deleteStaff(id, fullname) {
            showConfirmModal(
                'Delete Staff',
                'Delete "' + fullname + '"?\n\nThis action cannot be undone.',
                function() {
                    window.location.href = 'manage_staff.php?delete=' + id;
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

        // Pre-fill form if editing
        <?php if ($edit_staff): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('modalTitle').textContent = 'Edit Staff';
                document.getElementById('staffId').value = '<?php echo $edit_staff['id']; ?>';
                document.getElementById('fullname').value = '<?php echo addslashes($edit_staff['fullname']); ?>';
                document.getElementById('email').value = '<?php echo addslashes($edit_staff['email']); ?>';
                document.getElementById('password').required = false;
                document.getElementById('passwordRequired').style.display = 'none';
                document.getElementById('passwordHint').textContent = 'Leave blank to keep current password.';
                modal.style.display = 'block';
            });
        <?php endif; ?>
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
