<?php
/**
 * Student Registration Process (Simplified)
 * 
 * REQUIRED FIELDS:
 * ================
 * - Email (serves as login credential)
 * - Password
 * - Full Name
 * - Student Number
 * - Program
 * - Department
 * - Year Level
 * 
 * SECURITY MEASURES IMPLEMENTED:
 * ================================
 * 1. Duplicate Email Prevention - Checks students, staff, and admin tables
 * 2. Duplicate Full Name Prevention - Prevents multiple accounts with same name
 * 3. Email-based Authentication - Email serves as unique login identifier
 * 4. Duplicate Student Number Prevention - Ensures unique student numbers
 * 5. Academic Structure Validation - Validates department-program combinations
 * 5. Email Format Validation - Ensures valid email addresses
 * 6. Password Strength Validation - Minimum 6 characters required
 * 7. SQL Injection Protection - Uses prepared statements for all queries
 * 8. Session-based Error Handling - User-friendly error messages
 * 9. Password Hashing - Uses PHP password_hash() for security
 * 10. Input Sanitization - Trims and validates all user inputs
 * 11. Cross-table validation - Prevents conflicts across all user types
 * 12. Required Field Validation - Ensures all fields are completed
 * 
 * @author ScholarSeek Team
 * @version 2.1 (Simplified)
 */

session_start();
require_once 'db_connect.php';
require_once 'notification_system.php';
require_once 'config/academic_structure.php';
// email_system.php functionality is now integrated into db_connect.php

