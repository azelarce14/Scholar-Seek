<?php
/**
 * ScholarSeek Database Setup Script
 * Run this once on InfinityFree to create all necessary tables
 */

// Temporarily use InfinityFree credentials
$host = "sql100.infinityfree.com";
$user = "if0_40468565";
$pass = "mFSh9ALReEiE";
$db = "if0_40468565_scholarseek_db";
$port = 3306;

// Create connection
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// SQL queries to create tables
$sql_queries = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type ENUM('admin', 'staff', 'student') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // Students table
    "CREATE TABLE IF NOT EXISTS students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        student_number VARCHAR(50) UNIQUE NOT NULL,
        fullname VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        date_of_birth DATE,
        emergency_contact_name VARCHAR(255),
        emergency_contact_phone VARCHAR(20),
        department VARCHAR(255),
        program VARCHAR(255),
        year_level VARCHAR(50),
        gwa DECIMAL(3, 2),
        profile_picture VARCHAR(255),
        registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Staff table
    "CREATE TABLE IF NOT EXISTS staff (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        fullname VARCHAR(255) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(20),
        position VARCHAR(255),
        department VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    // Scholarships table
    "CREATE TABLE IF NOT EXISTS scholarships (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        amount DECIMAL(10, 2),
        deadline DATE,
        eligibility_criteria TEXT,
        requirements TEXT,
        status ENUM('active', 'inactive', 'closed') DEFAULT 'active',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES staff(id)
    )",

    // Applications table
    "CREATE TABLE IF NOT EXISTS applications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        scholarship_id INT NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        application_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        reviewed_by INT,
        reviewed_date DATETIME,
        rejection_reason TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (scholarship_id) REFERENCES scholarships(id) ON DELETE CASCADE,
        FOREIGN KEY (reviewed_by) REFERENCES staff(id)
    )",

    // System settings table
    "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(255) UNIQUE NOT NULL,
        setting_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    // Email logs table
    "CREATE TABLE IF NOT EXISTS email_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        recipient_email VARCHAR(255) NOT NULL,
        subject VARCHAR(255),
        sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('sent', 'failed') DEFAULT 'sent'
    )",

    // Activity logs table
    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(255),
        details TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )"
];

// Execute all queries
$success = true;
foreach ($sql_queries as $query) {
    if (!mysqli_query($conn, $query)) {
        echo "Error creating table: " . mysqli_error($conn) . "<br>";
        $success = false;
    }
}

if ($success) {
    // Insert default admin account
    $admin_email = "admin@biliran.edu.ph";
    $admin_password = password_hash("123", PASSWORD_BCRYPT);
    
    $check_admin = "SELECT id FROM users WHERE email = ?";
    $stmt = mysqli_prepare($conn, $check_admin);
    mysqli_stmt_bind_param($stmt, "s", $admin_email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 0) {
        $insert_admin = "INSERT INTO users (email, password, user_type) VALUES (?, ?, 'admin')";
        $stmt = mysqli_prepare($conn, $insert_admin);
        mysqli_stmt_bind_param($stmt, "ss", $admin_email, $admin_password);
        mysqli_stmt_execute($stmt);
        echo "✅ Admin account created: admin@biliran.edu.ph / 123<br>";
    }
    
    echo "✅ All tables created successfully!<br>";
    echo "✅ Database setup complete!<br>";
    echo "<br><a href='index.html'>Go to Homepage</a>";
} else {
    echo "❌ Setup failed. Please check the errors above.";
}

mysqli_close($conn);
?>
