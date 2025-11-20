<?php
/**
 * Notification Actions API
 * Handles AJAX requests for notification management
 */

session_start();
require_once 'db_connect.php';
require_once 'notification_system.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];

// Check if action is provided
if (!isset($_POST['action'])) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit();
}

$action = $_POST['action'];
$notificationSystem = new NotificationSystem($conn);

switch ($action) {
    case 'mark_read':
        if (!isset($_POST['notification_id'])) {
            echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
            exit();
        }
        
        $notification_id = intval($_POST['notification_id']);
        $result = $notificationSystem->markAsRead($notification_id, $user_id);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark notification as read']);
        }
        break;
        
    case 'mark_all_read':
        $result = $notificationSystem->markAllAsRead($user_id, $user_type);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to mark all notifications as read']);
        }
        break;
        
    case 'get_unread_count':
        $count = $notificationSystem->getUnreadCount($user_id, $user_type);
        echo json_encode(['success' => true, 'unread_count' => $count]);
        break;
        
    case 'get_notifications':
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 10;
        $notifications = $notificationSystem->getUserNotifications($user_id, $user_type, $limit);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
