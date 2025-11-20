<?php
/**
 * Notification System for ScholarSeek
 * Handles in-app notifications for users
 */

class NotificationSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($user_id, $user_type, $type, $title, $message, $related_id = null, $related_type = null) {
        $query = "INSERT INTO notifications (user_id, user_type, type, title, message, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "issssss", $user_id, $user_type, $type, $title, $message, $related_id, $related_type);
        
        $result = mysqli_stmt_execute($stmt);
        $notification_id = mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        
        return $result ? $notification_id : false;
    }
    
    /**
     * Get notifications for a user
     */
    public function getUserNotifications($user_id, $user_type, $limit = 50, $unread_only = false) {
        $where_clause = "WHERE user_id = ? AND user_type = ?";
        $params = [$user_id, $user_type];
        $types = "is";
        
        if ($unread_only) {
            $where_clause .= " AND is_read = FALSE";
        }
        
        $query = "SELECT * FROM notifications {$where_clause} ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notifications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = $row;
        }
        
        mysqli_stmt_close($stmt);
        return $notifications;
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($user_id, $user_type) {
        $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = ? AND is_read = FALSE";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $user_type);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $row['count'] ?? 0;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notification_id, $user_id) {
        $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "ii", $notification_id, $user_id);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($user_id, $user_type) {
        $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND user_type = ? AND is_read = FALSE";
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $user_type);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        return $result;
    }
    
    /**
     * Delete old notifications (older than 30 days)
     */
    public function cleanupOldNotifications() {
        $query = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        return mysqli_query($this->conn, $query);
    }
    
    /**
     * Create application status notification
     */
    public function notifyApplicationStatus($student_id, $application_id, $scholarship_title, $status) {
        $status_messages = [
            'approved' => [
                'title' => 'Application Approved! 🎉',
                'message' => "Congratulations! Your application for '{$scholarship_title}' has been approved."
            ],
            'rejected' => [
                'title' => 'Application Update',
                'message' => "Your application for '{$scholarship_title}' was not selected this time. Keep applying for other opportunities!"
            ],
            'pending' => [
                'title' => 'Application Under Review',
                'message' => "Your application for '{$scholarship_title}' is currently being reviewed."
            ]
        ];
        
        $message_data = $status_messages[$status] ?? $status_messages['pending'];
        
        return $this->createNotification(
            $student_id,
            'student',
            'application_' . $status,
            $message_data['title'],
            $message_data['message'],
            $application_id,
            'application'
        );
    }
    
    /**
     * Create application rejection notification with reason
     */
    public function notifyApplicationRejection($student_id, $application_id, $scholarship_title, $rejection_reason) {
        $title = 'Application Update - Action Required';
        $message = "Your application for '{$scholarship_title}' was not approved.\n\n" .
                  "Reason: {$rejection_reason}\n\n" .
                  "Don't be discouraged! Review the feedback and consider applying for other scholarships that match your profile.";
        
        return $this->createNotification(
            $student_id,
            'student',
            'application_rejected_with_reason',
            $title,
            $message,
            $application_id,
            'application'
        );
    }
    
    /**
     * Create scholarship deadline notification
     */
    public function notifyScholarshipDeadline($student_id, $scholarship_id, $scholarship_title, $deadline) {
        $deadline_formatted = date('F j, Y', strtotime($deadline));
        
        return $this->createNotification(
            $student_id,
            'student',
            'scholarship_deadline',
            'Scholarship Deadline Approaching ⏰',
            "The application deadline for '{$scholarship_title}' is {$deadline_formatted}. Don't miss out!",
            $scholarship_id,
            'scholarship'
        );
    }
    
    /**
     * Create welcome notification for new students
     */
    public function notifyWelcome($student_id, $student_name) {
        return $this->createNotification(
            $student_id,
            'student',
            'general',
            'Welcome to ScholarSeek! 🎓',
            'Your account has been created successfully. Start exploring scholarship opportunities today.'
        );
    }
    
    /**
     * Notify all eligible students about new scholarship
     */
    public function notifyNewScholarship($scholarship_id, $scholarship_title, $deadline = null) {
        // Get all active students
        $student_query = "SELECT id, fullname FROM students WHERE id IS NOT NULL";
        $student_result = mysqli_query($this->conn, $student_query);
        
        $deadline_text = $deadline ? " Application deadline: " . date('F j, Y', strtotime($deadline)) : "";
        
        $notifications_created = 0;
        while ($student = mysqli_fetch_assoc($student_result)) {
            $success = $this->createNotification(
                $student['id'],
                'student',
                'general',
                'New Scholarship Available! 🆕',
                "A new scholarship '{$scholarship_title}' is now available for applications.{$deadline_text}",
                $scholarship_id,
                'scholarship'
            );
            
            if ($success) {
                $notifications_created++;
            }
        }
        
        return $notifications_created;
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($user_id, $user_type) {
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN is_read = FALSE THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN type = 'application_approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN type = 'application_rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN type = 'application_pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN type = 'scholarship_deadline' THEN 1 ELSE 0 END) as deadlines,
                SUM(CASE WHEN type = 'general' THEN 1 ELSE 0 END) as general
            FROM notifications 
            WHERE user_id = ? AND user_type = ?
        ";
        
        $stmt = mysqli_prepare($this->conn, $query);
        mysqli_stmt_bind_param($stmt, "is", $user_id, $user_type);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $stats = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        
        return $stats;
    }
}

// Global notification functions
function createNotification($user_id, $user_type, $type, $title, $message, $related_id = null, $related_type = null) {
    global $conn;
    $notificationSystem = new NotificationSystem($conn);
    return $notificationSystem->createNotification($user_id, $user_type, $type, $title, $message, $related_id, $related_type);
}

function getUserNotifications($user_id, $user_type, $limit = 50) {
    global $conn;
    $notificationSystem = new NotificationSystem($conn);
    return $notificationSystem->getUserNotifications($user_id, $user_type, $limit);
}

function getUnreadNotificationCount($user_id, $user_type) {
    global $conn;
    $notificationSystem = new NotificationSystem($conn);
    return $notificationSystem->getUnreadCount($user_id, $user_type);
}

function markNotificationAsRead($notification_id, $user_id) {
    global $conn;
    $notificationSystem = new NotificationSystem($conn);
    return $notificationSystem->markAsRead($notification_id, $user_id);
}

function markAllNotificationsAsRead($user_id, $user_type) {
    global $conn;
    $notificationSystem = new NotificationSystem($conn);
    return $notificationSystem->markAllAsRead($user_id, $user_type);
}
?>
