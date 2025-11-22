<?php
session_start();
require_once 'db_connect.php';
require_once 'notification_system.php';

// Simple authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit();
}

$student_id = $_SESSION['user_id'];

// Fetch student details
$student = [];
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);

if ($student_result && mysqli_num_rows($student_result) > 0) {
    $student = mysqli_fetch_assoc($student_result);
} else {
    // Fallback if student not found
    $student = [
        'fullname' => $_SESSION['user_name'] ?? 'Student', 
        'email' => $_SESSION['user_email'] ?? 'student@example.com',
        'student_number' => 'Not available',
        'program' => 'Not specified',
        'department' => 'Not specified',
        'year_level' => 'Not specified',
        'gwa' => 'Not specified'
    ];
}
mysqli_stmt_close($stmt);

// Fetch available scholarships
$scholarships = [];
$scholarship_query = "
    SELECT s.*, 
        (SELECT COUNT(*) 
         FROM applications a 
         WHERE a.scholarship_id = s.id 
         AND a.student_id = ?) as has_applied
    FROM scholarships s 
    WHERE s.deadline >= CURDATE() 
    AND s.status = 'active'
";
$stmt = mysqli_prepare($conn, $scholarship_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$scholarship_result = mysqli_stmt_get_result($stmt);

if ($scholarship_result) {
    while ($row = mysqli_fetch_assoc($scholarship_result)) {
        $scholarships[] = $row;
    }
}
mysqli_stmt_close($stmt);

// Prepare upcoming scholarships (soonest deadlines first)
$upcoming_scholarships = $scholarships;
if (!empty($upcoming_scholarships)) {
    usort($upcoming_scholarships, function($a, $b) {
        return strtotime($a['deadline']) <=> strtotime($b['deadline']);
    });
    $upcoming_scholarships = array_slice($upcoming_scholarships, 0, 3);
}

// Get session messages
$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch student's applications
$applications = [];
$app_query = "
    SELECT s.title, s.sponsor as organization, s.deadline, a.status, a.application_date 
    FROM applications a 
    JOIN scholarships s ON a.scholarship_id = s.id 
    WHERE a.student_id = ? 
    ORDER BY 
        CASE a.status 
            WHEN 'pending' THEN 1 
            WHEN 'under_review' THEN 2
            WHEN 'rejected' THEN 3 
            WHEN 'approved' THEN 4 
            ELSE 5 
        END,
        a.application_date DESC
";
$stmt = mysqli_prepare($conn, $app_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$app_result = mysqli_stmt_get_result($stmt);

if ($app_result) {
    while ($row = mysqli_fetch_assoc($app_result)) {
        $applications[] = $row;
    }
}
mysqli_stmt_close($stmt);

// Get real notifications
$notificationSystem = new NotificationSystem($conn);
$notifications = $notificationSystem->getUserNotifications($student_id, 'student', 10);

// Ensure each student only sees their own welcome notification
// Older welcome messages included the student's name in the message body,
// which could leave stray messages if test accounts were created.
$currentStudentName = $student['fullname'] ?? ($_SESSION['user_name'] ?? '');

if (!empty($notifications)) {
    $notifications = array_values(array_filter($notifications, function ($notification) use ($currentStudentName) {
        // Apply extra filtering only to general "Welcome to ScholarSeek!" notifications
        if (
            isset($notification['type'], $notification['title'], $notification['message']) &&
            $notification['type'] === 'general' &&
            strpos($notification['title'], 'Welcome to ScholarSeek!') !== false
        ) {
            // Newer welcome messages are generic (no name) but contain this fixed text
            $genericText = 'Your account has been created successfully. Start exploring scholarship opportunities today.';

            // If it's a legacy welcome message with a name, only show it when it matches the
            // currently logged-in student's name. Otherwise, hide it.
            if (!empty($currentStudentName)) {
                if (strpos($notification['message'], $currentStudentName) === false &&
                    strpos($notification['message'], $genericText) === false
                ) {
                    return false;
                }
            } else {
                // If we cannot determine the current student's name, hide non-generic legacy messages
                if (strpos($notification['message'], $genericText) === false) {
                    return false;
                }
            }
        }

        return true;
    }));
}

// Recalculate unread count based on the filtered notifications list
$unread_count = 0;
foreach ($notifications as $notification) {
    if (isset($notification['is_read']) && !$notification['is_read']) {
        $unread_count++;
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
    <link rel="stylesheet" href="assets/css/modern-toggle.css">
    <link rel="stylesheet" href="assets/css/student_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/custom-modal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/ftue-onboarding.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
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
            <a href="#dashboard" class="sidebar-item active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#scholarships" class="sidebar-item"><i class="fas fa-graduation-cap"></i> Scholarships</a>
            <a href="#applications" class="sidebar-item"><i class="fas fa-file-alt"></i>Applications</a>
            <a href="#notifications" class="sidebar-item"><i class="fas fa-bell"></i> Notifications</a>
            <a href="#profile" class="sidebar-item"><i class="fas fa-user"></i> Profile</a>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user-info">
                <div class="user-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="user-name">
                    <?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?>
                </div>
            </div>
            <button type="button" onclick="confirmLogout();" class="compact-logout-btn">
                <i class="fas fa-power-off"></i>
                <span>Logout</span>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container main-content-wrapper">
        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle alert-icon"></i>
                <span><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <span><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Dashboard Overview -->
        <section id="dashboard" class="dashboard-section">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1>Welcome back, <?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?>!</h1>
                    <p>Track your scholarship applications and discover new opportunities</p>
                </div>
                <button class="ftue-help-button" onclick="window.ftueOnboarding.showOnboarding()" title="Start onboarding tour">
                    <i class="fas fa-question"></i>
                </button>
            </div>

            <div class="stats-grid compact-stats-grid">
                <div class="stat-card stat-card-primary" data-section="scholarships">
                    <div class="stat-header">
                        <div class="stat-icon-circle">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-trend">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo count($scholarships); ?></h3>
                        <p class="stat-label">Available Scholarships</p>
                    </div>
                </div>

                <div class="stat-card stat-card-success" data-section="applications" data-filter="all">
                    <div class="stat-header">
                        <div class="stat-icon-circle">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="stat-trend success">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo count($applications); ?></h3>
                        <p class="stat-label">My Applications</p>
                    </div>
                </div>

                <div class="stat-card stat-card-warning" data-section="applications" data-filter="pending">
                    <div class="stat-header">
                        <div class="stat-icon-circle">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-trend warning">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">
                            <?php echo count(array_filter($applications, function ($app) {
                                return $app['status'] == 'pending';
                            })); ?>
                        </h3>
                        <p class="stat-label">Pending Reviews</p>
                    </div>
                </div>

                <div class="stat-card stat-card-info" data-section="applications" data-filter="approved">
                    <div class="stat-header">
                        <div class="stat-icon-circle">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-trend info">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number">
                            <?php echo count(array_filter($applications, function ($app) {
                                return $app['status'] == 'approved';
                            })); ?>
                        </h3>
                        <p class="stat-label">Approved</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($upcoming_scholarships)): ?>
            <div class="dashboard-row upcoming-deadlines-section">
                <div class="panel panel-upcoming">
                    <div class="panel-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Deadlines</h3>
                        <span class="panel-subtitle">Don't miss these opportunities</span>
                    </div>
                    <ul class="upcoming-list">
                        <?php foreach ($upcoming_scholarships as $sch): ?>
                            <?php
                                $days_left = null;
                                if (!empty($sch['deadline'])) {
                                    $days_left = ceil((strtotime($sch['deadline']) - time()) / 86400);
                                }
                            ?>
                            <li class="upcoming-item">
                                <div class="upcoming-main">
                                    <h4 class="upcoming-title"><?php echo htmlspecialchars($sch['title']); ?></h4>
                                    <span class="upcoming-sponsor"><?php echo htmlspecialchars($sch['sponsor'] ?? ''); ?></span>
                                </div>
                                <div class="upcoming-meta">
                                    <?php if (!empty($sch['deadline'])): ?>
                                        <span class="upcoming-date"><?php echo date('M j, Y', strtotime($sch['deadline'])); ?></span>
                                        <?php if ($days_left !== null): ?>
                                            <span class="upcoming-badge <?php echo $days_left <= 3 ? 'upcoming-badge-danger' : ($days_left <= 7 ? 'upcoming-badge-warning' : ''); ?>">
                                                <?php echo $days_left <= 0 ? 'Deadline today' : $days_left . ' day' . ($days_left === 1 ? '' : 's') . ' left'; ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!empty($sch['has_applied'])): ?>
                                        <span class="upcoming-status applied"><i class="fas fa-check"></i> Applied</span>
                                    <?php else: ?>
                                        <a href="apply_scholarship.php?id=<?php echo (int)$sch['id']; ?>" class="upcoming-status action">Apply</a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
        </section>
        
        <!-- Browse Scholarships -->
        <section id="scholarships" class="dashboard-section">
            <div class="section-header">
                <h2>Available Scholarships</h2>
                <div class="search-box">
                    <label for="searchScholarships" class="sr-only">Search scholarships</label>
                    <input type="text" id="searchScholarships" placeholder="Search scholarships by title or sponsor...">
                    <button type="button" id="searchBtn" class="search-btn" title="Search scholarships"><i class="fas fa-search"></i></button>
                    <button type="button" id="clearSearchBtn" class="clear-btn hidden" title="Clear search"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="scholarships-grid">
                <?php if (!empty($scholarships)): ?>
                    <?php foreach ($scholarships as $scholarship): ?>
                        <div class="scholarship-card" data-search="<?php echo strtolower(htmlspecialchars($scholarship['title'] . ' ' . $scholarship['sponsor'])); ?>">
                            <div class="scholarship-header">
                                <h3 class="scholarship-title"><?php echo htmlspecialchars($scholarship['title']); ?></h3>
                            </div>
                            <div class="scholarship-body">
                                <p class="description"><?php echo htmlspecialchars(substr($scholarship['description'], 0, 150) . '...'); ?></p>
                                <div class="scholarship-details">
                                    <div class="detail"><i class="fas fa-money-bill-wave"></i> <span>₱<?php echo number_format($scholarship['amount']); ?></span></div>
                                    <div class="detail"><i class="fas fa-clock"></i> <span><?php echo date('M d, Y', strtotime($scholarship['deadline'])); ?></span></div>
                                </div>
                            </div>
                            <div class="scholarship-footer">
                                <?php if ($scholarship['has_applied'] > 0): ?>
                                    <button class="btn-applied" disabled><i class="fas fa-check"></i> Applied</button>
                                <?php else: ?>
                                    <a href="apply_scholarship.php?id=<?php echo $scholarship['id']; ?>" class="btn-apply">
                                        <i class="fas fa-paper-plane"></i> Apply Now
                                    </a>
                                <?php endif; ?>
                                <button class="btn-details" onclick="showScholarshipDetails(event, <?php echo $scholarship['id']; ?>)" title="View scholarship details"><i class="fas fa-info-circle"></i> Details</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-graduation-cap"></i>
                        <h3>No Scholarships Available</h3>
                        <p>Check back later for new opportunities.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- My Applications -->
        <section id="applications" class="dashboard-section">
            <div class="section-header">
                <h2>My Applications</h2>
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">All</button>
                    <button class="filter-tab" data-filter="pending">Pending</button>
                    <button class="filter-tab" data-filter="approved">Approved</button>
                    <button class="filter-tab" data-filter="rejected">Rejected</button>
                </div>
            </div>
            <?php if (!empty($applications)): ?>
                <div class="applications-grid">
                    <?php foreach ($applications as $application): ?>
                        <div class="application-card" data-status="<?php echo strtolower($application['status']); ?>">
                            <div class="application-card-header">
                                <div class="application-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <span class="status-badge status-<?php echo strtolower($application['status']); ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo ucfirst($application['status']); ?>
                                </span>
                            </div>
                            <div class="application-card-body">
                                <h3 class="application-title"><?php echo htmlspecialchars($application['title']); ?></h3>
                                <p class="application-org">
                                    <i class="fas fa-building"></i>
                                    <?php echo htmlspecialchars($application['organization']); ?>
                                </p>
                                <div class="application-meta">
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-check"></i>
                                        <div class="meta-content">
                                            <span class="meta-label">Applied</span>
                                            <span class="meta-value"><?php echo date('M d, Y', strtotime($application['application_date'])); ?></span>
                                        </div>
                                    </div>
                                    <div class="meta-item">
                                        <i class="fas fa-calendar-times"></i>
                                        <div class="meta-content">
                                            <span class="meta-label">Deadline</span>
                                            <span class="meta-value"><?php echo date('M d, Y', strtotime($application['deadline'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="application-card-footer">
                                <?php if ($application['status'] == 'pending'): ?>
                                    <div class="progress-indicator">
                                        <div class="progress-bar">
                                            <div class="progress-fill progress-50"></div>
                                        </div>
                                        <span class="progress-text">Under Review</span>
                                    </div>
                                <?php elseif ($application['status'] == 'approved'): ?>
                                    <div class="application-message success">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Congratulations! Your application has been approved.</span>
                                    </div>
                                <?php else: ?>
                                    <div class="application-message rejected">
                                        <i class="fas fa-times-circle"></i>
                                        <span>Application was not successful this time.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-file-alt"></i>
                    <h3>No Applications Yet</h3>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Profile Section -->
        <section id="profile" class="dashboard-section">
            <div class="section-header">
                <h2><i class="fas fa-user-circle"></i> My Profile</h2>
                <div class="profile-actions">
                    <button class="btn-edit-profile" onclick="toggleEditMode()">
                        <i class="fas fa-edit"></i> Edit Profile
                    </button>
                </div>
            </div>
            
            <?php if (!empty($student)): ?>
                <!-- Profile Overview Card -->
                <div class="profile-overview-card">
                    <div class="profile-header">
                        <div class="profile-picture-container">
                            <div class="profile-picture">
                                <?php if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" alt="Profile Picture" id="profilePictureImg">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <i class="fas fa-user-graduate"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="profile-picture-overlay" onclick="openProfilePictureModal()">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h3 class="profile-name"><?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?></h3>
                            <p class="profile-email"><?php echo htmlspecialchars($student['email'] ?? 'No email'); ?></p>
                            <div class="profile-badges">
                                <span class="badge badge-primary"><?php echo htmlspecialchars($student['year_level'] ?? 'N/A'); ?></span>
                                <span class="badge badge-secondary"><?php echo htmlspecialchars($student['program'] ?? 'No Program'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Details Tabs -->
                <div class="profile-tabs">
                    <div class="tab-buttons">
                        <button class="tab-btn active" data-tab="basic">
                            <i class="fas fa-user"></i> Basic Information
                        </button>
                        <button class="tab-btn" data-tab="contact">
                            <i class="fas fa-address-book"></i> Contact Details
                        </button>
                        <button class="tab-btn" data-tab="academic">
                            <i class="fas fa-graduation-cap"></i> Academic Info
                        </button>
                        <button class="tab-btn" data-tab="security">
                            <i class="fas fa-shield-alt"></i> Security
                        </button>
                    </div>

                    <!-- Basic Information Tab -->
                    <div class="tab-content active" id="basic-tab">
                        <div class="profile-card">
                            <div class="card-header">
                                <h4><i class="fas fa-user"></i> Basic Information</h4>
                            </div>
                            <div class="card-body">
                                <form id="basicInfoForm" class="profile-form">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="fullname">Full Name</label>
                                            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($student['fullname'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="student_number">Student Number</label>
                                            <input type="text" id="student_number" name="student_number" value="<?php echo htmlspecialchars($student['student_number'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" readonly>
                                            <small class="form-note">Email cannot be changed. Contact admin if needed.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="date_of_birth">Date of Birth</label>
                                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo (!empty($student['date_of_birth']) && $student['date_of_birth'] !== 'Not specified') ? htmlspecialchars($student['date_of_birth']) : ''; ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-actions hidden">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="cancelEdit('basic')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Details Tab -->
                    <div class="tab-content" id="contact-tab">
                        <div class="profile-card">
                            <div class="card-header">
                                <h4><i class="fas fa-address-book"></i> Contact Information</h4>
                            </div>
                            <div class="card-body">
                                <form id="contactInfoForm" class="profile-form">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="phone">Phone Number</label>
                                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>">
                                        </div>
                                        <div class="form-group full-width">
                                            <label for="address">Address</label>
                                            <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-actions" style="display: none;">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="cancelEdit('contact')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Academic Information Tab -->
                    <div class="tab-content" id="academic-tab">
                        <div class="profile-card">
                            <div class="card-header">
                                <h4><i class="fas fa-graduation-cap"></i> Academic Information</h4>
                            </div>
                            <div class="card-body">
                                <form id="academicInfoForm" class="profile-form">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="department">Department/School</label>
                                            <select id="department" name="department" disabled>
                                                <option value="">Select Department</option>
                                                <option value="School of Arts and Sciences" <?php echo ($student['department'] ?? '') === 'School of Arts and Sciences' ? 'selected' : ''; ?>>School of Arts and Sciences</option>
                                                <option value="School of Criminal Justice Education" <?php echo ($student['department'] ?? '') === 'School of Criminal Justice Education' ? 'selected' : ''; ?>>School of Criminal Justice Education</option>
                                                <option value="School of Management and Entrepreneurship" <?php echo ($student['department'] ?? '') === 'School of Management and Entrepreneurship' ? 'selected' : ''; ?>>School of Management and Entrepreneurship</option>
                                                <option value="School of Nursing and Health Sciences" <?php echo ($student['department'] ?? '') === 'School of Nursing and Health Sciences' ? 'selected' : ''; ?>>School of Nursing and Health Sciences</option>
                                                <option value="School of Engineering" <?php echo ($student['department'] ?? '') === 'School of Engineering' ? 'selected' : ''; ?>>School of Engineering</option>
                                                <option value="School of Technology and Computer Studies" <?php echo ($student['department'] ?? '') === 'School of Technology and Computer Studies' ? 'selected' : ''; ?>>School of Technology and Computer Studies</option>
                                                <option value="School of Teacher Education" <?php echo ($student['department'] ?? '') === 'School of Teacher Education' ? 'selected' : ''; ?>>School of Teacher Education</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="program">Program</label>
                                            <input type="text" id="program" name="program" value="<?php echo htmlspecialchars($student['program'] ?? ''); ?>" readonly>
                                        </div>
                                        <div class="form-group">
                                            <label for="year_level">Year Level</label>
                                            <select id="year_level" name="year_level" disabled>
                                                <option value="">Select Year Level</option>
                                                <option value="1st Year" <?php echo ($student['year_level'] ?? '') === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                                <option value="2nd Year" <?php echo ($student['year_level'] ?? '') === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                                <option value="3rd Year" <?php echo ($student['year_level'] ?? '') === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                                <option value="4th Year" <?php echo ($student['year_level'] ?? '') === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="gwa">GWA (General Weighted Average)</label>
                                            <input type="number" id="gwa" name="gwa" step="0.01" min="1.00" max="5.00" value="<?php echo htmlspecialchars($student['gwa'] ?? ''); ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="form-actions hidden">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="cancelEdit('academic')">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-content" id="security-tab">
                        <div class="profile-card">
                            <div class="card-header">
                                <h4><i class="fas fa-shield-alt"></i> Security Settings</h4>
                            </div>
                            <div class="card-body">
                                <div class="security-section">
                                    <h5><i class="fas fa-key"></i> Change Password</h5>
                                    <p class="security-description">Keep your account secure by using a strong password</p>
                                    <form id="changePasswordForm" class="profile-form">
                                        <div class="form-group">
                                            <label for="current_password">Current Password</label>
                                            <input type="password" id="current_password" name="current_password" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="new_password">New Password</label>
                                            <input type="password" id="new_password" name="new_password" required>
                                            <small class="form-note">Password must be at least 6 characters long</small>
                                            <small id="password_same_warning" class="form-note form-note-error hidden">New password must be different from your current password.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirm_password">Confirm New Password</label>
                                            <input type="password" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Change Password
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- Profile Picture Upload Modal -->
        <div id="profilePictureModal" class="modal">
            <div class="modal-content profile-picture-modal">
                <div class="modal-header">
                    <h3><i class="fas fa-camera"></i> Update Profile Picture</h3>
                    <span class="close" onclick="closeProfilePictureModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="profilePictureForm" enctype="multipart/form-data">
                        <div class="upload-area">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <p>Click to select a photo or drag and drop</p>
                            <input type="file" id="profilePictureInput" name="profile_picture" accept="image/*" class="hidden">
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('profilePictureInput').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                        </div>
                        <div class="upload-preview hidden">
                            <img id="previewImage" src="" alt="Preview">
                            <div class="preview-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i> Upload Photo
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="clearPreview()">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Notifications Section -->
        <section id="notifications" class="dashboard-section">
            <h2>📬 Notifications & Inbox</h2>
            
            <div class="notifications-header">
                <div class="notifications-stats">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo count($notifications); ?></span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number unread"><?php echo $unread_count; ?></span>
                        <span class="stat-label">Unread</span>
                    </div>
                </div>
            </div>
            
            <div class="notifications-container">
                <?php if (empty($notifications)): ?>
                    <div class="no-notifications">
                        <div class="no-data-icon">
                            <i class="fas fa-bell-slash"></i>
                        </div>
                        <h3>No Notifications Yet</h3>
                        <p>You'll receive notifications here when there are updates about your scholarship applications.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?> notification-<?php echo $notification['type']; ?>" 
                             data-notification-id="<?php echo $notification['id']; ?>"
                             data-related-type="<?php echo htmlspecialchars($notification['related_type'] ?? ''); ?>"
                             data-related-id="<?php echo htmlspecialchars($notification['related_id'] ?? ''); ?>"
                             class="notification-clickable">
                            <div class="notification-icon">
                                <?php
                                $icons = [
                                    'application_approved' => 'fas fa-check-circle',
                                    'application_rejected' => 'fas fa-times-circle',
                                    'application_pending' => 'fas fa-clock',
                                    'scholarship_deadline' => 'fas fa-calendar-alt',
                                    'general' => 'fas fa-info-circle'
                                ];
                                $icon = $icons[$notification['type']] ?? 'fas fa-bell';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-header">
                                    <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                    <span class="notification-time"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></span>
                                </div>
                                <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <?php if (!$notification['is_read']): ?>
                                    <button class="notification-action" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-check"></i> Mark as Read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($unread_count > 0): ?>
                        <div class="notification-actions">
                            <button class="btn-mark-all-read" onclick="markAllAsRead()">
                                <i class="fas fa-check-double"></i> Mark All as Read
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Scholarship Details Modal -->
        <div id="scholarshipModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Scholarship Details</h3>
                    <span class="close" id="scholarshipModalClose">&times;</span>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>

        <!-- Notification Detail Modal -->
        <div id="notificationModal" class="modal">
            <div class="modal-content notification-modal">
                <div class="modal-header">
                    <h3 id="notificationModalTitle">Notification Details</h3>
                    <span class="close" id="notificationModalClose">&times;</span>
                </div>
                <div class="modal-body" id="notificationModalBody">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button class="btn-primary" id="notificationModalOk">Got it!</button>
                </div>
            </div>
        </div>

    <script>
        // ============================================
        // INITIALIZATION - Wait for DOM to be ready
        // ============================================
        function initializeAllModals() {
            console.log('initializeAllModals() called');
            // Mobile menu toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function () {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.toggle('active');
                    }
                });
            }

        // Theme management (light/dark for sidebar)
        const THEME_STORAGE_KEY = 'scholarseek_theme';

        function applyTheme(theme) {
            const body = document.body;
            const toggleBtn = document.getElementById('themeToggle');

            if (theme === 'dark') {
                body.classList.add('theme-dark');
            } else {
                body.classList.remove('theme-dark');
                theme = 'light';
            }

            try {
                localStorage.setItem(THEME_STORAGE_KEY, theme);
            } catch (e) {
                // Ignore storage errors (e.g., private mode)
            }

            if (toggleBtn) {
                const icon = toggleBtn.querySelector('i');
                const label = toggleBtn.querySelector('span');

                if (theme === 'dark') {
                    if (icon) {
                        icon.classList.remove('fa-moon');
                        icon.classList.add('fa-sun');
                    }
                    if (label) {
                        label.textContent = 'Light mode';
                    }
                } else {
                    if (icon) {
                        icon.classList.remove('fa-sun');
                        icon.classList.add('fa-moon');
                    }
                    if (label) {
                        label.textContent = 'Dark mode';
                    }
                }
            }
        }

        // Section navigation with state persistence
        function showSection(sectionId) {
            console.log('showSection() called with:', sectionId);
            // Remove active class from all sections and links
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.classList.remove('active');
            });
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.classList.remove('active');
            });

            // Add active class to target section and corresponding link
            const targetSection = document.getElementById(sectionId);
            const targetLink = document.querySelector(`.sidebar a[href="#${sectionId}"]`);
            
            console.log('Target section found:', !!targetSection);
            console.log('Target link found:', !!targetLink);
            
            if (targetSection && targetLink) {
                targetSection.classList.add('active');
                targetLink.classList.add('active');
                console.log('Active class added to section:', sectionId);
                
                // Update URL hash without triggering page reload
                history.replaceState(null, null, `#${sectionId}`);
                
                // Save current section to localStorage
                localStorage.setItem('scholarseek_current_section', sectionId);
                
                // Update page title to reflect current section
                const sectionTitles = {
                    'dashboard': 'Dashboard',
                    'scholarships': 'Scholarships',
                    'applications': 'My Applications',
                    'notifications': 'Notifications',
                    'profile': 'Profile'
                };
                document.title = `${sectionTitles[sectionId] || 'Dashboard'} - ScholarSeek`;
                
                // Smooth scroll to top of section (optional)
                targetSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Close mobile menu
            document.querySelector('.sidebar').classList.remove('active');
        }

        // Handle sidebar navigation clicks
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href').startsWith('#')) {
                        const targetId = this.getAttribute('href').substring(1).trim();
                        // Only handle section navigation if targetId is not empty
                        if (targetId) {
                            e.preventDefault();
                            showSection(targetId);
                        }
                    }
                });
            });

        // Initialize page with correct section on load
        try {
            let currentSection = 'dashboard'; // default section
            
            // Check URL hash first
            if (window.location.hash) {
                const hashSection = window.location.hash.substring(1).trim();
                if (hashSection && document.getElementById(hashSection)) {
                    currentSection = hashSection;
                }
            } else {
                // Check localStorage for saved section
                const savedSection = localStorage.getItem('scholarseek_current_section');
                if (savedSection && typeof savedSection === 'string' && savedSection.trim().length > 0 && document.getElementById(savedSection)) {
                    currentSection = savedSection;
                }
            }
            
            console.log('Calling showSection with:', currentSection);
            showSection(currentSection);
        } catch (e) {
            console.error('Error initializing section:', e);
            showSection('dashboard');
        }

            // Initialize theme from storage (default to light)
            let savedTheme = 'light';
            try {
                const stored = localStorage.getItem(THEME_STORAGE_KEY);
                if (stored === 'dark' || stored === 'light') {
                    savedTheme = stored;
                }
            } catch (e) {
                // ignore
            }
            applyTheme(savedTheme);

            // Wire up theme toggle button
            const themeToggleBtn = document.getElementById('themeToggle');
            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    const isDark = document.body.classList.contains('theme-dark');
                    applyTheme(isDark ? 'light' : 'dark');
                });
            }

        // Make dashboard stat cards behave like navigation buttons
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', function() {
                const targetSection = this.getAttribute('data-section');
                const filter = this.getAttribute('data-filter');

                if (targetSection) {
                    showSection(targetSection);

                    // If navigating to applications, optionally apply filter
                    if (targetSection === 'applications' && filter) {
                        const filterTab = document.querySelector(`.filter-tab[data-filter="${filter}"]`);
                        if (filterTab) {
                            filterTab.click();
                        }
                    }
                }
            });
        });

        // Handle browser back/forward buttons
        window.addEventListener('hashchange', function() {
            if (window.location.hash) {
                const hashSection = window.location.hash.substring(1).trim();
                if (hashSection && document.getElementById(hashSection)) {
                    showSection(hashSection);
                }
            } else {
                showSection('dashboard');
            }
        });

        // Scholarship search functionality
        const searchInput = document.getElementById('searchScholarships');
        const searchBtn = document.getElementById('searchBtn');
        const clearBtn = document.getElementById('clearSearchBtn');
        
        function performSearch() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;
            
            document.querySelectorAll('.scholarship-card').forEach(card => {
                const searchData = card.getAttribute('data-search');
                if (searchData.includes(searchTerm)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show/hide clear button
            if (searchTerm.length > 0) {
                clearBtn.style.display = 'inline-block';
            } else {
                clearBtn.style.display = 'none';
            }
            
            // Show message if no results
            const scholarshipsGrid = document.querySelector('.scholarships-grid');
            let noResultsMsg = document.querySelector('.no-search-results');
            
            if (visibleCount === 0 && searchTerm.length > 0) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'no-search-results';
                    noResultsMsg.innerHTML = '<i class="fas fa-search"></i><p>No scholarships found matching "' + searchTerm + '"</p>';
                    scholarshipsGrid.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }
        
        // Search on input (real-time)
        searchInput.addEventListener('input', performSearch);
        
        // Search on button click
        searchBtn.addEventListener('click', performSearch);
        
        // Search on Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performSearch();
            }
        });
        
        // Clear search
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            performSearch();
            searchInput.focus();
        });

        // Modal functionality
        const modal = document.getElementById('scholarshipModal');
        const scholarshipCloseBtn = document.getElementById('scholarshipModalClose');

        function showScholarshipDetails(e, scholarshipId) {
            // Add loading state to button
            const clickedButton = e.target.closest('.btn-details');
            const originalText = clickedButton.innerHTML;
            clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            clickedButton.disabled = true;
            
            document.getElementById('modalBody').innerHTML =
                "<div class='modal-loading'><div class='loading-spinner'></div><p>Loading scholarship details...</p></div>";
            modal.style.display = 'block';

            // Simulate API call to fetch scholarship details
            setTimeout(() => {
                // Find scholarship data from the page
                const scholarshipCards = document.querySelectorAll('.scholarship-card');
                let scholarshipData = null;
                
                scholarshipCards.forEach(card => {
                    const detailsBtn = card.querySelector('.btn-details');
                    if (detailsBtn && detailsBtn.onclick.toString().includes(scholarshipId)) {
                        const titleEl = card.querySelector('.scholarship-title') || card.querySelector('h3');
                        const title = titleEl ? titleEl.textContent : '';
                        const descriptionEl = card.querySelector('.description');
                        const description = descriptionEl ? descriptionEl.textContent : '';

                        const detailSpans = card.querySelectorAll('.detail span');
                        const amount = detailSpans[0] ? detailSpans[0].textContent : '';
                        const deadline = detailSpans[1] ? detailSpans[1].textContent : '';

                        scholarshipData = { title, description, amount, deadline };
                    }
                });
                
                if (scholarshipData) {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="scholarship-modal-content">
                            <div class="modal-header-info">
                                <h3>${scholarshipData.title}</h3>
                            </div>
                            <div class="modal-details-grid">
                                <div class="modal-detail-item">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <div>
                                        <strong>Amount</strong>
                                        <p>${scholarshipData.amount}</p>
                                    </div>
                                </div>
                                <div class="modal-detail-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <strong>Deadline</strong>
                                        <p>${scholarshipData.deadline}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-description">
                                <h4>Description</h4>
                                <p>${scholarshipData.description}</p>
                            </div>
                            <div class="modal-actions">
                                <a href="apply_scholarship.php?id=${scholarshipId}" class="btn-apply">
                                    <i class="fas fa-paper-plane"></i> Apply Now
                                </a>
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('modalBody').innerHTML = `
                        <div class="modal-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Error Loading Details</h4>
                            <p>Unable to load scholarship details. Please try again.</p>
                        </div>
                    `;
                }
                
                // Restore button state
                clickedButton.innerHTML = originalText;
                clickedButton.disabled = false;
            }, 800);
        }

        scholarshipCloseBtn.addEventListener('click', function () {
            modal.style.display = 'none';
        });

        window.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function (e) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            if (!sidebar.contains(e.target) && !toggle.contains(e.target) && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // Filter functionality for applications
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                // Filter applications
                document.querySelectorAll('.application-card').forEach(card => {
                    const status = card.getAttribute('data-status');

                    if (filter === 'all' || status === filter) {
                        // Use block so cards keep their natural layout inside the grid
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Notification management functions
        function markAsRead(notificationId) {
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            const button = notificationElement?.querySelector('.notification-action');
            
            // Show loading state
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking...';
                button.disabled = true;
            }
            
            fetch('notification_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI without full page reload
                    if (notificationElement) {
                        notificationElement.classList.remove('unread');
                        notificationElement.classList.add('read');
                        if (button) {
                            button.remove();
                        }
                    }
                    
                    // Show success message
                    showNotificationToast('Notification marked as read', 'success');
                } else {
                    console.error('Failed to mark notification as read:', data.error);
                    showNotificationToast('Failed to mark as read', 'error');
                    
                    // Restore button state
                    if (button) {
                        button.innerHTML = '<i class="fas fa-check"></i> Mark as Read';
                        button.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationToast('Network error occurred', 'error');
                
                // Restore button state
                if (button) {
                    button.innerHTML = '<i class="fas fa-check"></i> Mark as Read';
                    button.disabled = false;
                }
            });
        }

        function markAllAsRead() {
            const button = document.querySelector('.btn-mark-all-read');
            
            // Show loading state
            if (button) {
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Marking All...';
                button.disabled = true;
            }
            
            fetch('notification_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update all unread notifications
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                        const actionBtn = item.querySelector('.notification-action');
                        if (actionBtn) {
                            actionBtn.remove();
                        }
                    });
                    
                    // Remove the mark all button
                    if (button) {
                        button.parentElement.remove();
                    }
                    
                    // Show success message
                    showNotificationToast('All notifications marked as read', 'success');
                } else {
                    console.error('Failed to mark all notifications as read:', data.error);
                    showNotificationToast('Failed to mark all as read', 'error');
                    
                    // Restore button state
                    if (button) {
                        button.innerHTML = '<i class="fas fa-check-double"></i> Mark All as Read';
                        button.disabled = false;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationToast('Network error occurred', 'error');
                
                // Restore button state
                if (button) {
                    button.innerHTML = '<i class="fas fa-check-double"></i> Mark All as Read';
                    button.disabled = false;
                }
            });
        }

        // Show toast notification
        function showNotificationToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `notification-toast notification-toast-${type}`;
            toast.innerHTML = `
                <div class="toast-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Show toast
            setTimeout(() => toast.classList.add('show'), 100);
            
            // Hide toast after 3 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Make notification items clickable
        document.addEventListener('click', function(e) {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem && !e.target.closest('.notification-action')) {
                const notificationId = notificationItem.dataset.notificationId;
                if (notificationId) {
                    showNotificationDetails(notificationId);
                    
                    // Mark as read if unread
                    if (notificationItem.classList.contains('unread')) {
                        markAsRead(notificationId);
                    }
                }
            }
        });

        // Show notification details in modal
        function showNotificationDetails(notificationId) {
            const modal = document.getElementById('notificationModal');
            const modalTitle = document.getElementById('notificationModalTitle');
            const modalBody = document.getElementById('notificationModalBody');
            
            // Show loading state
            modalTitle.textContent = 'Loading...';
            modalBody.innerHTML = `
                <div class="modal-loading">
                    <div class="loading-spinner"></div>
                    <p>Loading notification details...</p>
                </div>
            `;
            modal.style.display = 'block';
            
            // Fetch notification details
            fetch(`get_notification_details.php?id=${notificationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        modalTitle.textContent = data.notification.title;
                        modalBody.innerHTML = data.formatted_content;
                    } else {
                        modalTitle.textContent = 'Error';
                        modalBody.innerHTML = `
                            <div class="notification-error">
                                <div class="notification-icon-large">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <h4>Unable to Load Details</h4>
                                <p>${data.error || 'An error occurred while loading notification details.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching notification details:', error);
                    modalTitle.textContent = 'Error';
                    modalBody.innerHTML = `
                        <div class="notification-error">
                            <div class="notification-icon-large">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <h4>Network Error</h4>
                            <p>Unable to load notification details. Please try again.</p>
                        </div>
                    `;
                });
        }

            // ============================================
            // NOTIFICATION MODAL HANDLERS
            // ============================================
            const notificationModalClose = document.getElementById('notificationModalClose');
            const notificationModalOk = document.getElementById('notificationModalOk');
            const notificationModal = document.getElementById('notificationModal');

            if (notificationModalClose) {
                notificationModalClose.addEventListener('click', function() {
                    if (notificationModal) {
                        notificationModal.style.display = 'none';
                    }
                });
            }

            if (notificationModalOk) {
                notificationModalOk.addEventListener('click', function() {
                    if (notificationModal) {
                        notificationModal.style.display = 'none';
                    }
                });
            }

            // Close notification modal when clicking outside
            if (notificationModal) {
                window.addEventListener('click', function(e) {
                    if (e.target === notificationModal) {
                        notificationModal.style.display = 'none';
                    }
                });
            }

        // Profile functionality
        let isEditMode = false;
        let currentTab = 'basic';

        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                switchTab(tabId);
            });
        });

        function switchTab(tabId) {
            // Update tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`${tabId}-tab`).classList.add('active');

            currentTab = tabId;
        }

        // Edit mode toggle
        function toggleEditMode() {
            isEditMode = !isEditMode;
            const editBtn = document.querySelector('.btn-edit-profile');
            
            if (isEditMode) {
                editBtn.innerHTML = '<i class="fas fa-eye"></i> View Mode';
                editBtn.classList.add('active');
                enableEditMode();
            } else {
                editBtn.innerHTML = '<i class="fas fa-edit"></i> Edit Profile';
                editBtn.classList.remove('active');
                disableEditMode();
            }
        }

        function enableEditMode() {
            // Enable form fields based on current tab
            const currentTabContent = document.getElementById(`${currentTab}-tab`);
            const inputs = currentTabContent.querySelectorAll('input:not([readonly]), select, textarea');
            const readonlyInputs = currentTabContent.querySelectorAll('input[readonly]:not(#email)');
            const selects = currentTabContent.querySelectorAll('select[disabled]');
            const formActions = currentTabContent.querySelector('.form-actions');

            // Enable editable fields
            readonlyInputs.forEach(input => {
                if (input.id !== 'email') { // Keep email readonly
                    input.removeAttribute('readonly');
                }
            });

            selects.forEach(select => {
                select.removeAttribute('disabled');
            });

            // Show form actions
            if (formActions) {
                formActions.style.display = 'flex';
            }
        }

        function disableEditMode() {
            // Disable all form fields
            document.querySelectorAll('.tab-content').forEach(tab => {
                const inputs = tab.querySelectorAll('input:not(#email), textarea');
                const selects = tab.querySelectorAll('select');
                const formActions = tab.querySelector('.form-actions');

                inputs.forEach(input => {
                    if (input.type !== 'password') {
                        input.setAttribute('readonly', 'readonly');
                    }
                });

                selects.forEach(select => {
                    select.setAttribute('disabled', 'disabled');
                });

                // Hide form actions
                if (formActions) {
                    formActions.style.display = 'none';
                }
            });
        }

        function cancelEdit(tabType) {
            // Reload the page to reset form values
            location.reload();
        }

        // Profile Picture Modal
        function openProfilePictureModal() {
            document.getElementById('profilePictureModal').style.display = 'block';
        }

        function closeProfilePictureModal() {
            document.getElementById('profilePictureModal').style.display = 'none';
            clearPreview();
        }

        function clearPreview() {
            document.getElementById('profilePictureInput').value = '';
            document.querySelector('.upload-area').style.display = 'block';
            document.querySelector('.upload-preview').style.display = 'none';
        }

        // Profile picture preview
        const profilePictureInput = document.getElementById('profilePictureInput');
        if (profilePictureInput) {
            profilePictureInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewImage = document.getElementById('previewImage');
                        if (previewImage) {
                            previewImage.src = e.target.result;
                        }
                        const uploadArea = document.querySelector('.upload-area');
                        const uploadPreview = document.querySelector('.upload-preview');
                        if (uploadArea) uploadArea.style.display = 'none';
                        if (uploadPreview) uploadPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Form submissions
        const basicInfoForm = document.getElementById('basicInfoForm');
        if (basicInfoForm) {
            basicInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitProfileForm('update_basic_info', this);
            });
        }

        const contactInfoForm = document.getElementById('contactInfoForm');
        if (contactInfoForm) {
            contactInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitProfileForm('update_contact_info', this);
            });
        }

        const academicInfoForm = document.getElementById('academicInfoForm');
        if (academicInfoForm) {
            academicInfoForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitProfileForm('update_basic_info', this);
            });
        }

        // Live validation to prevent using the same password again
        const currentPasswordInput = document.getElementById('current_password');
        const newPasswordInput = document.getElementById('new_password');
        const passwordSameWarning = document.getElementById('password_same_warning');

        function validatePasswordDifference() {
            if (!currentPasswordInput || !newPasswordInput || !passwordSameWarning) {
                return true;
            }

            const currentVal = currentPasswordInput.value;
            const newVal = newPasswordInput.value;
            const isSame = currentVal.length > 0 && newVal.length > 0 && currentVal === newVal;

            if (isSame) {
                passwordSameWarning.style.display = 'block';
                newPasswordInput.classList.add('input-error');
            } else {
                passwordSameWarning.style.display = 'none';
                newPasswordInput.classList.remove('input-error');
            }

            return !isSame;
        }

        if (currentPasswordInput && newPasswordInput) {
            currentPasswordInput.addEventListener('input', validatePasswordDifference);
            newPasswordInput.addEventListener('input', validatePasswordDifference);
        }

        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                if (!validatePasswordDifference()) {
                    showNotificationToast('New password must be different from your current password', 'error');
                    return;
                }
                submitProfileForm('change_password', this);
            });
        }

        const profilePictureForm = document.getElementById('profilePictureForm');
        if (profilePictureForm) {
            profilePictureForm.addEventListener('submit', function(e) {
                e.preventDefault();
                submitProfilePictureForm(this);
            });
        }

        function submitProfileForm(action, form) {
            const formData = new FormData(form);
            formData.append('action', action);

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            submitBtn.disabled = true;

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotificationToast(data.message, 'success');
                    if (action === 'change_password') {
                        form.reset();
                    } else {
                        // Refresh page to show updated data
                        setTimeout(() => location.reload(), 1500);
                    }
                } else {
                    showNotificationToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationToast('An error occurred while updating your profile', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        function submitProfilePictureForm(form) {
            const formData = new FormData(form);
            formData.append('action', 'upload_profile_picture');

            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            submitBtn.disabled = true;

            fetch('update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotificationToast(data.message, 'success');
                    closeProfilePictureModal();
                    // Update profile picture in the UI
                    if (data.file_path) {
                        const profileImg = document.getElementById('profilePictureImg');
                        const placeholder = document.querySelector('.profile-avatar-placeholder');
                        
                        if (profileImg) {
                            profileImg.src = data.file_path + '?v=' + Date.now();
                        } else if (placeholder) {
                            placeholder.parentElement.innerHTML = `<img src="${data.file_path}?v=${Date.now()}" alt="Profile Picture" id="profilePictureImg">`;
                        }
                    }
                } else {
                    showNotificationToast(data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotificationToast('An error occurred while uploading your profile picture', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Close profile picture modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('profilePictureModal');
            if (e.target === modal) {
                closeProfilePictureModal();
            }
        });

        // Drag and drop functionality for profile picture
        const uploadArea = document.querySelector('.upload-area');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.classList.add('drag-over');
        }

        function unhighlight(e) {
            uploadArea.classList.remove('drag-over');
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                const profilePictureInput = document.getElementById('profilePictureInput');
                if (profilePictureInput) {
                    profilePictureInput.files = files;
                    const event = new Event('change', { bubbles: true });
                    profilePictureInput.dispatchEvent(event);
                }
            }
        }

        } // End of initializeAllModals function

        // ============================================
        // CALL INITIALIZATION WHEN DOM IS READY
        // ============================================
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeAllModals);
        } else {
            initializeAllModals();
        }

    </script>

    <script src="assets/js/ftue-onboarding.js?v=<?php echo time(); ?>"></script>
    
    <script>
        // ============================================
        // LOGOUT MODAL - Same as Admin Dashboard
        // ============================================
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
                if (!this.modal || !this.cancelBtn || !this.confirmBtn) {
                    console.error('LogoutModal: Missing elements', {
                        modal: !!this.modal,
                        cancelBtn: !!this.cancelBtn,
                        confirmBtn: !!this.confirmBtn
                    });
                    return;
                }
                console.log('LogoutModal: Binding events to buttons');
                this.cancelBtn.addEventListener('click', () => {
                    console.log('Cancel button clicked');
                    this.hide();
                });
                this.modal.addEventListener('click', (e) => {
                    if (e.target === this.modal) {
                        console.log('Overlay clicked');
                        this.hide();
                    }
                });
                this.confirmBtn.addEventListener('click', () => {
                    console.log('Confirm button clicked - logging out');
                    window.location.href = 'logout.php';
                });
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && this.modal.classList.contains('active')) {
                        console.log('Escape key pressed');
                        this.hide();
                    }
                });
            }
            
            show() {
                if (!this.modal) {
                    console.error('LogoutModal: Modal element not found in show()');
                    return;
                }
                console.log('LogoutModal: show() called');
                console.log('Modal element:', this.modal);
                console.log('Modal display:', window.getComputedStyle(this.modal).display);
                console.log('Modal visibility:', window.getComputedStyle(this.modal).visibility);
                this.modal.classList.add('active');
                // Force inline styles with !important to ensure visibility
                this.modal.setAttribute('style', 'opacity: 1 !important; visibility: visible !important;');
                console.log('Active class added');
                console.log('Modal after active:', window.getComputedStyle(this.modal).visibility);
                document.body.style.overflow = 'hidden';
            }
            
            hide() {
                if (!this.modal) return;
                this.modal.classList.remove('active');
                // Clear inline styles
                this.modal.setAttribute('style', 'opacity: 0 !important; visibility: hidden !important;');
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
            console.log('confirmLogout() called');
            console.log('window.logoutModal exists:', !!window.logoutModal);
            if (!window.logoutModal) {
                console.log('Initializing logout modal...');
                initLogoutModal();
            }
            console.log('window.logoutModal after init:', !!window.logoutModal);
            if (window.logoutModal) {
                console.log('Calling show() on modal');
                window.logoutModal.show();
            } else {
                console.error('LogoutModal still not initialized');
            }
        };
    </script>
</body>
</html>
