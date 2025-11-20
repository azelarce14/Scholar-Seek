<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

$student_id = $_SESSION['user_id'];
$response = ['success' => false, 'error' => ''];

try {
    // Handle different types of profile updates
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_basic_info':
            $response = updateBasicInfo($conn, $student_id, $_POST);
            break;
            
        case 'change_password':
            $response = changePassword($conn, $student_id, $_POST);
            break;
            
        case 'upload_profile_picture':
            $response = uploadProfilePicture($conn, $student_id, $_FILES);
            break;
            
        case 'update_contact_info':
            $response = updateContactInfo($conn, $student_id, $_POST);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
    
} catch (Exception $e) {
    error_log("Profile Update Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'An error occurred while updating your profile'];
}

header('Content-Type: application/json');
echo json_encode($response);

/**
 * Update basic student information
 */
function updateBasicInfo($conn, $student_id, $data) {
    $fullname = trim($data['fullname'] ?? '');
    $student_number = trim($data['student_number'] ?? '');
    $program = trim($data['program'] ?? '');
    $department = trim($data['department'] ?? '');
    $year_level = trim($data['year_level'] ?? '');
    $gwa = trim($data['gwa'] ?? '');
    
    // Validation
    if (empty($fullname)) {
        return ['success' => false, 'error' => 'Full name is required'];
    }
    
    if (empty($student_number)) {
        return ['success' => false, 'error' => 'Student number is required'];
    }
    
    // Check if student number is already taken by another student
    $check_query = "SELECT id FROM students WHERE student_number = ? AND id != ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, "si", $student_number, $student_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if (mysqli_num_rows($check_result) > 0) {
        mysqli_stmt_close($check_stmt);
        return ['success' => false, 'error' => 'Student number is already taken'];
    }
    mysqli_stmt_close($check_stmt);
    
    // Update student information
    $update_query = "UPDATE students SET fullname = ?, student_number = ?, program = ?, department = ?, year_level = ?, gwa = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "ssssssi", $fullname, $student_number, $program, $department, $year_level, $gwa, $student_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        
        // Update session data
        $_SESSION['user_name'] = $fullname;
        
        return ['success' => true, 'message' => 'Profile updated successfully'];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Failed to update profile'];
    }
}

/**
 * Change student password
 */
function changePassword($conn, $student_id, $data) {
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        return ['success' => false, 'error' => 'All password fields are required'];
    }
    
    if ($new_password !== $confirm_password) {
        return ['success' => false, 'error' => 'New passwords do not match'];
    }
    
    if (strlen($new_password) < 6) {
        return ['success' => false, 'error' => 'New password must be at least 6 characters long'];
    }

    // Prevent using the same password again
    if ($current_password === $new_password) {
        return ['success' => false, 'error' => 'New password must be different from your current password'];
    }
    
    // Get current password hash
    $get_password_query = "SELECT password FROM students WHERE id = ?";
    $stmt = mysqli_prepare($conn, $get_password_query);
    mysqli_stmt_bind_param($stmt, "i", $student_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Verify current password
        if (!password_verify($current_password, $row['password'])) {
            mysqli_stmt_close($stmt);
            return ['success' => false, 'error' => 'Current password is incorrect'];
        }
        
        mysqli_stmt_close($stmt);
        
        // Hash new password and update
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE students SET password = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $new_password_hash, $student_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            return ['success' => true, 'message' => 'Password changed successfully'];
        } else {
            mysqli_stmt_close($update_stmt);
            return ['success' => false, 'error' => 'Failed to update password'];
        }
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Student not found'];
    }
}

/**
 * Upload profile picture
 */
function uploadProfilePicture($conn, $student_id, $files) {
    if (!isset($files['profile_picture']) || $files['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error occurred'];
    }
    
    $file = $files['profile_picture'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed'];
    }
    
    // Validate file size
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File size too large. Maximum size is 5MB'];
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/profile_pictures/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Check if profile_picture column exists, if not add it
        $check_column_query = "SHOW COLUMNS FROM students LIKE 'profile_picture'";
        $column_result = mysqli_query($conn, $check_column_query);
        
        if (mysqli_num_rows($column_result) == 0) {
            // Add profile_picture column
            $add_column_query = "ALTER TABLE students ADD COLUMN profile_picture VARCHAR(255) NULL";
            mysqli_query($conn, $add_column_query);
        }
        
        // Get old profile picture to delete it
        $get_old_pic_query = "SELECT profile_picture FROM students WHERE id = ?";
        $stmt = mysqli_prepare($conn, $get_old_pic_query);
        mysqli_stmt_bind_param($stmt, "i", $student_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $old_pic = null;
        
        if ($row = mysqli_fetch_assoc($result)) {
            $old_pic = $row['profile_picture'];
        }
        mysqli_stmt_close($stmt);
        
        // Update database with new profile picture path
        $update_query = "UPDATE students SET profile_picture = ? WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $file_path, $student_id);
        
        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);
            
            // Delete old profile picture if it exists
            if ($old_pic && file_exists($old_pic)) {
                unlink($old_pic);
            }
            
            return ['success' => true, 'message' => 'Profile picture updated successfully', 'file_path' => $file_path];
        } else {
            mysqli_stmt_close($update_stmt);
            // Delete uploaded file if database update failed
            unlink($file_path);
            return ['success' => false, 'error' => 'Failed to update profile picture in database'];
        }
    } else {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
}

/**
 * Update contact information
 */
function updateContactInfo($conn, $student_id, $data) {
    $phone = trim($data['phone'] ?? '');
    $address = trim($data['address'] ?? '');
    $date_of_birth = trim($data['date_of_birth'] ?? '');
    
    // Check if columns exist, if not add them
    $columns_to_check = [
        'phone' => 'VARCHAR(20)',
        'address' => 'TEXT',
        'date_of_birth' => 'DATE'
    ];
    
    foreach ($columns_to_check as $column => $type) {
        $check_query = "SHOW COLUMNS FROM students LIKE '$column'";
        $result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($result) == 0) {
            $add_query = "ALTER TABLE students ADD COLUMN $column $type NULL";
            mysqli_query($conn, $add_query);
        }
    }
    
    // Update contact information
    $update_query = "UPDATE students SET phone = ?, address = ?, date_of_birth = ? WHERE id = ?";
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "sssi", $phone, $address, $date_of_birth, $student_id);
    
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Contact information updated successfully'];
    } else {
        mysqli_stmt_close($stmt);
        return ['success' => false, 'error' => 'Failed to update contact information'];
    }
}
?>
