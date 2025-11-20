<?php
session_start();
include 'db_connect.php';

// Only allow students
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: student_dashboard.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$scholarship_id = $_POST['scholarship_id'] ?? 0;

// Validate scholarship exists and is active
$scholarship_query = "SELECT * FROM scholarships WHERE id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $scholarship_query);
mysqli_stmt_bind_param($stmt, "i", $scholarship_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    $_SESSION['error_message'] = "Invalid scholarship.";
    header("Location: student_dashboard.php");
    exit();
}

$scholarship = mysqli_fetch_assoc($result);

// Check if already applied
$check_query = "SELECT * FROM applications WHERE student_id = ? AND scholarship_id = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "ii", $student_id, $scholarship_id);
mysqli_stmt_execute($stmt);
$check_result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($check_result) > 0) {
    $_SESSION['error_message'] = "You have already applied for this scholarship.";
    header("Location: student_dashboard.php");
    exit();
}

// Get and sanitize form data
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$student_number = trim($_POST['student_number'] ?? '');
$date_of_birth = $_POST['date_of_birth'] ?? '';
$program = trim($_POST['program'] ?? '');
$year_level = $_POST['year_level'] ?? '';
$address = trim($_POST['address'] ?? '');
$gwa = !empty($_POST['gwa']) ? floatval($_POST['gwa']) : null;

// Validate required fields
if (empty($full_name) || empty($email) || empty($student_number) || empty($date_of_birth) || 
    empty($program) || empty($year_level) || empty($address)) {
    $_SESSION['error_message'] = "All required fields must be filled.";
    header("Location: apply_scholarship.php?id=" . $scholarship_id);
    exit();
}

// Validate GWA if provided
if ($gwa !== null) {
    // Validate GWA is within allowed range 1.0 to 2.5, or 5.0 for fail
    if ($gwa < 1.0 || ($gwa > 2.5 && $gwa !== 5.0)) {
        $_SESSION['error_message'] = "GWA must be between 1.0 (highest) and 2.5 (lowest), or 5.0 (fail).";
        header("Location: apply_scholarship.php?id=" . $scholarship_id);
        exit();
    }

    // Validate GWA meets minimum requirement if scholarship has one
    if (!empty($scholarship['min_gwa'])) {
        // In this scale, LOWER is BETTER (1.0 is highest, 2.5 is lowest, 5.0 is fail)
        // So we check if student's GWA is GREATER than minimum (worse than required)
        if ($gwa > $scholarship['min_gwa']) {
            $_SESSION['error_message'] = "Your GWA (" . $gwa . ") does not meet the minimum requirement (" . $scholarship['min_gwa'] . " or better). Lower GWA is better in this scale.";
            header("Location: apply_scholarship.php?id=" . $scholarship_id);
            exit();
        }
    }
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error_message'] = "Invalid email format.";
    header("Location: apply_scholarship.php?id=" . $scholarship_id);
    exit();
}

// Handle file uploads
$uploaded_files = [];
if (isset($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
    $upload_dir = 'uploads/applications/' . $student_id . '_' . $scholarship_id . '/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $document_types = $_POST['document_types'] ?? [];
    
    for ($i = 0; $i < count($_FILES['documents']['name']); $i++) {
        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['documents']['name'][$i];
            $file_tmp = $_FILES['documents']['tmp_name'][$i];
            $file_size = $_FILES['documents']['size'][$i];
            $file_type = $_FILES['documents']['type'][$i];
            
            // Validate file type
            if ($file_type !== 'application/pdf') {
                $_SESSION['error_message'] = "All documents must be PDF files.";
                header("Location: apply_scholarship.php?id=" . $scholarship_id);
                exit();
            }
            
            // Validate file size (5MB limit)
            if ($file_size > 5 * 1024 * 1024) {
                $_SESSION['error_message'] = "File size must not exceed 5MB.";
                header("Location: apply_scholarship.php?id=" . $scholarship_id);
                exit();
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $safe_filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file_name, PATHINFO_FILENAME));
            $unique_filename = $safe_filename . '_' . time() . '_' . $i . '.' . $file_extension;
            $file_path = $upload_dir . $unique_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $file_path)) {
                $uploaded_files[] = [
                    'type' => $document_types[$i] ?? 'Unknown',
                    'filename' => $unique_filename,
                    'path' => $file_path,
                    'original_name' => $file_name
                ];
            } else {
                $_SESSION['error_message'] = "Failed to upload file: " . $file_name;
                header("Location: apply_scholarship.php?id=" . $scholarship_id);
                exit();
            }
        }
    }
}

// Prepare document file paths for database
$enrollment_certificate = null;
$good_moral_document = null;
$report_card = null;
$study_load_document = null;
$other_documents = [];

foreach ($uploaded_files as $file) {
    $file_path = $file['path'];
    $doc_type = strtolower($file['type']);
    
    if (strpos($doc_type, 'enrollment') !== false) {
        $enrollment_certificate = $file_path;
    } elseif (strpos($doc_type, 'good moral') !== false) {
        $good_moral_document = $file_path;
    } elseif (strpos($doc_type, 'report card') !== false || strpos($doc_type, 'grades') !== false) {
        $report_card = $file_path;
    } elseif (strpos($doc_type, 'study load') !== false) {
        $study_load_document = $file_path;
    } else {
        $other_documents[] = $file;
    }
}

$other_documents_json = !empty($other_documents) ? json_encode($other_documents) : null;

// Insert application
$status = 'pending';
$apply_query = "
    INSERT INTO applications
    (student_id, scholarship_id, full_name, email, date_of_birth, year_level, gwa, status, 
     enrollment_certificate, good_moral_document, report_card, study_load_document, other_documents)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($conn, $apply_query);
mysqli_stmt_bind_param(
    $stmt,
    "iissssdssssss",
    $student_id,
    $scholarship_id,
    $full_name,
    $email,
    $date_of_birth,
    $year_level,
    $gwa,
    $status,
    $enrollment_certificate,
    $good_moral_document,
    $report_card,
    $study_load_document,
    $other_documents_json
);

if (mysqli_stmt_execute($stmt)) {
    // Update student profile with latest information (only editable fields)
    $update_student = "
        UPDATE students 
        SET gwa = ?, address = ?, date_of_birth = ?
        WHERE id = ?
    ";
    $update_stmt = mysqli_prepare($conn, $update_student);
    mysqli_stmt_bind_param(
        $update_stmt,
        "dssi",
        $gwa,
        $address,
        $date_of_birth,
        $student_id
    );
    mysqli_stmt_execute($update_stmt);
    
    $_SESSION['success_message'] = "Successfully applied for the scholarship!";
    header("Location: student_dashboard.php");
    exit();
} else {
    // Clean up uploaded files if database insert fails
    foreach ($uploaded_files as $file) {
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
    }
    
    $_SESSION['error_message'] = "Error submitting application. Please try again.";
    header("Location: apply_scholarship.php?id=" . $scholarship_id);
    exit();
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>