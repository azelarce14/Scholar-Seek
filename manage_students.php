<?php
session_start();

// Include academic structure configuration
require_once 'config/academic_structure.php';

// Use centralized database connection
require_once 'db_connect.php';

// Handle AJAX requests for student details
if (isset($_GET['action']) && $_GET['action'] === 'get_student_details') {
    handleStudentDetailsRequest();
    exit();
}

function handleStudentDetailsRequest() {
    global $conn;
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Check authentication - admin or staff only
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }
    
    // Check if student ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
        return;
    }
    
    $student_id = intval($_GET['id']);
    
    try {
        // Get student details
        $student_query = "SELECT * FROM students WHERE id = ?";
        $stmt = $conn->prepare($student_query);
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'error' => 'Student not found']);
                return;
            }
            
            $student = $result->fetch_assoc();
            $result->free();
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
            return;
        }
        
        // Get application counts
        $app_count_query = "SELECT COUNT(*) as count FROM applications WHERE student_id = ?";
        $app_stmt = $conn->prepare($app_count_query);
        if ($app_stmt) {
            $app_stmt->bind_param("i", $student_id);
            $app_stmt->execute();
            $app_result = $app_stmt->get_result();
            $application_count = $app_result->fetch_assoc()['count'];
            $app_result->free();
            $app_stmt->close();
        } else {
            $application_count = 0;
        }
        
        // Get approved application count
        $approved_count_query = "SELECT COUNT(*) as count FROM applications WHERE student_id = ? AND status = 'approved'";
        $approved_stmt = $conn->prepare($approved_count_query);
        if ($approved_stmt) {
            $approved_stmt->bind_param("i", $student_id);
            $approved_stmt->execute();
            $approved_result = $approved_stmt->get_result();
            $approved_count = $approved_result->fetch_assoc()['count'];
            $approved_result->free();
            $approved_stmt->close();
        } else {
            $approved_count = 0;
        }
        
        // Return student data
        echo json_encode([
            'success' => true,
            'student' => [
                'id' => $student['id'],
                'fullname' => $student['fullname'],
                'email' => $student['email'],
                'student_number' => $student['student_number'],
                'program' => $student['program'],
                'department' => $student['department'],
                'year_level' => $student['year_level'],
                'gwa' => $student['gwa'],
                'address' => $student['address'] ?? 'N/A',
                'phone' => $student['phone'] ?? 'N/A',
                'date_of_birth' => $student['date_of_birth'] ?? 'N/A',
                'created_at' => $student['created_at'] ?? 'N/A'
            ],
            'application_count' => $application_count,
            'approved_count' => $approved_count
        ]);
        
    } catch (Exception $e) {
        error_log("Error fetching student details: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    }
}

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_student') {
    // Require admin authentication for delete operations
    if ($_SESSION['user_type'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    header('Content-Type: application/json');
    
    $student_id = $_POST['id'] ?? null;
    
    if (!$student_id || !is_numeric($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
        exit;
    }
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // First, check if student exists
        $check_query = "SELECT id, fullname FROM students WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        if ($check_stmt) {
            $check_stmt->bind_param("i", $student_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Student not found']);
                exit;
            }
            
            $student = $check_result->fetch_assoc();
            $check_result->free();
            $check_stmt->close();
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error']);
            exit;
        }
        
        // Delete related applications first (if any)
        $delete_applications_query = "DELETE FROM applications WHERE student_id = ?";
        $delete_applications_stmt = $conn->prepare($delete_applications_query);
        if ($delete_applications_stmt) {
            $delete_applications_stmt->bind_param("i", $student_id);
            $delete_applications_stmt->execute();
            $delete_applications_stmt->close();
        }
        
        // Delete the student
        $delete_student_query = "DELETE FROM students WHERE id = ?";
        $delete_student_stmt = $conn->prepare($delete_student_query);
        if ($delete_student_stmt) {
            $delete_student_stmt->bind_param("i", $student_id);
            
            if ($delete_student_stmt->execute()) {
                // Commit transaction
                $conn->commit();
                
                // Log the deletion (optional)
                error_log("Admin deleted student: ID {$student_id}, Name: {$student['fullname']}");
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Student deleted successfully',
                    'student_name' => $student['fullname']
                ]);
            } else {
                $conn->rollback();
                echo json_encode(['success' => false, 'message' => 'Failed to delete student']);
            }
            $delete_student_stmt->close();
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Error deleting student: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    
    exit;
}

// Authentication already checked at the top of the file

// Admin can view and delete students

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$filter_department = $_GET['department'] ?? '';
$filter_program = $_GET['program'] ?? '';
$filter_year = $_GET['year'] ?? '';
$sort_by = $_GET['sort'] ?? 'fullname';
$sort_order = $_GET['order'] ?? 'ASC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = intval($_GET['per_page'] ?? 20);
$offset = ($page - 1) * $per_page;

// Build WHERE clause for search and filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(fullname LIKE ? OR email LIKE ? OR student_number LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($filter_department)) {
    $where_conditions[] = "department = ?";
    $params[] = $filter_department;
    $param_types .= 's';
}

