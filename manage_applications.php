<?php
session_start();

// Use shared database connection and email system
require_once 'db_connect.php';
require_once 'notification_system.php';

// Simple authentication check - admin or staff can manage applications
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    header('Location: login.php');
    exit();
}

// Handle AJAX requests for application and notification details
if (isset($_GET['action'])) {
    handleAjaxRequest();
    exit();
}

function handleAjaxRequest() {
    global $conn;
    
    $action = $_GET['action'];
    
    // Check authentication
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        return;
    }
    
    switch ($action) {
        case 'get_application_details':
            getApplicationDetails();
            break;
        case 'get_notification_details':
            getNotificationDetails();
            break;
        case 'bulk_update':
            handleBulkUpdate();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}

function getApplicationDetails() {
    global $conn;
    
    // Check if application ID is provided
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid application ID']);
        return;
    }
    
    $application_id = intval($_GET['id']);
    
    // Fetch application details with related data
    $query = "
        SELECT 
            a.*,
            s.fullname as student_name,
            s.student_number,
            s.department,
            s.program,
            s.address as student_address,
            sch.title as scholarship_title,
            sch.description as scholarship_description,
            sch.amount as scholarship_amount,
            sch.required_documents,
            reviewer.fullname as reviewer_name
        FROM applications a
        LEFT JOIN students s ON a.student_id = s.id
        LEFT JOIN scholarships sch ON a.scholarship_id = sch.id
        LEFT JOIN staff reviewer ON a.reviewed_by = reviewer.id
        WHERE a.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $application_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Application not found']);
            return;
        }
        
        $application = $result->fetch_assoc();
        $result->free();
        $stmt->close();
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        return;
    }
    
    // Get uploaded documents
    $documents = [];
    $db_document_columns = [
        'enrollment_certificate' => 'Certificate of Enrollment',
        'good_moral_document' => 'Certificate of Good Moral Character', 
        'report_card' => 'Report Card (Grades)',
        'study_load_document' => 'Study Load Document'
    ];
    
    foreach ($db_document_columns as $column => $doc_type) {
        if (!empty($application[$column]) && file_exists($application[$column])) {
            $file_path = $application[$column];
            $filename = basename($file_path);
            $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $documents[] = [
                'filename' => $filename,
                'path' => $file_path,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path),
                'type' => $doc_type,
                'extension' => $file_extension,
                'source' => 'database'
            ];
        }
    }
    
    // Parse required documents
    $required_docs = [];
    if (!empty($application['required_documents'])) {
        $required_docs = json_decode($application['required_documents'], true) ?: [];
    }
    
    // Format the response
    $response = [
        'success' => true,
        'application' => [
            'id' => $application['id'],
            'student_id' => $application['student_id'],
            'scholarship_id' => $application['scholarship_id'],
            'full_name' => $application['full_name'],
            'email' => $application['email'],
            'student_number' => $application['student_number'],
            'date_of_birth' => $application['date_of_birth'],
            'year_level' => $application['year_level'],
            'program' => $application['program'],
            'department' => $application['department'],
            'address' => $application['student_address'],
            'gwa' => $application['gwa'],
            'status' => $application['status'],
            'application_date' => $application['application_date'],
            'review_date' => $application['review_date'],
            'reviewer_name' => $application['reviewer_name']
        ],
        'scholarship' => [
            'title' => $application['scholarship_title'],
            'description' => $application['scholarship_description'],
            'amount' => $application['scholarship_amount'],
            'required_documents' => $required_docs
        ],
        'documents' => $documents
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

function getNotificationDetails() {
    global $conn;
    
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['user_type'];
    
    // Check if notification ID is provided
    if (!isset($_GET['id'])) {
        echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
        return;
    }
    
    $notification_id = intval($_GET['id']);
    
    // Get notification details
    $query = "SELECT * FROM notifications WHERE id = ? AND user_id = ? AND user_type = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("iis", $notification_id, $user_id, $user_type);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Notification not found']);
            return;
        }
        
        $notification = $result->fetch_assoc();
        $result->free();
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error']);
        return;
    }
    
    // Prepare response data
    $response = [
        'success' => true,
        'notification' => $notification,
        'details' => null
    ];
    
    // Get additional details based on notification type
    if ($notification['related_type'] && $notification['related_id']) {
        switch ($notification['related_type']) {
            case 'application':
                $app_query = "
                    SELECT a.*, s.title as scholarship_title, s.sponsor, s.amount, s.description, s.deadline,
                           s.min_gwa, a.rejection_reason
                    FROM applications a 
                    JOIN scholarships s ON a.scholarship_id = s.id 
                    WHERE a.id = ? AND a.student_id = ?
                ";
                $app_stmt = $conn->prepare($app_query);
                if ($app_stmt) {
                    $app_stmt->bind_param("ii", $notification['related_id'], $user_id);
                    $app_stmt->execute();
                    $app_result = $app_stmt->get_result();
                    
                    if ($app_result && $app_result->num_rows > 0) {
                        $response['details'] = $app_result->fetch_assoc();
                    }
                    $app_result->free();
                    $app_stmt->close();
                }
                break;
                
            case 'scholarship':
                $scholarship_query = "SELECT * FROM scholarships WHERE id = ?";
                $scholarship_stmt = $conn->prepare($scholarship_query);
                if ($scholarship_stmt) {
                    $scholarship_stmt->bind_param("i", $notification['related_id']);
                    $scholarship_stmt->execute();
                    $scholarship_result = $scholarship_stmt->get_result();
                    
                    if ($scholarship_result && $scholarship_result->num_rows > 0) {
                        $response['details'] = $scholarship_result->fetch_assoc();
                    }
                    $scholarship_result->free();
                    $scholarship_stmt->close();
                }
                break;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}

function handleBulkUpdate() {
    global $conn;
    
    // Set content type to JSON
    header('Content-Type: application/json');
    
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $application_ids = $input['application_ids'] ?? [];
        $status = $input['status'] ?? '';
        
        // Validate input
        if (empty($application_ids) || !is_array($application_ids)) {
            throw new Exception('No applications selected');
        }
        
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new Exception('Invalid status');
        }
        
        // Sanitize application IDs
        $application_ids = array_map('intval', $application_ids);
        $application_ids = array_filter($application_ids, function($id) {
            return $id > 0;
        });
        
        if (empty($application_ids)) {
            throw new Exception('Invalid application IDs');
        }
        
        // First, verify that all selected applications are currently pending
        $placeholders = str_repeat('?,', count($application_ids) - 1) . '?';
        $check_sql = "SELECT id, status FROM applications WHERE id IN ($placeholders)";
        $check_stmt = $conn->prepare($check_sql);
        
        if (!$check_stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        $check_types = str_repeat('i', count($application_ids));
        $check_stmt->bind_param($check_types, ...$application_ids);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        $non_pending = [];
        $valid_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            if ($row['status'] !== 'pending') {
                $non_pending[] = $row['id'];
            } else {
                $valid_ids[] = $row['id'];
            }
        }
        $check_stmt->close();
        
        // If there are non-pending applications, return error
        if (!empty($non_pending)) {
            throw new Exception('Only pending applications can be bulk updated. You have selected ' . count($non_pending) . ' non-pending application(s). Please select only pending applications.');
        }
        
        // If no valid applications found
        if (empty($valid_ids)) {
            throw new Exception('No valid pending applications found.');
        }
        
        // Create placeholders for the update query
        $update_placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
        
        // Prepare the update query (only update pending applications)
        $sql = "UPDATE applications SET status = ?, updated_at = NOW() WHERE id IN ($update_placeholders) AND status = 'pending'";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }
        
        // Bind parameters (only for valid pending applications)
        $types = 's' . str_repeat('i', count($valid_ids)); // 's' for status, 'i' for each ID
        $params = array_merge([$status], $valid_ids);
        $stmt->bind_param($types, ...$params);
        
        // Execute the query
        if (!$stmt->execute()) {
            throw new Exception('Database execution error: ' . $stmt->error);
        }
        
        $updated_count = $stmt->affected_rows;
        $stmt->close();
        
        // Log the bulk action (optional - only if activity_logs table exists)
        $user_id = $_SESSION['user_id'];
        $action_description = "Bulk {$status} {$updated_count} pending applications";
        
        // Check if activity_logs table exists before attempting to log
        $check_table = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($check_table && $check_table->num_rows > 0) {
            // Try to log, but don't fail if it errors
            try {
                $log_sql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                
                if ($log_stmt) {
                    $action = 'bulk_update_applications';
                    $log_stmt->bind_param('iss', $user_id, $action, $action_description);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            } catch (Exception $e) {
                // Silently fail - don't break the application update
                error_log("Activity log error: " . $e->getMessage());
            }
        }
        
        // Return success response
        echo json_encode([
            'success' => true,
            'message' => "Successfully {$status} {$updated_count} pending application(s)",
            'updated_count' => $updated_count,
            'status' => $status,
            'processed_ids' => $valid_ids
        ]);
        
    } catch (Exception $e) {
        // Return error response
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Email system now integrated into db_connect.php

// Handle individual rejection with reason
if (isset($_POST['reject_application']) && isset($_POST['application_id']) && isset($_POST['rejection_reason'])) {
    require_once 'rejection_reasons.php';
    
    $app_id = $_POST['application_id'];
    $rejection_reason_key = trim($_POST['rejection_reason']);
    $custom_reason = trim($_POST['custom_reason'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    $reviewed_by = $_SESSION['user_id'];
    
    // Build the complete rejection reason
    if ($rejection_reason_key === 'custom_reason' && !empty($custom_reason)) {
        $reason_text = $custom_reason;
    } else {
        $reason_text = RejectionReasons::getReasonText($rejection_reason_key);
    }
    
    $full_rejection_reason = $reason_text;
    if (!empty($additional_notes)) {
        $full_rejection_reason .= "\n\nAdditional Notes: " . $additional_notes;
    }
    
    if (!empty($rejection_reason_key)) {
        // Get application details before updating
        $app_query = "SELECT a.*, s.title as scholarship_title, st.fullname, st.email 
                     FROM applications a 
                     JOIN scholarships s ON a.scholarship_id = s.id 
                     JOIN students st ON a.student_id = st.id 
                     WHERE a.id = ?";
        $app_stmt = $conn->prepare($app_query);
        $app_stmt->bind_param("i", $app_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        $app_data = $app_result->fetch_assoc();
        
        if ($app_data) {
            // Check if rejection_reason column exists
            $check_column = $conn->query("SHOW COLUMNS FROM applications LIKE 'rejection_reason'");
            
            if ($check_column && $check_column->num_rows > 0) {
                // Column exists, use full update
                $update_sql = "UPDATE applications SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("sii", $full_rejection_reason, $reviewed_by, $app_id);
            } else {
                // Column doesn't exist, use simple update
                $update_sql = "UPDATE applications SET status = 'rejected' WHERE id = ?";
                $stmt = $conn->prepare($update_sql);
                $stmt->bind_param("i", $app_id);
            }
            
            if ($stmt->execute()) {
                $stmt->close();
                
                try {
                    // Create notification with rejection reason
                    $notificationSystem = new NotificationSystem($conn);
                    $notificationSystem->notifyApplicationRejection(
                        $app_data['student_id'], 
                        $app_id, 
                        $app_data['scholarship_title'], 
                        $full_rejection_reason
                    );
                    
                    // Send email notification with rejection reason
                    sendNotificationEmail('application_rejection', [
                        'email' => $app_data['email'],
                        'name' => $app_data['fullname'],
                        'scholarship_title' => $app_data['scholarship_title'],
                        'rejection_reason' => $full_rejection_reason
                    ]);
                } catch (Exception $e) {
                    error_log("Rejection notification error: " . $e->getMessage());
                }
                
                $_SESSION['success_message'] = "✅ Application rejected successfully! Student has been notified with detailed feedback.";
            } else {
                $_SESSION['error_message'] = "❌ Failed to reject application. Please try again.";
                error_log("Rejection update error: " . $stmt->error);
                $stmt->close();
            }
        }
    } else {
        $_SESSION['error_message'] = "⚠️ Please select a rejection reason before submitting.";
    }
    
    header('Location: manage_applications.php');
    exit();
}

// Simple approval processing is handled in the single approve/reject actions section below

// Handle bulk actions
if (isset($_POST['bulk_action']) && isset($_POST['selected_applications'])) {
    $action = $_POST['bulk_action'];
    $selected_apps = $_POST['selected_applications'];
    $reviewed_by = $_SESSION['user_id'];
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        
        foreach ($selected_apps as $app_id) {
            // Get application details before updating
            $app_query = "SELECT a.*, s.title as scholarship_title, st.fullname, st.email 
                         FROM applications a 
                         JOIN scholarships s ON a.scholarship_id = s.id 
                         JOIN students st ON a.student_id = st.id 
                         WHERE a.id = ?";
            $app_stmt = $conn->prepare($app_query);
            $app_stmt->bind_param("i", $app_id);
            $app_stmt->execute();
            $app_result = $app_stmt->get_result();
            $app_data = $app_result->fetch_assoc();
            
            // Update application status
            $update_sql = "UPDATE applications SET status = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("sii", $status, $reviewed_by, $app_id);
            $stmt->execute();
            
            if ($app_data) {
                // Create notification
                $notificationSystem = new NotificationSystem($conn);
                $notificationSystem->notifyApplicationStatus(
                    $app_data['student_id'], 
                    $app_id, 
                    $app_data['scholarship_title'], 
                    $status
                );
                
                // Send email notification
                sendNotificationEmail('application_status', [
                    'email' => $app_data['email'],
                    'name' => $app_data['fullname'],
                    'scholarship_title' => $app_data['scholarship_title'],
                    'status' => $status,
                    'application_id' => $app_id
                ]);
            }
        }
    }
    
    header("Location: manage_applications.php?" . http_build_query($_GET));
    exit();
}

// Handle single approve/reject actions
if (isset($_POST['action']) && isset($_POST['application_id'])) {
    $application_id = $_POST['application_id'];
    $status = ($_POST['action'] == 'approve') ? 'approved' : 'rejected';
    $reviewed_by = $_SESSION['user_id'];
    
    // Get application details before updating
    $app_query = "SELECT a.*, s.title as scholarship_title, st.fullname, st.email 
                 FROM applications a 
                 JOIN scholarships s ON a.scholarship_id = s.id 
                 JOIN students st ON a.student_id = st.id 
                 WHERE a.id = ?";
    $app_stmt = $conn->prepare($app_query);
    $app_stmt->bind_param("i", $application_id);
    $app_stmt->execute();
    $app_result = $app_stmt->get_result();
    $app_data = $app_result->fetch_assoc();
    
    // Update application status
    $update_sql = "UPDATE applications SET status = ?, reviewed_by = ?, review_date = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("sii", $status, $reviewed_by, $application_id);
    if (!$stmt->execute()) {
        error_log("Update error: " . $stmt->error);
    }
    $stmt->close();
    
    if ($app_data) {
        try {
            // Create notification
            $notificationSystem = new NotificationSystem($conn);
            $notificationSystem->notifyApplicationStatus(
                $app_data['student_id'], 
                $application_id, 
                $app_data['scholarship_title'], 
                $status
            );
            
            // Send email notification
            sendNotificationEmail('application_status', [
                'email' => $app_data['email'],
                'name' => $app_data['fullname'],
                'scholarship_title' => $app_data['scholarship_title'],
                'status' => $status,
                'application_id' => $application_id
            ]);
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
        
        // Set success message based on action
        if ($status === 'approved') {
            $_SESSION['success_message'] = "🎉 Application approved successfully! Student has been notified.";
        } else {
            $_SESSION['success_message'] = "✅ Application rejected successfully! Student has been notified.";
        }
    } else {
        $_SESSION['error_message'] = "❌ Failed to update application status. Please try again.";
    }
    
    header("Location: manage_applications.php?" . http_build_query($_GET));
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$scholarship_filter = $_GET['scholarship'] ?? 'all';
$search_query = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'application_date';
$sort_order = $_GET['order'] ?? 'DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = intval($_GET['per_page'] ?? 25);
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($scholarship_filter !== 'all') {
    $where_conditions[] = "a.scholarship_id = ?";
    $params[] = $scholarship_filter;
    $param_types .= 'i';
}

if (!empty($search_query)) {
    $where_conditions[] = "(a.full_name LIKE ? OR a.email LIKE ?)";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM applications a JOIN scholarships sch ON a.scholarship_id = sch.id $where_clause";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    if ($count_stmt) {
        $count_stmt->bind_param($param_types, ...$params);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total_applications = $count_result->fetch_assoc()['total'];
        $count_result->free();
        $count_stmt->close();
    } else {
        $total_applications = 0;
    }
} else {
    // Ensure fresh connection for this query
    if (!$conn || $conn->ping() === false) {
        // Reconnect if connection is lost
        $conn = new mysqli("127.0.0.1", "root", "", "scholarseek", 3306);
        if ($conn->connect_error) {
            error_log("Reconnection failed: " . $conn->connect_error);
            $total_applications = 0;
        } else {
            $conn->set_charset("utf8mb4");
        }
    }
    
    if ($conn && !$conn->connect_error) {
        $count_result = $conn->query($count_sql);
        if ($count_result) {
            $total_applications = $count_result->fetch_assoc()['total'];
            $count_result->free();
        } else {
            error_log("Count query failed: " . $conn->error);
            $total_applications = 0;
        }
    } else {
        $total_applications = 0;
    }
}
$total_pages = ceil($total_applications / $per_page);

// Fetch applications with pagination
$valid_sort_columns = ['application_date', 'full_name', 'status', 'gwa', 'scholarship_title'];
$sort_by = in_array($sort_by, $valid_sort_columns) ? $sort_by : 'application_date';
$sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

$sql = "SELECT a.*, sch.title as scholarship_title, sch.deadline as scholarship_deadline, s.fullname as student_name, s.student_number, s.department, s.program, s.year_level
        FROM applications a 
        JOIN scholarships sch ON a.scholarship_id = sch.id 
        LEFT JOIN students s ON a.student_id = s.id
        $where_clause 
        ORDER BY $sort_by $sort_order 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;
$param_types .= 'ii';

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

$applications = $result->fetch_all(MYSQLI_ASSOC);

// Get scholarships for filter dropdown (only those with applications)
$scholarships_sql = "SELECT DISTINCT s.id, s.title 
                     FROM scholarships s 
                     INNER JOIN applications a ON s.id = a.scholarship_id 
                     ORDER BY s.title";
$scholarships_result = $conn->query($scholarships_sql);

if (!$scholarships_result) {
    error_log("Scholarships query failed: " . $conn->error);
    $scholarships = [];
} else {
    $scholarships = $scholarships_result->fetch_all(MYSQLI_ASSOC);
}

// If no scholarships with applications found, get all scholarships as fallback
if (empty($scholarships)) {
    $all_scholarships_sql = "SELECT id, title FROM scholarships ORDER BY title";
    $all_scholarships_result = $conn->query($all_scholarships_sql);
    if ($all_scholarships_result) {
        $scholarships = $all_scholarships_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Get overall statistics (not affected by current filters)
$overall_stats_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM applications a JOIN scholarships sch ON a.scholarship_id = sch.id";

$overall_stats_result = $conn->query($overall_stats_sql);
$stats = $overall_stats_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ScholarSeek</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/img/icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/img/icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="assets/img/icon.png">
    
    <!-- Preload critical CSS to prevent FOUC -->
    <link rel="preload" href="assets/css/manage_applications.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" as="style">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/manage_applications.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/logout-modal.css?v=<?php echo time(); ?>">
    
    <!-- Critical CSS for immediate styling -->
    <style>
        /* Ensure page displays immediately */
        body { 
            font-family: 'Poppins', sans-serif;
            background: var(--gray-50);
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo" />
        </div>
        <nav class="sidebar-nav">
            <?php if ($_SESSION['user_type'] == 'admin'): ?>
            <a href="admin_dashboard.php" class="sidebar-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_scholarships.php" class="sidebar-item">
                <i class="fas fa-graduation-cap"></i>
                Scholarships
            </a>
            <a href="manage_applications.php" class="sidebar-item active">
                <i class="fas fa-file-alt"></i>
                Applications
            </a>
            <a href="manage_students.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                Students
            </a>
            <?php if ($_SESSION['user_type'] == 'admin'): ?>
            <a href="manage_staff.php" class="sidebar-item">
                <i class="fas fa-user-tie"></i>
                Staff
            </a>
            <?php endif; ?>
            <?php else: ?>
            <a href="staff_dashboard.php" class="sidebar-item">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
            <a href="manage_scholarships.php" class="sidebar-item">
                <i class="fas fa-graduation-cap"></i>
                Scholarships
            </a>
            <a href="manage_applications.php" class="sidebar-item active">
                <i class="fas fa-file-alt"></i>
                Applications
            </a>
            <a href="manage_students.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                Students
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

    <!-- Bulk Action Confirmation Modal -->
    <div class="bulk-confirm-modal" id="bulkConfirmModal">
        <div class="bulk-confirm-overlay" onclick="closeBulkConfirm()"></div>
        <div class="bulk-confirm-content">
            <div class="bulk-confirm-header">
                <div class="bulk-confirm-icon" id="bulkConfirmIcon">
                    <i class="fas fa-check-double"></i>
                </div>
                <h3 class="bulk-confirm-title" id="bulkConfirmTitle">Confirm Bulk Action</h3>
            </div>
            
            <div class="bulk-confirm-body">
                <p class="bulk-confirm-message" id="bulkConfirmMessage">
                    Are you sure you want to approve 5 pending application(s)?
                </p>
                <div class="bulk-confirm-details">
                    <span class="bulk-confirm-note">This action cannot be undone.</span>
                </div>
            </div>
            
            <div class="bulk-confirm-actions">
                <button type="button" class="bulk-confirm-btn cancel-btn" onclick="closeBulkConfirm()">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="button" class="bulk-confirm-btn confirm-btn" id="bulkConfirmBtn" onclick="executeBulkAction()">
                    <i class="fas fa-check"></i>
                    <span id="bulkConfirmBtnText">Approve</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">

        <!-- Page Header -->
        <div class="page-header-compact">
            <div class="header-left">
                <h3><i class="fas fa-file-alt"></i> Scholarship Applications</h3>
                <p>Review and manage student scholarship applications - <?php echo number_format($total_applications); ?> total applications</p>
            </div>
            <div class="header-right">
                <!-- Statistics Pills -->
                <div class="stats-cards">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'all', 'page' => 1])); ?>" class="stat-pill stat-total">
                        <span class="stat-label">TOTAL</span>
                        <span class="stat-value"><?php echo number_format($stats['total']); ?></span>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'pending', 'page' => 1])); ?>" class="stat-pill stat-pending">
                        <span class="stat-label">PENDING</span>
                        <span class="stat-value"><?php echo number_format($stats['pending']); ?></span>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'approved', 'page' => 1])); ?>" class="stat-pill stat-approved">
                        <span class="stat-label">APPROVED</span>
                        <span class="stat-value"><?php echo number_format($stats['approved']); ?></span>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['status' => 'rejected', 'page' => 1])); ?>" class="stat-pill stat-rejected">
                        <span class="stat-label">REJECTED</span>
                        <span class="stat-value"><?php echo number_format($stats['rejected']); ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Advanced Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-form" id="filtersForm">
                <div class="filters-row">
                    <div class="filter-group">
                        <label for="search"><i class="fas fa-search"></i> Search</label>
                        <input type="text" id="search" name="search" placeholder="Search by name or email..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><i class="fas fa-filter"></i> Status</label>
                        <select id="status" name="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="scholarship"><i class="fas fa-graduation-cap"></i> Scholarship</label>
                        <select id="scholarship" name="scholarship">
                            <option value="all" <?php echo $scholarship_filter === 'all' ? 'selected' : ''; ?>>All Scholarships</option>
                            <?php foreach ($scholarships as $scholarship): ?>
                                <option value="<?php echo $scholarship['id']; ?>" 
                                        <?php echo $scholarship_filter == $scholarship['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($scholarship['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="per_page"><i class="fas fa-list"></i> Per Page</label>
                        <select id="per_page" name="per_page">
                            <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo $per_page == 200 ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                </div>
                
            </form>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulkActionsBar" style="display: none;">
            <div class="bulk-selection-info">
                <div class="selection-count">
                    <i class="fas fa-check-square"></i>
                    <span id="selectedCount">0</span> pending applications selected
                </div>
                <div class="selection-hint">
                    Choose an action to apply to selected applications
                </div>
            </div>
            <div class="bulk-actions">
                <button type="button" class="btn btn-approve" onclick="bulkApprove()">
                    <i class="fas fa-check-double"></i>
                    <span class="btn-text">Approve Selected</span>
                </button>
                <button type="button" class="btn btn-reject" onclick="bulkReject()">
                    <i class="fas fa-ban"></i>
                    <span class="btn-text">Reject Selected</span>
                </button>
                <button type="button" class="btn btn-clear" onclick="clearSelection()">
                    <i class="fas fa-times"></i>
                    <span class="btn-text">Clear</span>
                </button>
            </div>
        </div>
        
        <!-- Floating Action Bar -->
        <div class="floating-actions" id="floatingActions" style="display: none;">
            <div class="floating-content">
                <div class="floating-info">
                    <span id="floatingCount">0</span> selected
                </div>
                <div class="floating-buttons">
                    <button type="button" class="floating-btn approve-btn" onclick="bulkApprove()" title="Approve Selected">
                        <i class="fas fa-check"></i>
                    </button>
                    <button type="button" class="floating-btn reject-btn" onclick="bulkReject()" title="Reject Selected">
                        <i class="fas fa-times"></i>
                    </button>
                    <button type="button" class="floating-btn clear-btn" onclick="clearSelection()" title="Clear Selection">
                        <i class="fas fa-undo"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Summary -->
        <div class="results-summary">
            <div class="results-info">
                <span class="results-count">
                    Showing <?php echo count($applications); ?> of <?php echo number_format($total_applications); ?> applications
                    <?php if ($page > 1): ?>
                        (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)
                    <?php endif; ?>
                </span>
                
                <?php if ($search_query || $status_filter !== 'all' || $scholarship_filter !== 'all'): ?>
                    <div class="active-filters">
                        <span class="filter-label">Active filters:</span>
                        <?php if ($search_query): ?>
                            <span class="filter-tag">
                                <i class="fas fa-search"></i> "<?php echo htmlspecialchars($search_query); ?>"
                            </span>
                        <?php endif; ?>
                        <?php if ($status_filter !== 'all'): ?>
                            <span class="filter-tag">
                                <i class="fas fa-filter"></i> <?php echo ucfirst($status_filter); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($scholarship_filter !== 'all'): ?>
                            <?php 
                            $selected_scholarship = array_filter($scholarships, function($s) use ($scholarship_filter) {
                                return $s['id'] == $scholarship_filter;
                            });
                            $selected_scholarship = reset($selected_scholarship);
                            ?>
                            <span class="filter-tag">
                                <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($selected_scholarship['title']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="sort-controls">
                <label for="sort">Sort by:</label>
                <select id="sort" name="sort" onchange="updateSort()">
                    <option value="application_date" <?php echo $sort_by === 'application_date' ? 'selected' : ''; ?>>Date Applied</option>
                    <option value="full_name" <?php echo $sort_by === 'full_name' ? 'selected' : ''; ?>>Student Name</option>
                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                    <option value="gwa" <?php echo $sort_by === 'gwa' ? 'selected' : ''; ?>>GWA</option>
                    <option value="scholarship_title" <?php echo $sort_by === 'scholarship_title' ? 'selected' : ''; ?>>Scholarship</option>
                </select>
                <button type="button" class="sort-order-btn" onclick="toggleSortOrder()" 
                        data-order="<?php echo $sort_order; ?>" title="Sort Order">
                    <i class="fas fa-sort-amount-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>"></i>
                </button>
            </div>
        </div>

        <!-- Applications Table - Clean Design -->
        <?php if (!empty($applications)): ?>
                <div class="applications-container" id="applicationsTable">
                    <div class="table-wrapper">
                        <table class="applications-table clean-design">
                            <thead>
                                <tr>
                                    <th class="checkbox-col">
                                        <input type="checkbox" id="selectAll" class="select-all-checkbox" onchange="toggleSelectAll()">
                                    </th>
                                    <th class="name-col">Full name</th>
                                    <th class="email-col">Email</th>
                                    <th class="status-col">Application Status</th>
                                    <th class="scholarship-col">Scholarship Title</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $app): ?>
                                    <tr class="application-row status-<?php echo $app['status']; ?> <?php echo ($app['status'] !== 'pending') ? 'non-selectable' : ''; ?>" data-id="<?php echo $app['id']; ?>" onclick="<?php echo ($app['status'] === 'pending') ? 'viewApplication(' . $app['id'] . ')' : 'viewApplication(' . $app['id'] . ')'; ?>" style="cursor: pointer;">
                                        <td class="checkbox-col" onclick="event.stopPropagation();">
                                            <?php if ($app['status'] === 'pending'): ?>
                                                <input type="checkbox" class="application-checkbox" value="<?php echo $app['id']; ?>" onchange="updateBulkActions()">
                                            <?php endif; ?>
                                        </td>
                                        <td class="name-col">
                                            <div class="student-cell">
                                                <div class="student-avatar">
                                                    <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
                                                </div>
                                                <span class="student-name"><?php echo htmlspecialchars($app['full_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="email-col">
                                            <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" class="email-link">
                                                <?php echo htmlspecialchars($app['email']); ?>
                                            </a>
                                        </td>
                                        <td class="status-col">
                                            <div class="status-cell">
                                                <span class="status-dot status-<?php echo $app['status']; ?>"></span>
                                                <span class="status-text"><?php echo ucfirst($app['status']); ?></span>
                                            </div>
                                        </td>
                                        <td class="scholarship-col">
                                            <span class="scholarship-name"><?php echo htmlspecialchars($app['scholarship_title']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
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
                        <span><?php echo number_format($total_applications); ?> total applications</span>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state-container">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No Applications Found</h3>
                <p>No applications match your current filters. Try adjusting your search criteria.</p>
                <a href="manage_applications.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i> View All Applications
                </a>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div id="applicationModal" class="modal" style="display: none;">
        <div class="modal-content application-modal">
            <div class="modal-header">
                <div class="modal-title-group">
                    <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                    <p class="modal-subtitle">Review the student's application information and submitted documents before taking action.</p>
                </div>
                <span class="close" onclick="closeApplicationModal()">&times;</span>
            </div>
            <div class="modal-body" id="applicationModalBody">
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading application details...</p>
                </div>
            </div>
            <div class="modal-footer" id="applicationModalFooter" style="display: none;">
                <div class="modal-actions">
                    <button type="button" id="rejectAppBtn" class="btn btn-danger btn-reject-app" title="Reject Application">
                        <i class="fas fa-times-circle"></i> Reject
                    </button>
                    <button type="button" id="approveAppBtn" class="btn btn-success btn-approve-app" title="Approve Application">
                        <i class="fas fa-check-circle"></i> Approve
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prevent FOUC by showing body when loaded
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
            initializeFilters();
            initializeSorting();

            // Make each application row behave like a clickable card
            document.querySelectorAll('.application-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Ignore clicks on checkboxes or bulk controls
                    if (e.target.closest('.application-checkbox')) return;

                    const applicationId = this.getAttribute('data-id');
                    if (applicationId) {
                        // viewApplication is overridden by modal_fix.js to the enhanced version
                        if (typeof viewApplication === 'function') {
                            viewApplication(applicationId);
                        }
                    }
                });
            });

            // Setup modal action button listeners
            const rejectBtn = document.getElementById('rejectAppBtn');
            const approveBtn = document.getElementById('approveAppBtn');

            if (rejectBtn) {
                rejectBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof showRejectionModal === 'function') {
                        showRejectionModal(window.currentApplicationId, window.currentStudentName, window.currentScholarshipTitle);
                    } else {
                        console.error('showRejectionModal function not found');
                    }
                });
            }

            if (approveBtn) {
                approveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    approveApplication();
                });
            }
        });
        
        function viewApplication(applicationId) {
            const modal = document.getElementById('applicationModal');
            const modalBody = document.getElementById('applicationModalBody');
            
            // Show modal with loading state
            modal.style.display = 'block';
            modalBody.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading application details...</p>
                </div>
            `;
            
            // Fetch application details via AJAX
            fetch(`manage_applications.php?action=get_application_details&id=${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayApplicationDetails(data);
                    } else {
                        modalBody.innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <p>Error: ${data.error || 'Failed to load application details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching application details:', error);
                    modalBody.innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <p>Failed to load application details. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        let currentApplicationId = null;
        let currentStudentName = null;
        let currentScholarshipTitle = null;
        
        function displayApplicationDetails(data) {
            const app = data.application;
            const scholarship = data.scholarship;
            const documents = data.documents;
            
            // Store current application data for approve/reject actions
            currentApplicationId = app.id;
            currentStudentName = app.full_name || app.student_name;
            currentScholarshipTitle = scholarship.title;
            window.currentApplicationId = currentApplicationId;
            window.currentStudentName = currentStudentName;
            window.currentScholarshipTitle = currentScholarshipTitle;
            
            const modalBody = document.getElementById('applicationModalBody');
            const modalFooter = document.getElementById('applicationModalFooter');
            modalBody.innerHTML = `
                <div class="application-details">
                    <!-- Student Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-user"></i> Student Information</h4>
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Full Name:</label>
                                <span>${app.full_name}</span>
                            </div>
                            <div class="detail-item">
                                <label>Email:</label>
                                <span>${app.email}</span>
                            </div>
                            <div class="detail-item">
                                <label>Student Number:</label>
                                <span>${app.student_number || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Date of Birth:</label>
                                <span>${app.date_of_birth ? new Date(app.date_of_birth).toLocaleDateString() : 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Year Level:</label>
                                <span>${app.year_level}</span>
                            </div>
                            <div class="detail-item">
                                <label>Program:</label>
                                <span>${app.program || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Department:</label>
                                <span>${app.department || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>GWA:</label>
                                <span>${app.gwa ? parseFloat(app.gwa).toFixed(2) : 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scholarship Information -->
                    <div class="details-section">
                        <h4><i class="fas fa-graduation-cap"></i> Scholarship Information</h4>
                        <div class="details-grid">
                            <div class="detail-item full-width">
                                <label>Scholarship Title:</label>
                                <span>${scholarship.title}</span>
                            </div>
                            <div class="detail-item">
                                <label>Amount:</label>
                                <span>₱${parseFloat(scholarship.amount).toLocaleString()}</span>
                            </div>
                            <div class="detail-item full-width">
                                <label>Description:</label>
                                <span>${scholarship.description}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Application Status -->
                    <div class="details-section">
                        <h4><i class="fas fa-info-circle"></i> Application Status</h4>
                        <div class="details-grid">
                            <div class="detail-item">
                                <label>Status:</label>
                                <span class="status-badge status-${app.status}">
                                    <i class="fas ${getStatusIcon(app.status)}"></i>
                                    ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Application Date:</label>
                                <span>${new Date(app.application_date).toLocaleDateString()}</span>
                            </div>
                            ${app.review_date ? `
                                <div class="detail-item">
                                    <label>Review Date:</label>
                                    <span>${new Date(app.review_date).toLocaleDateString()}</span>
                                </div>
                                <div class="detail-item">
                                    <label>Reviewed By:</label>
                                    <span>${app.reviewer_name || 'N/A'}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Submitted Documents -->
                    <div class="details-section">
                        <h4><i class="fas fa-file-pdf"></i> Submitted Documents</h4>
                        <div class="documents-list">
                            ${documents.length > 0 ? documents.map(doc => `
                                <div class="document-item">
                                    <div class="document-info">
                                        <i class="fas fa-file-${doc.extension === 'pdf' ? 'pdf' : 'alt'}"></i>
                                        <div class="document-details">
                                            <span class="document-name">${doc.type}</span>
                                            <span class="document-filename" title="${doc.filename}">${doc.filename}</span>
                                            <span class="document-size">${formatFileSize(doc.size)}</span>
                                        </div>
                                    </div>
                                    <div class="document-actions">
                                        <button class="btn-view-doc" onclick="viewDocument('${doc.path}')" title="View Document">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn-download-doc" onclick="downloadDocument('${doc.path}', '${doc.filename}')" title="Download Document">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </div>
                                </div>
                            `).join('') : '<p class="no-documents">No documents submitted</p>'}
                        </div>
                    </div>
                </div>
            `;
            
            // Show/hide modal footer based on application status
            if (app.status === 'pending') {
                modalFooter.style.display = 'block';
            } else {
                modalFooter.style.display = 'none';
            }
        }
        
        function getStatusIcon(status) {
            const icons = {
                'pending': 'fa-hourglass-half',
                'approved': 'fa-check-circle',
                'rejected': 'fa-times-circle'
            };
            return icons[status] || 'fa-question-circle';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function viewDocument(path) {
            window.open(path, '_blank');
        }
        
        function downloadDocument(path, filename) {
            const link = document.createElement('a');
            link.href = path;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        function approveApplication() {
            if (!window.currentApplicationId) return;

            const confirmModal = document.createElement('div');
            confirmModal.className = 'confirmation-modal show';
            confirmModal.innerHTML = `
                <div class="modal-content">
                    <div class="modal-header">
                        <h3><i class="fas fa-check-circle" style="color: var(--success-color);"></i> Approve Application</h3>
                        <span class="close" onclick="this.closest('.confirmation-modal').remove()">&times;</span>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to <strong>approve</strong> this application for <strong>${window.currentStudentName || ''}</strong>?</p>
                        <p style="color: var(--gray-600); font-size: 0.85rem;">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <div class="modal-actions">
                            <button type="button" class="btn-secondary" onclick="this.closest('.confirmation-modal').remove()">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="button" class="btn-approve-app" onclick="submitApplicationAction(${window.currentApplicationId}, 'approve'); this.closest('.confirmation-modal').remove();">
                                <i class="fas fa-check"></i> Approve
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmModal);

            confirmModal.addEventListener('click', function(e) {
                if (e.target === this) this.remove();
            });
        }
        
        function rejectApplication() {
            if (!window.currentApplicationId) return;
            
            showRejectionModal(window.currentApplicationId, window.currentStudentName, window.currentScholarshipTitle);
        }
        
        function submitApplicationAction(applicationId, action) {
            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_applications.php';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = action;
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'application_id';
            idInput.value = applicationId;
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            document.body.appendChild(form);
            form.submit();
        }
        
        function closeApplicationModal() {
            const modal = document.getElementById('applicationModal');
            const modalFooter = document.getElementById('applicationModalFooter');
            modal.style.display = 'none';
            modalFooter.style.display = 'none';
            currentApplicationId = null;
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('applicationModal');
            if (event.target === modal) {
                closeApplicationModal();
            }
        }
        
        // Filtering and Sorting
        function initializeFilters() {
            const filtersForm = document.getElementById('filtersForm');
            const searchInput = document.getElementById('search');
            
            // Auto-submit on filter changes
            ['status', 'scholarship', 'per_page'].forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('change', function() {
                        filtersForm.submit();
                    });
                }
            });
            
            // Search on Enter key or after longer pause
            let searchTimeout;
            if (searchInput) {
                // Submit on Enter key
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        clearTimeout(searchTimeout);
                        filtersForm.submit();
                    }
                });
                
                // Submit after longer pause (2 seconds)
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filtersForm.submit();
                    }, 2000);
                });
            }
        }
        
        function initializeSorting() {
            const sortableHeaders = document.querySelectorAll('.sortable');
            
            sortableHeaders.forEach(header => {
                header.style.cursor = 'pointer';
                header.addEventListener('click', function() {
                    const sortBy = this.dataset.sort;
                    const currentSort = new URLSearchParams(window.location.search).get('sort');
                    const currentOrder = new URLSearchParams(window.location.search).get('order') || 'DESC';
                    
                    let newOrder = 'DESC';
                    if (currentSort === sortBy) {
                        newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                    }
                    
                    const url = new URL(window.location);
                    url.searchParams.set('sort', sortBy);
                    url.searchParams.set('order', newOrder);
                    url.searchParams.delete('page'); // Reset to first page
                    
                    window.location.href = url.toString();
                });
            });
        }
        
        function updateSort() {
            const sortSelect = document.getElementById('sort');
            if (sortSelect) {
                const url = new URL(window.location);
                url.searchParams.set('sort', sortSelect.value);
                url.searchParams.delete('page'); // Reset to first page
                window.location.href = url.toString();
            }
        }
        
        function toggleSortOrder() {
            const sortOrderBtn = document.querySelector('.sort-order-btn');
            if (sortOrderBtn) {
                const currentOrder = sortOrderBtn.dataset.order;
                const newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
                
                const url = new URL(window.location);
                url.searchParams.set('order', newOrder);
                url.searchParams.delete('page'); // Reset to first page
                window.location.href = url.toString();
            }
        }
        
        // View Toggle (for future card view implementation)
        function initializeViewToggle() {
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const view = this.dataset.view;
                    
                    // Update active state
                    toggleBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // TODO: Implement view switching
                    if (view === 'cards') {
                        alert('Card view will be implemented in future update');
                    }
                });
            });
        }
        
        // Bulk Selection Functions (Pending Only)
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            if (!selectAllCheckbox) return;
            
            const applicationCheckboxes = document.querySelectorAll('.application-checkbox');
            
            applicationCheckboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const selectedCheckboxes = document.querySelectorAll('.application-checkbox:checked');
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const floatingActions = document.getElementById('floatingActions');
            const selectedCount = document.getElementById('selectedCount');
            const floatingCount = document.getElementById('floatingCount');
            const selectAllCheckbox = document.getElementById('selectAll');
            
            // Update selected count
            selectedCount.textContent = selectedCheckboxes.length;
            floatingCount.textContent = selectedCheckboxes.length;
            
            // Show/hide bulk actions
            if (selectedCheckboxes.length > 0) {
                bulkActionsBar.style.display = 'flex';
                floatingActions.style.display = 'block';
                
                // Add body class for floating actions
                document.body.classList.add('has-floating-actions');
            } else {
                bulkActionsBar.style.display = 'none';
                floatingActions.style.display = 'none';
                
                // Remove body class
                document.body.classList.remove('has-floating-actions');
            }
            
            // Update select all checkbox state (only if it exists)
            if (selectAllCheckbox) {
                const allCheckboxes = document.querySelectorAll('.application-checkbox');
                if (selectedCheckboxes.length === 0) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = false;
                } else if (selectedCheckboxes.length === allCheckboxes.length) {
                    selectAllCheckbox.indeterminate = false;
                    selectAllCheckbox.checked = true;
                } else {
                    selectAllCheckbox.indeterminate = true;
                    selectAllCheckbox.checked = false;
                }
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.application-checkbox, #selectAll');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                checkbox.indeterminate = false;
            });
            updateBulkActions();
            
            // Show feedback
            showToast('Selection cleared', 'info', 2000);
        }
        
        function getSelectedApplicationIds() {
            const selectedCheckboxes = document.querySelectorAll('.application-checkbox:checked');
            return Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
        }
        
        // Bulk confirmation modal variables
        let pendingBulkAction = null;
        let pendingBulkIds = [];
        
        function bulkApprove() {
            const selectedIds = getSelectedApplicationIds();
            if (selectedIds.length === 0) {
                showToast('Please select pending applications to approve', 'warning');
                return;
            }
            
            showBulkConfirmation(selectedIds, 'approved');
        }
        
        function bulkReject() {
            const selectedIds = getSelectedApplicationIds();
            if (selectedIds.length === 0) {
                showToast('Please select pending applications to reject', 'warning');
                return;
            }
            
            showBulkConfirmation(selectedIds, 'rejected');
        }
        
        function showBulkConfirmation(applicationIds, action) {
            pendingBulkAction = action;
            pendingBulkIds = applicationIds;
            
            const modal = document.getElementById('bulkConfirmModal');
            const icon = document.getElementById('bulkConfirmIcon');
            const title = document.getElementById('bulkConfirmTitle');
            const message = document.getElementById('bulkConfirmMessage');
            const confirmBtn = document.getElementById('bulkConfirmBtn');
            const confirmBtnText = document.getElementById('bulkConfirmBtnText');
            
            // Configure modal based on action
            if (action === 'approved') {
                icon.innerHTML = '<i class="fas fa-check-double"></i>';
                icon.className = 'bulk-confirm-icon approve-icon';
                title.textContent = 'Approve Applications';
                message.textContent = `Are you sure you want to approve ${applicationIds.length} pending application(s)?`;
                confirmBtn.className = 'bulk-confirm-btn confirm-btn approve-confirm';
                confirmBtnText.textContent = 'Approve';
            } else {
                icon.innerHTML = '<i class="fas fa-ban"></i>';
                icon.className = 'bulk-confirm-icon reject-icon';
                title.textContent = 'Reject Applications';
                message.textContent = `Are you sure you want to reject ${applicationIds.length} pending application(s)?`;
                confirmBtn.className = 'bulk-confirm-btn confirm-btn reject-confirm';
                confirmBtnText.textContent = 'Reject';
            }
            
            // Show modal with animation
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }
        
        function closeBulkConfirm() {
            const modal = document.getElementById('bulkConfirmModal');
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
                pendingBulkAction = null;
                pendingBulkIds = [];
            }, 300);
        }
        
        function executeBulkAction() {
            if (pendingBulkAction && pendingBulkIds.length > 0) {
                closeBulkConfirm();
                bulkUpdateStatus(pendingBulkIds, pendingBulkAction);
            }
        }
        
        function bulkUpdateStatus(applicationIds, status) {
            // Show loading state
            const bulkActionsBar = document.getElementById('bulkActionsBar');
            const originalContent = bulkActionsBar.innerHTML;
            bulkActionsBar.innerHTML = '<div class="bulk-loading"><i class="fas fa-spinner fa-spin"></i> Processing...</div>';
            
            // Send AJAX request
            fetch('manage_applications.php?action=bulk_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    application_ids: applicationIds,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Format message based on status
                    const statusMessage = status === 'approved' ? '🎉 Application Approved!' : '✅ Application Rejected!';
                    showToast(statusMessage, 'success');
                    // Reload the page to show updated data
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    showToast(data.message || 'An error occurred', 'error');
                    bulkActionsBar.innerHTML = originalContent;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while updating applications', 'error');
                bulkActionsBar.innerHTML = originalContent;
            });
        }
        
        // Initialize view toggle and bulk selection
        document.addEventListener('DOMContentLoaded', function() {
            initializeViewToggle();
            updateBulkActions(); // Initialize bulk actions state
        });

        // Logout function now handled by custom-modal.js
    </script>
    <script src="assets/js/custom-modal.js?v=<?php echo time(); ?>"></script>
    <script src="modal_fix.js?v=<?php echo time(); ?>"></script>

    <?php include 'rejection_modal.php'; ?>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="toast-container"></div>

    <script>
    // Toast notification system
    function showToast(message, type = 'success', duration = 3000) {
        const toastContainer = document.getElementById('toast-container');
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Toast content
        const icon = type === 'success' ? 'fa-check-circle' : 
                    type === 'error' ? 'fa-times-circle' : 
                    type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
        
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${icon}"></i>
                <span class="toast-message">${message}</span>
            </div>
            <button class="toast-close" onclick="closeToast(this)">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Show toast with animation
        setTimeout(() => toast.classList.add('show'), 100);
        
        // Auto remove after duration
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);
    }

    function closeToast(button) {
        const toast = button.parentNode;
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    // Show toast notifications based on session messages
    <?php if (isset($_SESSION['success_message'])): ?>
        showToast('<?php echo addslashes($_SESSION['success_message']); ?>', 'success');
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        showToast('<?php echo addslashes($_SESSION['error_message']); ?>', 'error');
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
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