// DEBUG: Log all registration attempts
$debug_log = date('Y-m-d H:i:s') . " - Registration attempt started for email: " . ($_POST['email'] ?? 'none') . "\n";
$logs_dir = __DIR__ . '/logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}
file_put_contents($logs_dir . '/debug.log', $debug_log, FILE_APPEND | LOCK_EX);

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $password = $_POST['password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $student_number = trim($_POST['student_number'] ?? '');
    $program = trim($_POST['program'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $year_level = trim($_POST['year_level'] ?? '');

    // Validate required fields
    if (empty($password) || empty($email) || empty($fullname) || empty($student_number) || empty($program) || empty($department) || empty($year_level)) {
        $_SESSION['error_message'] = "All fields are required. Please fill in all information.";
        header("Location: register.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = "Invalid email format. Please enter a valid email address.";
        header("Location: register.php");
        exit();
    }

    // Validate password strength (minimum 6 characters)
    if (strlen($password) < 6) {
        $_SESSION['error_message'] = "Password must be at least 6 characters long.";
        header("Location: register.php");
        exit();
    }

    // Validate academic structure
    if (!isValidDepartment($department)) {
        $_SESSION['error_message'] = "Invalid department selected. Please choose a valid school.";
        header("Location: register.php");
        exit();
    }

    if (!isValidProgram($program)) {
        $_SESSION['error_message'] = "Invalid program selected. Please choose a valid program.";
        header("Location: register.php");
        exit();
    }

    if (!isValidDepartmentProgramCombination($department, $program)) {
        $_SESSION['error_message'] = "The selected program is not available in the chosen school. Please select a valid combination.";
        header("Location: register.php");
        exit();
    }

    // Validate year level
    if (!in_array($year_level, $year_levels)) {
        $_SESSION['error_message'] = "Invalid year level selected. Please choose a valid year level.";
        header("Location: register.php");
        exit();
    }

    // Start transaction for atomic registration process
    mysqli_begin_transaction($conn);

    // ===== ENHANCED SECURITY: Check for duplicates across ALL tables =====

    // 1. STRICT SECURITY: Check for duplicate EMAIL (case-insensitive)
    $check_email = "SELECT id, email FROM students WHERE LOWER(email) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $check_email);

    if (!$stmt) {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        header("Location: register.php");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Rollback transaction
        mysqli_rollback($conn);
        
        // Log duplicate email attempt
        $log_entry = date('Y-m-d H:i:s') . " - DUPLICATE EMAIL ATTEMPT: " . $email . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents(__DIR__ . '/logs/security.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        // DEBUG: Log duplicate detection
        $debug_log = date('Y-m-d H:i:s') . " - DUPLICATE EMAIL DETECTED AND BLOCKED: " . $email . "\n";
        file_put_contents(__DIR__ . '/logs/debug.log', $debug_log, FILE_APPEND | LOCK_EX);
        
        $_SESSION['error_message'] = "This email address is already registered. Please use a different email or <a href='login.php' style='color: #0A06D3; text-decoration: underline;'>login here</a> if you already have an account.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    } else {
        // DEBUG: Log email check passed
        $debug_log = date('Y-m-d H:i:s') . " - Email check PASSED for: " . $email . "\n";
        file_put_contents(__DIR__ . '/logs/debug.log', $debug_log, FILE_APPEND | LOCK_EX);
    }
    mysqli_stmt_close($stmt);

    // 2. STRICT SECURITY: Check for duplicate FULL NAME (case-insensitive)
    $check_name = "SELECT id, fullname FROM students WHERE LOWER(fullname) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $check_name);

    if (!$stmt) {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        header("Location: register.php");
        exit();
    }

    mysqli_stmt_bind_param($stmt, "s", $fullname);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Rollback transaction
        mysqli_rollback($conn);
        
        // Log duplicate name attempt
        $log_entry = date('Y-m-d H:i:s') . " - DUPLICATE NAME ATTEMPT: " . $fullname . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents(__DIR__ . '/logs/security.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        $_SESSION['error_message'] = "A student with this exact name is already registered. If this is you, please <a href='login.php' style='color: #0A06D3; text-decoration: underline;'>login here</a>. Otherwise, contact support if you believe this is an error.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
    mysqli_stmt_close($stmt);


    // 3. Check if email exists in staff table (case-insensitive)
    $check_email_staff = "SELECT id FROM staff WHERE LOWER(email) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $check_email_staff);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Rollback transaction
        mysqli_rollback($conn);
        
        $_SESSION['error_message'] = "This email is already registered in the system. Please use a different email.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
    mysqli_stmt_close($stmt);

    // 4. Check if email exists in users table (admin/staff) - case-insensitive
    $check_email_users = "SELECT id FROM users WHERE LOWER(email) = LOWER(?)";
    $stmt = mysqli_prepare($conn, $check_email_users);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Rollback transaction
        mysqli_rollback($conn);
        
        $_SESSION['error_message'] = "This email is already registered in the system. Please use a different email.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
    mysqli_stmt_close($stmt);

    // 5. Check if student number already exists
    $check_student_number = "SELECT id FROM students WHERE student_number = ?";
    $stmt = mysqli_prepare($conn, $check_student_number);
    mysqli_stmt_bind_param($stmt, "s", $student_number);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        // Rollback transaction
        mysqli_rollback($conn);
        
        $_SESSION['error_message'] = "This student number is already registered. Please check your student number or contact support.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
    mysqli_stmt_close($stmt);

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username column exists and insert accordingly
    $check_columns_query = "SHOW COLUMNS FROM students LIKE 'username'";
    $column_result = mysqli_query($conn, $check_columns_query);
    $has_username_column = mysqli_num_rows($column_result) > 0;

    if ($has_username_column) {
        // Database still has username column - include it in insert
        $insert_query = "
            INSERT INTO students (username, password, email, fullname, student_number, program, department, year_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "ssssssss", $email, $hashed_password, $email, $fullname, $student_number, $program, $department, $year_level);
    } else {
        // Username column removed - insert without it
        $insert_query = "
            INSERT INTO students (password, email, fullname, student_number, program, department, year_level) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        $stmt = mysqli_prepare($conn, $insert_query);
        mysqli_stmt_bind_param($stmt, "sssssss", $hashed_password, $email, $fullname, $student_number, $program, $department, $year_level);
    }

    if (mysqli_stmt_execute($stmt)) {
        // Registration successful - commit transaction
        mysqli_commit($conn);
        
        $student_id = mysqli_insert_id($conn);
        
        // Log successful registration for security monitoring
        $log_entry = date('Y-m-d H:i:s') . " - New student registration: " . $email . " (ID: " . $student_id . ") from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents(__DIR__ . '/logs/registrations.log', $log_entry, FILE_APPEND | LOCK_EX);
        
        // DEBUG: Log successful registration
        $debug_log = date('Y-m-d H:i:s') . " - REGISTRATION SUCCESSFUL: " . $email . " (ID: " . $student_id . ")\n";
        file_put_contents(__DIR__ . '/logs/debug.log', $debug_log, FILE_APPEND | LOCK_EX);
        
        // Create welcome notification
        $notificationSystem = new NotificationSystem($conn);
        $notificationSystem->notifyWelcome($student_id, $fullname);
        
        // Send welcome email
        sendNotificationEmail('welcome', [
            'email' => $email,
            'name' => $fullname
        ]);
        
        $_SESSION['user_type'] = 'student';
        $_SESSION['user_id'] = $student_id;
        $_SESSION['student_id'] = $student_id;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = $fullname;
        $_SESSION['success_message'] = "Welcome to ScholarSeek! Your account has been created successfully.";
        
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        
        // Redirect to student dashboard
        header("Location: student_dashboard.php");
        exit();
    } else {
        // Registration failed - rollback transaction
        mysqli_rollback($conn);
        
        $error = mysqli_error($conn);
        $_SESSION['error_message'] = "Registration failed: " . $error . ". Please try again or contact support.";
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        header("Location: register.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSeek</title>
    <link rel="stylesheet" href="assets/css/register.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <style>
        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <img src="assets/img/logo.png" alt="ScholarSeek Logo">
        </div>

        <form action="register.php" method="POST" class="register-form">
            <h2>Create Your Account</h2>

            <!-- Display Error/Success Messages -->
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Personal Information Section -->
            <div class="form-section">
                <h3 class="section-title">Personal Information</h3>
                <div class="form-grid">
                    <div class="input-group email-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <input type="email" id="email" name="email" placeholder="Enter your email" required>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="input-group password-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <input type="password" id="password" name="password" placeholder="Enter your password (min. 6 characters)" required>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="input-group text-group">
                        <label for="fullname">Full Name</label>
                        <div class="input-wrapper">
                            <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Academic Information Section -->
            <div class="form-section">
                <h3 class="section-title">Academic Information</h3>
                <div class="form-grid">
                    <div class="input-group text-group">
                        <label for="student_number">Student Number</label>
                        <div class="input-wrapper">
                            <input type="text" id="student_number" name="student_number" placeholder="Enter your student number" required>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="year_level">Year Level</label>
                        <div class="input-wrapper">
                            <select id="year_level" name="year_level" required>
                                <option value="1st Year">1st Year</option>
                                <option value="2nd Year">2nd Year</option>
                                <option value="3rd Year">3rd Year</option>
                                <option value="4th Year">4th Year</option>
                            </select>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>

                    <div class="input-group full-width">
                        <label for="department">Department</label>
                        <div class="input-wrapper">
                            <select id="department" name="department" required>
                                <option value="">Select your school</option>
                                <option value="School of Arts and Sciences">School of Arts and Sciences</option>
                                <option value="School of Criminal Justice Education">School of Criminal Justice Education</option>
                                <option value="School of Management and Entrepreneurship">School of Management and Entrepreneurship</option>
                                <option value="School of Nursing and Health Sciences">School of Nursing and Health Sciences</option>
                                <option value="School of Engineering">School of Engineering</option>
                                <option value="School of Technology and Computer Studies">School of Technology and Computer Studies</option>
                                <option value="School of Teacher Education">School of Teacher Education</option>
                            </select>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                            </svg>
                        </div>
                    </div>

                    <div class="input-group full-width">
                        <label for="program">Program</label>
                        <div class="input-wrapper">
                            <select id="program" name="program" required disabled>
                                <option value="">Select department first</option>
                            </select>
                            <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="form-actions">
                <button type="submit" class="submit-btn">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Create Account
                </button>
                <p class="login-link">Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </form>
    </div>

    <script>
        // Department to Program mapping
        const departmentPrograms = {
            'School of Arts and Sciences': [
                'Bachelor of Arts in Communication',
                'Bachelor of Arts in Economics',
                'Bachelor of Science in Business Administration'
            ],
            'School of Criminal Justice Education': [
                'Bachelor of Science in Criminology'
            ],
            'School of Management and Entrepreneurship': [
                'Bachelor of Science in Tourism Management',
                'Bachelor of Science in Hospitality Management'
            ],
            'School of Nursing and Health Sciences': [
                'Bachelor of Science in Nursing'
            ],
            'School of Engineering': [
                'Bachelor of Science in Civil Engineering',
                'Bachelor of Science in Electrical Engineering',
                'Bachelor of Science in Mechanical Engineering',
                'Bachelor of Science in Computer Engineering'
            ],
            'School of Technology and Computer Studies': [
                'Bachelor of Science in Industrial Technology',
                'Bachelor of Science in Computer Science',
                'Bachelor of Science in Information System'
            ],
            'School of Teacher Education': [
                'Bachelor of Elementary Education',
                'Bachelor of Secondary Education',
                'Bachelor of Physical Education',
                'Bachelor of Early Childhood Education',
                'Bachelor of Special Needs Education',
                'Bachelor of Technology and Livelihood Education'
            ]
        };

        // Get dropdown elements
        const departmentSelect = document.getElementById('department');
        const programSelect = document.getElementById('program');

        // Add event listener to department dropdown
        departmentSelect.addEventListener('change', function() {
            const selectedDepartment = this.value;
            
            // Clear program dropdown
            programSelect.innerHTML = '';
            
            if (selectedDepartment === '') {
                // No department selected
                programSelect.disabled = true;
                programSelect.innerHTML = '<option value="">Select department first</option>';
            } else {
                // Department selected, populate programs
                programSelect.disabled = false;
                programSelect.innerHTML = '<option value="">Select your program</option>';
                
                // Add programs for selected department
                const programs = departmentPrograms[selectedDepartment] || [];
                programs.forEach(program => {
                    const option = document.createElement('option');
                    option.value = program;
                    option.textContent = program;
                    programSelect.appendChild(option);
                });
            }
        });
    </script>
</body>
</html>