<?php
/**
 * Get Student Details API
 * Returns student information and application count for viewing
 */

session_start();
require_once 'db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication - admin or staff only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit();
}

// Check if student ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid student ID']);
    exit();
}

$student_id = intval($_GET['id']);

try {
    // Get student details
    $student_query = "SELECT * FROM students WHERE id = ?";
    $stmt = mysqli_prepare($conn, $student_query);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) === 0) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }
    
    $student = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    // Get application counts
    $app_count_query = "SELECT COUNT(*) as count FROM applications WHERE student_id = ?";
    $app_stmt = mysqli_prepare($conn, $app_count_query);
    mysqli_stmt_bind_param($app_stmt, "i", $student_id);
    mysqli_stmt_execute($app_stmt);
    $app_result = mysqli_stmt_get_result($app_stmt);
    $application_count = mysqli_fetch_assoc($app_result)['count'];
    mysqli_stmt_close($app_stmt);
    
    // Get approved application count
    $approved_count_query = "SELECT COUNT(*) as count FROM applications WHERE student_id = ? AND status = 'approved'";
    $approved_stmt = mysqli_prepare($conn, $approved_count_query);
    mysqli_stmt_bind_param($approved_stmt, "i", $student_id);
    mysqli_stmt_execute($approved_stmt);
    $approved_result = mysqli_stmt_get_result($approved_stmt);
    $approved_count = mysqli_fetch_assoc($approved_result)['count'];
    mysqli_stmt_close($approved_stmt);
    
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
?>
