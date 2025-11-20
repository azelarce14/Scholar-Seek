<?php
/**
 * Secure Database Connection for ScholarSeek
 */

// Database configuration
$host = "127.0.0.1";     
$user = "root";          
$pass = "";              
$db   = "scholarseek";   
$port = 3306;

// Create connection with error handling
$conn = mysqli_connect($host, $user, $pass, $db, $port);

if (!$conn) {
    // Log connection error
    error_log("Database connection failed: " . mysqli_connect_error());
    
    // Don't expose database details to users
    http_response_code(503);
    die("Service temporarily unavailable. Please try again later.");
}

// Set charset to prevent character set confusion attacks
mysqli_set_charset($conn, "utf8mb4");

// Set SQL mode for stricter data validation
mysqli_query($conn, "SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");

// Enable autocommit by default (transactions will be explicit where needed)
mysqli_autocommit($conn, TRUE);

/**
 * Secure database query function with prepared statements
 */
function secure_query($conn, $query, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Query preparation failed: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            // Auto-detect types
            $types = str_repeat('s', count($params));
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        error_log("Query execution failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    
    return $result;
}

/**
 * Secure insert function that returns the inserted ID
 */
function secure_insert($conn, $query, $params = [], $types = '') {
    $stmt = mysqli_prepare($conn, $query);
    
    if (!$stmt) {
        error_log("Insert preparation failed: " . mysqli_error($conn));
        return false;
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    
    $result = mysqli_stmt_execute($stmt);
    
    if (!$result) {
        error_log("Insert execution failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    $insert_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    return $insert_id;
}

/**
 * Begin transaction
 */
function begin_transaction($conn) {
    return mysqli_begin_transaction($conn);
}

/**
 * Commit transaction
 */
function commit_transaction($conn) {
    return mysqli_commit($conn);
}

/**
 * Rollback transaction
 */
function rollback_transaction($conn) {
    return mysqli_rollback($conn);
}

// Email System Class - Consolidated from email_system.php
class EmailSystem {
    private $conn;
    private $settings = [];
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'email_%' OR setting_key LIKE 'smtp_%' OR setting_key LIKE 'from_%' OR setting_key = 'site_url'";
        $result = mysqli_query($this->conn, $query);
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    
    public function sendApplicationStatusEmail($student_email, $student_name, $scholarship_title, $status, $application_id) {
        if (!$this->isEmailEnabled()) {
            return false;
        }
        
        $subject = "Scholarship Application Update - " . ucfirst($status);
        
        $status_messages = [
            'approved' => [
                'title' => 'Congratulations! Your Application Has Been Approved',
                'message' => "We are pleased to inform you that your application for the {$scholarship_title} scholarship has been approved. You will be contacted soon with further details about the next steps.",
                'color' => '#10b981'
            ],
            'rejected' => [
                'title' => 'Application Status Update',
                'message' => "Thank you for your interest in the {$scholarship_title} scholarship. Unfortunately, your application was not selected at this time. We encourage you to apply for other available scholarships.",
                'color' => '#ef4444'
            ],
            'pending' => [
                'title' => 'Application Under Review',
                'message' => "Your application for the {$scholarship_title} scholarship is currently under review. We will notify you once a decision has been made.",
                'color' => '#f59e0b'
            ]
        ];
        
        $status_info = $status_messages[$status] ?? $status_messages['pending'];
        
        $message = $this->buildEmailTemplate([
            'title' => $status_info['title'],
            'greeting' => "Dear {$student_name},",
            'content' => $status_info['message'],
            'action_text' => 'View Application',
            'action_url' => ($this->settings['site_url'] ?? 'http://localhost/scholarseek') . '/student_dashboard.php#applications',
            'footer' => 'If you have any questions, please contact our scholarship office.',
            'color' => $status_info['color']
        ]);
        
        return $this->sendEmail($student_email, $student_name, $subject, $message, 'application_status');
    }
    
    public function sendWelcomeEmail($student_email, $student_name) {
        if (!$this->isEmailEnabled()) {
            return false;
        }
        
        $subject = "Welcome to ScholarSeek - Your Account is Ready!";
        
        $message = $this->buildEmailTemplate([
            'title' => 'Welcome to ScholarSeek!',
            'greeting' => "Dear {$student_name},",
            'content' => "Your ScholarSeek account has been successfully created. You can now browse and apply for scholarships that match your profile. Start exploring opportunities today!",
            'action_text' => 'Access Dashboard',
            'action_url' => ($this->settings['site_url'] ?? 'http://localhost/scholarseek') . '/student_dashboard.php',
            'footer' => 'Need help getting started? Contact our support team.',
            'color' => '#667eea'
        ]);
        
        return $this->sendEmail($student_email, $student_name, $subject, $message, 'welcome');
    }
    
    public function sendDeadlineReminder($student_email, $student_name, $scholarship_title, $deadline, $scholarship_id) {
        if (!$this->isEmailEnabled()) {
            return false;
        }
        
        $subject = "Scholarship Deadline Reminder - {$scholarship_title}";
        $deadline_formatted = date('F j, Y', strtotime($deadline));
        
        $message = $this->buildEmailTemplate([
            'title' => 'Scholarship Deadline Approaching',
            'greeting' => "Dear {$student_name},",
            'content' => "This is a reminder that the application deadline for the {$scholarship_title} scholarship is approaching. The deadline is {$deadline_formatted}. Don't miss this opportunity!",
            'action_text' => 'Apply Now',
            'action_url' => ($this->settings['site_url'] ?? 'http://localhost/scholarseek') . '/apply_scholarship.php?id=' . $scholarship_id,
            'footer' => 'Apply early to ensure your application is processed on time.',
            'color' => '#f59e0b'
        ]);
        
        return $this->sendEmail($student_email, $student_name, $subject, $message, 'deadline_reminder');
    }
    
    private function buildEmailTemplate($data) {
        $site_url = $this->settings['site_url'] ?? 'http://localhost/scholarseek';
        $color = $data['color'] ?? '#667eea';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$data['title']}</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, {$color} 0%, " . $this->adjustBrightness($color, -20) . " 100%); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
                .content { padding: 30px; }
                .greeting { font-size: 18px; font-weight: 500; margin-bottom: 20px; color: #2d3748; }
                .message { font-size: 16px; line-height: 1.8; margin-bottom: 30px; color: #4a5568; }
                .action-button { display: inline-block; background: {$color}; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 500; margin: 20px 0; }
                .footer { background: #f7fafc; padding: 20px; text-align: center; font-size: 14px; color: #718096; border-top: 1px solid #e2e8f0; }
                .logo { font-size: 20px; font-weight: bold; margin-bottom: 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>🎓 ScholarSeek</div>
                    <h1>{$data['title']}</h1>
                </div>
                <div class='content'>
                    <div class='greeting'>{$data['greeting']}</div>
                    <div class='message'>{$data['content']}</div>
                    " . (isset($data['action_url']) ? "<a href='{$data['action_url']}' class='action-button'>{$data['action_text']}</a>" : "") . "
                </div>
                <div class='footer'>
                    <p>{$data['footer']}</p>
                    <p><strong>ScholarSeek</strong> - Biliran Province State University<br>
                    <a href='{$site_url}' style='color: {$color};'>Visit ScholarSeek</a></p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function sendEmail($to_email, $to_name, $subject, $message, $type) {
        $from_email = $this->settings['from_email'] ?? 'scholarseek@biliran.edu.ph';
        $from_name = $this->settings['from_name'] ?? 'ScholarSeek System';
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        $success = mail($to_email, $subject, $message, implode("\r\n", $headers));
        
        // Log email attempt
        $this->logEmail($to_email, $to_name, $subject, $message, $type, $success ? 'sent' : 'failed');
        
        return $success;
    }
    
    private function logEmail($recipient_email, $recipient_name, $subject, $message, $type, $status) {
        $query = "INSERT INTO email_logs (recipient_email, recipient_name, subject, message, type, status, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $query);
        if ($stmt) {
            $sent_at = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
            mysqli_stmt_bind_param($stmt, "sssssss", $recipient_email, $recipient_name, $subject, $message, $type, $status, $sent_at);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
    
    private function isEmailEnabled() {
        return ($this->settings['email_enabled'] ?? '0') === '1';
    }
    
    private function adjustBrightness($hex, $percent) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}

// Global function to send emails easily
function sendNotificationEmail($type, $data) {
    global $conn;
    $emailSystem = new EmailSystem($conn);
    
    switch ($type) {
        case 'application_status':
            return $emailSystem->sendApplicationStatusEmail(
                $data['email'], 
                $data['name'], 
                $data['scholarship_title'], 
                $data['status'], 
                $data['application_id']
            );
        case 'welcome':
            return $emailSystem->sendWelcomeEmail($data['email'], $data['name']);
        case 'deadline_reminder':
            return $emailSystem->sendDeadlineReminder(
                $data['email'], 
                $data['name'], 
                $data['scholarship_title'], 
                $data['deadline'], 
                $data['scholarship_id']
            );
        default:
            return false;
    }
}

// Note: Connection will be closed automatically at the end of script execution
// Don't close connection here as it's needed by including files
?>
