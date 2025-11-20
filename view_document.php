<?php
session_start();
include 'db_connect.php';

// Only allow authenticated users
if (!isset($_SESSION['user_type'])) {
    header("HTTP/1.0 403 Forbidden");
    exit("Access denied");
}

$file_path = $_GET['file'] ?? '';
$application_id = $_GET['app_id'] ?? 0;

if (empty($file_path) || empty($application_id)) {
    header("HTTP/1.0 400 Bad Request");
    exit("Invalid request");
}

// Verify the file belongs to a valid application
$query = "SELECT a.*, s.title as scholarship_title FROM applications a 
          JOIN scholarships s ON a.scholarship_id = s.id 
          WHERE a.id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $application_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("HTTP/1.0 404 Not Found");
    exit("Application not found");
}

$application = mysqli_fetch_assoc($result);

// Check permissions
if ($_SESSION['user_type'] == 'student') {
    // Students can only view their own documents
    if ($application['student_id'] != $_SESSION['user_id']) {
        header("HTTP/1.0 403 Forbidden");
        exit("Access denied");
    }
} elseif ($_SESSION['user_type'] != 'admin' && $_SESSION['user_type'] != 'staff') {
    header("HTTP/1.0 403 Forbidden");
    exit("Access denied");
}

// Verify file exists and is within uploads directory
$full_path = realpath($file_path);
$uploads_dir = realpath('uploads/');

if (!$full_path || !$uploads_dir || strpos($full_path, $uploads_dir) !== 0) {
    header("HTTP/1.0 404 Not Found");
    exit("File not found");
}

if (!file_exists($full_path)) {
    header("HTTP/1.0 404 Not Found");
    exit("File not found");
}

// Verify file type
$file_info = pathinfo($full_path);
if (strtolower($file_info['extension']) !== 'pdf') {
    header("HTTP/1.0 403 Forbidden");
    exit("Invalid file type");
}

// Set headers for PDF display
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($full_path) . '"');
header('Content-Length: ' . filesize($full_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file
readfile($full_path);
exit();
?>
