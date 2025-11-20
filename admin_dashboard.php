<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Initialize variables with default values
$total_scholars = 0;
$total_applicants = 0;
$total_students = 0;
$pending_applications = 0;
$approved_applications = 0;
$rejected_applications = 0;

try {
    // Get total scholars (approved applications)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_scholars = $row['count'] ?? 0;
    $stmt->close();

    // Get total applicants (all applications)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_applicants = $row['count'] ?? 0;
    $stmt->close();

    // Get total registered students
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM students");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_students = $row['count'] ?? 0;
    $stmt->close();

    // Get pending applications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $pending_applications = $row['count'] ?? 0;
    $stmt->close();

    // Get approved applications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'approved'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $approved_applications = $row['count'] ?? 0;
    $stmt->close();

    // Get rejected applications
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'rejected'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $rejected_applications = $row['count'] ?? 0;
    $stmt->close();
} catch (Exception $e) {
    // Log error but continue with default values
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSeek - Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/admin_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="mobile-menu-toggle">
        <i class="fas fa-bars"></i>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo" />
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-item active">
                <i class="fas fa-home"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="manage_scholarships.php" class="sidebar-item">
                <i class="fas fa-graduation-cap"></i>
                <span class="sidebar-label">Scholarships</span>
            </a>
            <a href="manage_applications.php" class="sidebar-item">
                <i class="fas fa-file-alt"></i>
                <span class="sidebar-label">Applications</span>
            </a>
            <a href="manage_students.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                <span class="sidebar-label">Students</span>
            </a>
            <a href="manage_staff.php" class="sidebar-item">
                <i class="fas fa-user-tie"></i>
                <span class="sidebar-label">Staff</span>
            </a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-icon">
                    <i class="fas fa-user-cog"></i>
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
        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>👋 Welcome back, Admin!</h1>
                    <p>Here's what's happening with your scholarship management system today.</p>
                </div>
            </div>

            <!-- System Overview -->
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> System Overview</h2>
                <p>Key metrics and statistics at a glance</p>
            </div>

            <div class="compact-stats-grid">
                <a href="manage_applications.php?status=approved" class="compact-stat-card stat-scholars clickable-stat">
                    <div class="compact-stat-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <div class="compact-stat-info">
                        <div class="compact-stat-value"><?php echo number_format($total_scholars); ?></div>
                        <div class="compact-stat-label">Total Scholars</div>
                    </div>
                </a>

                <a href="manage_applications.php" class="compact-stat-card stat-applicants clickable-stat">
                    <div class="compact-stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="compact-stat-info">
                        <div class="compact-stat-value"><?php echo number_format($total_applicants); ?></div>
                        <div class="compact-stat-label">Total Applicants</div>
                    </div>
                </a>

                <a href="manage_students.php" class="compact-stat-card stat-students clickable-stat">
                    <div class="compact-stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="compact-stat-info">
                        <div class="compact-stat-value"><?php echo number_format($total_students); ?></div>
                        <div class="compact-stat-label">Registered Students</div>
                    </div>
                </a>
            </div>

            <!-- Application Statistics and Quick Actions -->
            <div class="stats-actions-container">
                <!-- Application Statistics -->
                <div class="stats-section">
                    <div class="section-header">
                        <h2><i class="fas fa-chart-pie"></i> Application Statistics</h2>
                        <p>Current status breakdown of all applications</p>
                    </div>

                    <div class="application-stats-grid">
                        <a href="manage_applications.php" class="app-stat-card stat-total clickable-stat">
                            <div class="app-stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="app-stat-info">
                                <div class="app-stat-value"><?php echo number_format($total_applicants); ?></div>
                                <div class="app-stat-label">Total Applications</div>
                            </div>
                        </a>

                        <a href="manage_applications.php?status=pending" class="app-stat-card stat-pending clickable-stat">
                            <div class="app-stat-icon">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <div class="app-stat-info">
                                <div class="app-stat-value"><?php echo number_format($pending_applications); ?></div>
                                <div class="app-stat-label">Pending Review</div>
                            </div>
                        </a>

                        <a href="manage_applications.php?status=approved" class="app-stat-card stat-approved clickable-stat">
                            <div class="app-stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="app-stat-info">
                                <div class="app-stat-value"><?php echo number_format($approved_applications); ?></div>
                                <div class="app-stat-label">Approved</div>
                            </div>
                        </a>

                        <a href="manage_applications.php?status=rejected" class="app-stat-card stat-rejected clickable-stat">
                            <div class="app-stat-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <div class="app-stat-info">
                                <div class="app-stat-value"><?php echo number_format($rejected_applications); ?></div>
                                <div class="app-stat-label">Rejected</div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="actions-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                        <p>Manage your system efficiently</p>
                    </div>

                    <div class="quick-actions-grid">
                        <a href="manage_scholarships.php" class="action-card">
                            <div class="action-icon gradient-blue">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h3>Add New Scholarship</h3>
                        </a>

                        <a href="manage_students.php" class="action-card">
                            <div class="action-icon gradient-green">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>View Students</h3>
                        </a>

                        <a href="manage_applications.php" class="action-card">
                            <div class="action-icon gradient-orange">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <h3>Review Applications</h3>
                        </a>

                        <a href="manage_staff.php" class="action-card">
                            <div class="action-icon gradient-purple">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <h3>Manage Staff</h3>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        // Toggle mobile menu
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
        });

        // Close menu when clicking overlay
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });

        // Close menu when clicking sidebar links on mobile
        const sidebarLinks = document.querySelectorAll('.sidebar a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Dropdown functionality
        const notificationButton = document.getElementById('notificationButton');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const profileButton = document.getElementById('profileButton');
        const profileDropdown = document.getElementById('profileDropdown');

        // Toggle notification dropdown
        notificationButton.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
            profileDropdown.classList.remove('show');
        });

        // Toggle profile dropdown
        profileButton.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
            notificationDropdown.classList.remove('show');
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            notificationDropdown.classList.remove('show');
            profileDropdown.classList.remove('show');
        });

        // Prevent dropdowns from closing when clicking inside them
        [notificationDropdown, profileDropdown].forEach(dropdown => {
            dropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

    </script>
    
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