if (!empty($filter_program)) {
    $where_conditions[] = "program = ?";
    $params[] = $filter_program;
    $param_types .= 's';
}

if (!empty($filter_year)) {
    $where_conditions[] = "year_level = ?";
    $params[] = $filter_year;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM students {$where_clause}";
$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    if (!empty($params)) {
        $count_stmt->bind_param($param_types, ...$params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_students = $count_result->fetch_assoc()['total'];
    $count_result->free();
    $count_stmt->close();
} else {
    $total_students = 0;
}
$total_pages = ceil($total_students / $per_page);

// Fetch students with filters and pagination
$students = [];
$valid_sort_columns = ['program', 'year_level'];
$sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'program';
$sort_order = strtoupper($sort_order) === 'DESC' ? 'DESC' : 'ASC';

// Build the main query with simplified sorting
$order_clause = '';
if ($sort_by === 'program') {
    $order_clause = "ORDER BY program $sort_order, year_level ASC, fullname ASC";
} elseif ($sort_by === 'year_level') {
    $order_clause = "ORDER BY year_level $sort_order, program ASC, fullname ASC";
} else {
    $order_clause = "ORDER BY program $sort_order, year_level ASC, fullname ASC";
}

$query = "SELECT * FROM students {$where_clause} {$order_clause} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $bind_params = array_merge($params, [$per_page, $offset]);
    $bind_types = $param_types . 'ii';
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

// Filter options are now available from db_connect.php
// $departments, $programs, and helper functions are loaded

// Year levels are also predefined in db_connect.php

// Get student for view only
$view_student = null;
if (isset($_GET['view'])) {
    $id = $_GET['view'];
    $query = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $view_student = $result->fetch_assoc();
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
    <link rel="stylesheet" href="assets/css/manage_students.css?v=<?php echo time(); ?>">
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
            <a href="manage_scholarships.php" class="sidebar-item">
                <i class="fas fa-graduation-cap"></i>
                Scholarships
            </a>
            <a href="manage_applications.php" class="sidebar-item">
                <i class="fas fa-file-alt"></i>
                Applications
            </a>
            <a href="manage_students.php" class="sidebar-item active">
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
                    <i class="fas fa-user-cog"></i>
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
    <div class="main-content">
        <div class="container">
       
        <!-- Compact Header -->
        <div class="compact-page-header">
            <h2 class="compact-title">
                <i class="fas fa-user-graduate"></i> 
                All Students 
                <span class="student-count">(<?php echo number_format($total_students); ?>)</span>
            </h2>
        </div>

        <!-- Compact Search and Filter Section -->
        <div class="compact-search-filter-section">
            <form method="GET" class="compact-filter-form" id="filterForm">
                <!-- Preserve current sort and pagination settings -->
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                <input type="hidden" name="order" value="<?php echo htmlspecialchars($sort_order); ?>">
                
                <!-- Compact Filters Row -->
                <div class="compact-filters-container">
                    <!-- Search Input -->
                    <div class="compact-search-group">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search search-icon"></i>
                            <input type="text" name="search" id="searchInput" placeholder="Search students..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="compact-search-input">
                            <?php if (!empty($search)): ?>
                                <button type="button" class="clear-search-compact" onclick="clearSearch()" title="Clear search">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Department Filter -->
                    <div class="compact-filter-group">
                        <select name="department" id="department" class="compact-select" onchange="updateProgramDropdown()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                        <?php echo $filter_department === $dept ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Program Filter -->
                    <div class="compact-filter-group">
                        <select name="program" id="program" class="compact-select" onchange="this.form.submit()">
                            <option value="">All Programs</option>
                            <?php 
                            // If a department is selected, only show programs for that department
                            if (!empty($filter_department) && isset($academic_structure[$filter_department])) {
                                foreach ($academic_structure[$filter_department] as $prog) {
                                    $selected = ($filter_program === $prog) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($prog) . '" ' . $selected . '>' . htmlspecialchars($prog) . '</option>';
                                }
                            } else {
                                // Show all programs if no department is selected
                                foreach ($programs as $prog) {
                                    $selected = ($filter_program === $prog) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($prog) . '" ' . $selected . '>' . htmlspecialchars($prog) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <!-- Year Filter -->
                    <div class="compact-filter-group">
                        <select name="year" id="year" class="compact-select" onchange="this.form.submit()">
                            <option value="">All Years</option>
                            <?php foreach ($year_levels as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" 
                                        <?php echo $filter_year === $year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="compact-action-group">
                        <button type="submit" class="compact-search-btn" title="Search">
                            <i class="fas fa-search"></i>
                        </button>
                        
                        <?php if (!empty($search) || !empty($filter_department) || !empty($filter_program) || !empty($filter_year)): ?>
                            <button type="button" class="compact-clear-btn" onclick="clearAllFilters()" title="Clear all filters">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Compact Results Summary -->
        <div class="compact-results-summary">
            <div class="compact-results-line">
                <span class="compact-count">
                    <i class="fas fa-users"></i>
                    <?php echo count($students); ?>/<?php echo number_format($total_students); ?>
                    <?php if ($page > 1): ?>
                        • Page <?php echo $page; ?>/<?php echo $total_pages; ?>
                    <?php endif; ?>
                </span>
                
                <span class="compact-sort">
                    <i class="fas fa-sort"></i>
                    <?php 
                    $sort_labels = [
                        'program' => 'Program', 
                        'year_level' => 'Year Level'
                    ];
                    echo $sort_labels[$sort_by] ?? 'Program';
                    echo ' (' . ($sort_order === 'ASC' ? '↑' : '↓') . ')';
                    ?>
                </span>
                
                <?php if (!empty($search) || !empty($filter_department) || !empty($filter_program) || !empty($filter_year)): ?>
                    <span class="compact-filters">
                        <i class="fas fa-filter"></i>
                        <?php 
                        $active_filters = [];
                        if (!empty($search)) $active_filters[] = '"' . htmlspecialchars($search) . '"';
                        if (!empty($filter_department)) $active_filters[] = htmlspecialchars($filter_department);
                        if (!empty($filter_program)) $active_filters[] = htmlspecialchars($filter_program);
                        if (!empty($filter_year)) $active_filters[] = 'Year ' . htmlspecialchars($filter_year);
                        echo implode(' • ', $active_filters);
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>


        <!-- Students Table -->
        <?php if (!empty($students)): ?>
            <div class="table-container">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Program</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="student-name-cell">
                                    <?php echo htmlspecialchars($student['fullname']); ?>
                                </td>
                                <td class="student-program-cell">
                                    <?php echo htmlspecialchars($student['program']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php
                        // Build query string for pagination links
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                        ?>
                        
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1) . $query_string; ?>" class="pagination-btn pagination-prev">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <a href="?page=1<?php echo $query_string; ?>" class="pagination-btn">1</a>
                            <?php if ($start_page > 2): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="pagination-btn pagination-current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i . $query_string; ?>" class="pagination-btn"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="pagination-ellipsis">...</span>
                            <?php endif; ?>
                            <a href="?page=<?php echo $total_pages . $query_string; ?>" class="pagination-btn"><?php echo $total_pages; ?></a>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1) . $query_string; ?>" class="pagination-btn pagination-next">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-info">
                        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        <span class="pagination-separator">•</span>
                        <span><?php echo number_format($total_students); ?> total students</span>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state-container">
                <div class="empty-state-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <?php if (!empty($search) || !empty($filter_department) || !empty($filter_program) || !empty($filter_year)): ?>
                    <h3>No Students Match Your Criteria</h3>
                    <p>Try adjusting your search terms or filters to find students.</p>
                    <button class="btn btn-secondary" onclick="clearAllFilters()">
                        <i class="fas fa-times-circle"></i>
                        Clear All Filters
                    </button>
                <?php else: ?>
                    <h3>No Students Found</h3>
                    <p>No students are currently registered in the system.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Enhanced Student Details Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content student-details-modal">
            <div class="modal-header">
                <div class="header-content">
                    <div class="student-avatar">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="header-text">
                        <h3 id="modalTitle">Student Profile</h3>
                        <p class="header-subtitle">Complete student information</p>
                    </div>
                </div>
                <button class="close-btn" onclick="closeModal()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="modal-body">
                <!-- Student Profile Section -->
                <div class="profile-section">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar-large">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div class="profile-info">
                                <h4 id="viewFullname" class="student-name">-</h4>
                                <p id="viewStudentNumber" class="student-number">-</p>
                                <div class="status-badge active">
                                    <i class="fas fa-circle"></i>
                                    Active Student
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="details-grid">
                    <!-- Contact Information -->
                    <div class="detail-section">
                        <div class="section-header">
                            <i class="fas fa-address-card"></i>
                            <h5>Contact Information</h5>
                        </div>
                        <div class="detail-items">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Email Address</label>
                                    <span id="viewEmail" class="detail-value">-</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Phone Number</label>
                                    <span id="viewPhone" class="detail-value">-</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Address</label>
                                    <span id="viewAddress" class="detail-value">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information -->
                    <div class="detail-section">
                        <div class="section-header">
                            <i class="fas fa-graduation-cap"></i>
                            <h5>Academic Information</h5>
                        </div>
                        <div class="detail-items">
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Program</label>
                                    <span id="viewProgram" class="detail-value">-</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Department</label>
                                    <span id="viewDepartment" class="detail-value">-</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon">
                                    <i class="fas fa-layer-group"></i>
                                </div>
                                <div class="detail-content">
                                    <label>Year Level</label>
                                    <span id="viewYearLevel" class="detail-value">-</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-icon gwa-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="detail-content">
                                    <label>General Weighted Average</label>
                                    <span id="viewGwa" class="detail-value gwa-value">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="stats-section">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="stat-content">
                                <span id="viewApplications" class="stat-number">-</span>
                                <label class="stat-label">Applications</label>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-content">
                                <span id="viewJoinDate" class="stat-number">-</span>
                                <label class="stat-label">Member Since</label>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <div class="stat-content">
                                <span id="viewApproved" class="stat-number">-</span>
                                <label class="stat-label">Approved</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-primary" onclick="closeModal()">
                        <i class="fas fa-check"></i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete functionality removed - View-only access -->

    <script>
        // Department to Program mapping for cascading dropdowns
        const departmentPrograms = <?php echo json_encode($academic_structure); ?>;
        
        function updateProgramDropdown() {
            const departmentSelect = document.getElementById('department');
            const programSelect = document.getElementById('program');
            const selectedDepartment = departmentSelect.value;
            
            // Clear current program options
            programSelect.innerHTML = '<option value="">All Programs</option>';
            
            if (selectedDepartment === '') {
                // If no department selected, show all programs
                <?php foreach ($programs as $prog): ?>
                    programSelect.innerHTML += '<option value="<?php echo htmlspecialchars($prog); ?>"><?php echo htmlspecialchars($prog); ?></option>';
                <?php endforeach; ?>
            } else {
                // Show only programs for selected department
                const programs = departmentPrograms[selectedDepartment] || [];
                programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program;
                    option.textContent = program;
                    programSelect.appendChild(option);
                });
            }
            
            // Submit form to apply filter
            document.getElementById('filterFormFilters').submit();
        }
        
        const modal = document.getElementById('studentModal');

        function viewStudent(id) {
            console.log('Viewing student ID:', id);
            
            // Check if modal exists
            if (!modal) {
                console.error('Modal element not found');
                alert('Modal not found. Please refresh the page.');
                return;
            }
            
            // Fetch student details via AJAX
            fetch(`manage_students.php?action=get_student_details&id=${id}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP error! status: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Check if all required elements exist
                        const elements = ['viewFullname', 'viewEmail', 'viewStudentNumber', 'viewGwa', 'viewApplications', 
                                        'viewPhone', 'viewAddress', 'viewProgram', 'viewDepartment', 'viewYearLevel'];
                        for (let elementId of elements) {
                            const element = document.getElementById(elementId);
                            if (!element) {
                                console.error('Element not found:', elementId);
                                alert('Modal elements missing. Please refresh the page.');
                                return;
                            }
                        }
                        
                        // Populate modal with data
                        document.getElementById('viewFullname').textContent = data.student.fullname || 'N/A';
                        document.getElementById('viewEmail').textContent = data.student.email || 'N/A';
                        document.getElementById('viewStudentNumber').textContent = data.student.student_number || 'N/A';
                        document.getElementById('viewPhone').textContent = data.student.phone || 'Not provided';
                        document.getElementById('viewAddress').textContent = data.student.address || 'Not provided';
                        document.getElementById('viewProgram').textContent = data.student.program || 'N/A';
                        document.getElementById('viewDepartment').textContent = data.student.department || 'N/A';
                        document.getElementById('viewYearLevel').textContent = data.student.year_level || 'N/A';
                        
                        // Format GWA with color coding
                        const gwaElement = document.getElementById('viewGwa');
                        const gwaValue = data.student.gwa ? parseFloat(data.student.gwa) : null;
                        if (gwaValue) {
                            gwaElement.textContent = gwaValue.toFixed(2);
                            // Color code GWA (1.0-1.5 = excellent, 1.6-2.0 = good, 2.1-3.0 = satisfactory)
                            if (gwaValue <= 1.5) {
                                gwaElement.className = 'detail-value gwa-value excellent';
                            } else if (gwaValue <= 2.0) {
                                gwaElement.className = 'detail-value gwa-value good';
                            } else {
                                gwaElement.className = 'detail-value gwa-value satisfactory';
                            }
                        } else {
                            gwaElement.textContent = 'N/A';
                            gwaElement.className = 'detail-value gwa-value';
                        }
                        
                        // Statistics
                        document.getElementById('viewApplications').textContent = data.application_count || '0';
                        
                        // Format join date
                        const joinDate = new Date(data.student.created_at);
                        const joinDateFormatted = joinDate.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short' 
                        });
                        document.getElementById('viewJoinDate').textContent = joinDateFormatted || 'N/A';
                        
                        // Approved applications count
                        document.getElementById('viewApproved').textContent = data.approved_count || '0';
                        
                        // Show modal
                        modal.style.display = 'block';
                        console.log('Modal displayed successfully');
                    } else {
                        console.error('API error:', data.error || data.message);
                        alert('Error loading student details: ' + (data.error || data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Error loading student details: ' + error.message);
                });
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });

        // Search and filter functions
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('filterForm').submit();
        }
        
        function clearAllFilters() {
            // Clear all form inputs
            document.getElementById('searchInput').value = '';
            document.getElementById('department').value = '';
            document.getElementById('program').value = '';
            document.getElementById('year').value = '';
            
            // Submit the form to apply changes
            document.getElementById('filterForm').submit();
        }
        
        // Auto-submit search after typing (with debounce)
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3 || this.value.length === 0) {
                    document.getElementById('filterForm').submit();
                }
            }, 500);
        });
        
        // Enter key support for search
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
        
        // Show student details if viewing
        <?php if ($view_student): ?>
            document.addEventListener('DOMContentLoaded', function() {
                viewStudent(<?php echo $view_student['id']; ?>);
            });
        <?php endif; ?>

        // Logout function now handled by custom-modal.js
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
