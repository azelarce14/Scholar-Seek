<?php
// Start secure session
session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

require_once 'db_connect.php';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic rate limiting - max 5 attempts per 15 minutes
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes
    $current_time = time();
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    // Clean old attempts
    $_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($timestamp) use ($current_time, $lockout_time) {
        return ($current_time - $timestamp) < $lockout_time;
    });
    
    // Check if user is locked out
    if (count($_SESSION['login_attempts']) >= $max_attempts) {
        $_SESSION['login_error'] = "Too many failed login attempts. Please try again in 15 minutes.";
        header("Location: login.php");
        exit();
    }
    
    // Check if database connection exists
    if (!$conn) {
        $_SESSION['login_error'] = "Database connection failed. Please check if XAMPP MySQL is running.";
        header("Location: login.php");
        exit();
    }

    // Get and sanitize form data
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate input
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = "Please enter both email and password.";
        header("Location: login.php");
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['login_error'] = "Please enter a valid email address (e.g., user@gmail.com).";
        header("Location: login.php");
        exit();
    }

    // Built-in accounts
    if ($email === 'admin@biliran.edu.ph' && $password === '123') {
        // Clear failed attempts on successful login
        unset($_SESSION['login_attempts']);
        $_SESSION['user_type'] = 'admin';
        $_SESSION['user_id'] = 1;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = 'Administrator';
        $_SESSION['last_activity'] = time();
        
        header("Location: admin_dashboard.php");
        exit();
    } elseif ($email === 'staff@biliran.edu.ph' && $password === '123') {
        // Clear failed attempts on successful login
        unset($_SESSION['login_attempts']);
        $_SESSION['user_type'] = 'staff';
        $_SESSION['user_id'] = 2;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_name'] = 'Staff Member';
        $_SESSION['last_activity'] = time();
        
        header("Location: staff_dashboard.php");
        exit();
    }

    // --- 1. Admin and Staff login from users table ---
    $query = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check hashed password
        if (password_verify($password, $row['password'])) {
            // Clear failed attempts on successful login
            unset($_SESSION['login_attempts']);
            $_SESSION['user_type'] = $row['role'] ?? 'staff';
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_name'] = $row['fullname'] ?? $row['email'];
            $_SESSION['last_activity'] = time();
            
            $redirect = ($row['role'] === 'admin') ? 'admin_dashboard.php' : 'staff_dashboard.php';
            header("Location: $redirect");
            exit();
        }
    }

    // --- 2. Staff login from staff table ---
    $query = "SELECT * FROM staff WHERE email = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Clear failed attempts on successful login
            unset($_SESSION['login_attempts']);
            $_SESSION['user_type'] = 'staff';
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_name'] = $row['fullname'];
            $_SESSION['last_activity'] = time();
            
            header("Location: staff_dashboard.php");
            exit();
        }
    }

    // --- 3. Student login from students table ---
    $query = "SELECT * FROM students WHERE email = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) {
        $_SESSION['login_error'] = "Database error occurred. Please try again.";
        header("Location: login.php");
        exit();
    }
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Clear failed attempts on successful login
            unset($_SESSION['login_attempts']);
            $_SESSION['user_type'] = 'student';
            $_SESSION['student_id'] = $row['id'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_name'] = $row['fullname'] ?? $row['email'];
            $_SESSION['last_activity'] = time();
            
            header("Location: student_dashboard.php");
            exit();
        }
    }

    // --- 4. Invalid login ---
    // Record failed attempt
    $_SESSION['login_attempts'][] = time();
    $_SESSION['login_error'] = "Invalid email or password.";
    header("Location: login.php");
    exit();
}

// Redirect if already authenticated
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    switch ($_SESSION['user_type']) {
        case 'admin':
            header('Location: admin_dashboard.php');
            break;
        case 'staff':
            header('Location: staff_dashboard.php');
            break;
        case 'student':
            header('Location: student_dashboard.php');
            break;
        default:
            header('Location: index.html');
    }
    exit();
}

$error = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
$timeout = isset($_GET['timeout']) ? true : false;

if (isset($_SESSION['login_error'])) {
    unset($_SESSION['login_error']);
}

if ($timeout) {
    $error = 'Your session has expired. Please login again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSeek - Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Logo -->
            <div class="logo-section">
                <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo-img">
            </div>
            
            <!-- Info Text -->
            <div class="info-section">
                <p class="info-text">Access your scholarship applications and manage your academic journey with ease.</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form action="login.php" method="POST" class="login-form">
                <!-- Email Input -->
                <div class="input-group">
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input"
                            placeholder="Email address" 
                            required
                        >
                    </div>
                </div>

                <!-- Password Input -->
                <div class="input-group">
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input"
                            placeholder="Password" 
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword()" title="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Terms Text -->
                <div class="terms-section">
                    <p class="terms-text">
                        By signing up or logging in, you consent to ScholarSeek's 
                        <a href="legal.php?section=terms" class="terms-link">Terms of Use</a> and 
                        <a href="legal.php?section=privacy" class="terms-link">Privacy Policy</a>.
                    </p>
                </div>

                <!-- Login Button -->
                <button type="submit" class="login-btn">
                    <span>Log in</span>
                </button>

                <!-- Footer Links -->
                <div class="footer-links">
                    <p class="signup-prompt">Don't have an account? <a href="register.php" class="footer-link sign-up">Sign up</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        }

        // Auto-hide password when clicking outside
        document.addEventListener('click', function(event) {
            const passwordWrapper = document.querySelector('.password-wrapper');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.querySelector('.password-toggle i');
            
            if (!passwordWrapper.contains(event.target) && passwordInput.type === 'text') {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
            }
        });

        // Add focus effects
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>