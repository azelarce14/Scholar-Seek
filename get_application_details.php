<?php
session_start();
require_once 'db_connect.php';

// Only allow admin and staff
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'staff'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit();
}

// Check if application ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid application ID']);
    exit();
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

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $application_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Application not found']);
    exit();
}

$application = mysqli_fetch_assoc($result);

// Get uploaded documents from database and file system
$documents = [];

// Debug information
$debug_info = [
    'db_documents' => [],
    'file_system_check' => []
];

// First, check documents stored in database columns
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
        
        $debug_info['db_documents'][] = $column . ': ' . $file_path;
    }
}

// Check for other documents stored as JSON
if (!empty($application['other_documents'])) {
    $other_docs = json_decode($application['other_documents'], true);
    if (is_array($other_docs)) {
        foreach ($other_docs as $doc) {
            if (isset($doc['path']) && file_exists($doc['path'])) {
                $file_path = $doc['path'];
                $filename = basename($file_path);
                $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                $documents[] = [
                    'filename' => $filename,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'type' => $doc['type'] ?? guessDocumentType($filename),
                    'extension' => $file_extension,
                    'source' => 'database_other'
                ];
                
                $debug_info['db_documents'][] = 'other: ' . $file_path;
            }
        }
    }
}

// Also check file system as fallback
$upload_dir = 'uploads/applications/' . $application['student_id'] . '_' . $application['scholarship_id'] . '/';
$debug_info['file_system_check'] = [
    'upload_dir' => $upload_dir,
    'dir_exists' => is_dir($upload_dir),
    'files_found' => []
];

if (is_dir($upload_dir)) {
    $files = scandir($upload_dir);
    $debug_info['file_system_check']['all_files'] = $files;
    
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $file_path = $upload_dir . $file;
            $file_extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            
            // Only add if not already found in database
            $already_exists = false;
            foreach ($documents as $existing_doc) {
                if ($existing_doc['path'] === $file_path) {
                    $already_exists = true;
                    break;
                }
            }
            
            if (!$already_exists && in_array($file_extension, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                $documents[] = [
                    'filename' => $file,
                    'path' => $file_path,
                    'size' => filesize($file_path),
                    'modified' => filemtime($file_path),
                    'type' => guessDocumentType($file),
                    'extension' => $file_extension,
                    'source' => 'file_system'
                ];
                $debug_info['file_system_check']['files_found'][] = $file;
            }
        }
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

function guessDocumentType($filename) {
    $filename_lower = strtolower($filename);
    
    if (strpos($filename_lower, 'enrollment') !== false) {
        return 'Certificate of Enrollment';
    } elseif (strpos($filename_lower, 'moral') !== false) {
        return 'Certificate of Good Moral Character';
    } elseif (strpos($filename_lower, 'grade') !== false || strpos($filename_lower, 'report') !== false) {
        return 'Report Card (Grades)';
    } elseif (strpos($filename_lower, 'study') !== false || strpos($filename_lower, 'load') !== false) {
        return 'Study Load';
    } elseif (strpos($filename_lower, 'transcript') !== false) {
        return 'Official Transcript of Records';
    } elseif (strpos($filename_lower, 'recommendation') !== false) {
        return 'Letter of Recommendation';
    } else {
        return 'Other Document';
    }
}
?>
