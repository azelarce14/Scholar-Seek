<?php
session_start();
require_once 'db_connect.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

$staff_id = $_SESSION['user_id'];

// Fetch dashboard statistics
try {
    // Get total scholarships
    $result = secure_query($conn, "SELECT COUNT(*) as total FROM scholarships WHERE status = 'active'");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $total_scholarships = $row ? intval($row['total']) : 0;
    
    // Get total applications
    $result = secure_query($conn, "SELECT COUNT(*) as total FROM applications");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $total_applications = $row ? intval($row['total']) : 0;
    
    // Get total students
    $result = secure_query($conn, "SELECT COUNT(*) as total FROM students");
    $row = $result ? mysqli_fetch_assoc($result) : null;
    $total_students = $row ? intval($row['total']) : 0;
    
    // Get application statistics
    $result = secure_query($conn, "SELECT status, COUNT(*) as count FROM applications GROUP BY status");
    $pending_applications = 0;
    $approved_applications = 0;
    $rejected_applications = 0;
    
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['status'] === 'pending') {
                $pending_applications = intval($row['count']);
            } elseif ($row['status'] === 'approved') {
                $approved_applications = intval($row['count']);
            } elseif ($row['status'] === 'rejected') {
                $rejected_applications = intval($row['count']);
            }
        }
    }
    
} catch (Exception $e) {
    // Set defaults if database queries fail
    $total_scholarships = 0;
    $total_applications = 0;
    $total_students = 0;
    $pending_applications = 0;
    $approved_applications = 0;
    $rejected_applications = 0;
    error_log("Staff Dashboard Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSeek - Staff Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/staff_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
</head>
<body class="staff-dashboard">

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
            <a href="staff_dashboard.php" class="sidebar-item active">
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
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="user-name">Staff</div>
            </div>
            <a href="#" onclick="confirmLogout(); return false;" class="compact-logout-btn">
                <i class="fas fa-power-off"></i>
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
                    <h1>👋 Welcome back, Staff!</h1>
                    <p>Here's what's happening with your scholarship management system today.</p>
                </div>
            </div>

            <!-- System Overview -->
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> System Overview</h2>
                <p>Key metrics and statistics at a glance</p>
            </div>

            <div class="compact-stats-grid">
                <a href="manage_scholarships.php" class="compact-stat-card stat-scholars clickable-stat">
                    <div class="compact-stat-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="compact-stat-info">
                        <div class="compact-stat-value"><?php echo number_format($total_scholarships); ?></div>
                        <div class="compact-stat-label">Active Scholarships</div>
                    </div>
                </a>

                <a href="manage_applications.php" class="compact-stat-card stat-applicants clickable-stat">
                    <div class="compact-stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="compact-stat-info">
                        <div class="compact-stat-value"><?php echo number_format($total_applications); ?></div>
                        <div class="compact-stat-label">Total Applications</div>
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
                                <div class="app-stat-value"><?php echo number_format($total_applications); ?></div>
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

        // Toggle mobile menu - only if element exists
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            });
        }

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
        const profileButton = document.getElementById('profileButton');
        const profileDropdown = document.getElementById('profileDropdown');

        // Toggle profile dropdown - only if elements exist
        if (profileButton && profileDropdown) {
            profileButton.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });
        }

        // Close dropdowns when clicking outside
        if (profileDropdown) {
            document.addEventListener('click', function() {
                profileDropdown.classList.remove('show');
            });

            // Prevent dropdowns from closing when clicking inside them
            profileDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

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
        
        // Define confirmLogout function first
        window.confirmLogout = function() {
            if (!window.logoutModal) initLogoutModal();
            if (window.logoutModal) window.logoutModal.show();
        };
        
        // Initialize logout modal when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initLogoutModal);
        } else {
            initLogoutModal();
        }
    </script>
</body>
</html>