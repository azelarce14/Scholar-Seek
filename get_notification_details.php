<?php
/**
 * Get Notification Details API
 * Returns detailed information about a specific notification
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

// Check if notification ID is provided
if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'No notification ID provided']);
    exit();
}

$notification_id = intval($_GET['id']);

// Get notification details
$query = "SELECT * FROM notifications WHERE id = ? AND user_id = ? AND user_type = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "iis", $notification_id, $user_id, $user_type);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['success' => false, 'error' => 'Notification not found']);
    exit();
}

$notification = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Prepare response data
$response = [
    'success' => true,
    'notification' => $notification,
    'details' => null
];

// Get additional details based on notification type and related data
if ($notification['related_type'] && $notification['related_id']) {
    switch ($notification['related_type']) {
        case 'application':
            // Get application and scholarship details
            $app_query = "
                SELECT a.*, s.title as scholarship_title, s.sponsor, s.amount, s.description, s.deadline,
                       s.min_gwa, a.rejection_reason
                FROM applications a 
                JOIN scholarships s ON a.scholarship_id = s.id 
                WHERE a.id = ? AND a.student_id = ?
            ";
            $app_stmt = mysqli_prepare($conn, $app_query);
            mysqli_stmt_bind_param($app_stmt, "ii", $notification['related_id'], $user_id);
            mysqli_stmt_execute($app_stmt);
            $app_result = mysqli_stmt_get_result($app_stmt);
            
            if ($app_result && mysqli_num_rows($app_result) > 0) {
                $response['details'] = mysqli_fetch_assoc($app_result);
            }
            mysqli_stmt_close($app_stmt);
            break;
            
        case 'scholarship':
            // Get scholarship details
            $scholarship_query = "SELECT * FROM scholarships WHERE id = ?";
            $scholarship_stmt = mysqli_prepare($conn, $scholarship_query);
            mysqli_stmt_bind_param($scholarship_stmt, "i", $notification['related_id']);
            mysqli_stmt_execute($scholarship_stmt);
            $scholarship_result = mysqli_stmt_get_result($scholarship_stmt);
            
            if ($scholarship_result && mysqli_num_rows($scholarship_result) > 0) {
                $response['details'] = mysqli_fetch_assoc($scholarship_result);
            }
            mysqli_stmt_close($scholarship_stmt);
            break;
    }
}

// Generate formatted content based on notification type
$response['formatted_content'] = generateNotificationContent($notification, $response['details']);

echo json_encode($response);

/**
 * Generate formatted notification content
 */
function generateNotificationContent($notification, $details) {
    $content = '';
    
    switch ($notification['type']) {
        case 'application_approved':
            if ($details) {
                $content = "
                    <div class='notification-success'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-check-circle'></i>
                        </div>
                        <h4>🎉 Congratulations!</h4>
                        <p>Your application has been <strong>approved</strong>!</p>
                        
                        <div class='scholarship-details'>
                            <h5>📚 Scholarship Details:</h5>
                            <div class='detail-grid'>
                                <div class='detail-item'>
                                    <strong>Title:</strong> {$details['scholarship_title']}
                                </div>
                                <div class='detail-item'>
                                    <strong>Sponsor:</strong> {$details['sponsor']}
                                </div>
                                <div class='detail-item'>
                                    <strong>Amount:</strong> $" . number_format($details['amount']) . "
                                </div>
                                <div class='detail-item'>
                                    <strong>Applied:</strong> " . date('M j, Y', strtotime($details['application_date'])) . "
                                </div>
                            </div>
                            
                            <div class='scholarship-description'>
                                <h6>Description:</h6>
                                <p>{$details['description']}</p>
                            </div>
                            
                            <div class='next-steps'>
                                <h6>🎯 Next Steps:</h6>
                                <ul>
                                    <li>Check your email for further instructions</li>
                                    <li>Prepare required documentation</li>
                                    <li>Contact the scholarship office if needed</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                ";
            } else {
                $content = "
                    <div class='notification-success'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-check-circle'></i>
                        </div>
                        <h4>🎉 Application Approved!</h4>
                        <p>{$notification['message']}</p>
                    </div>
                ";
            }
            break;
            
        case 'application_rejected':
            if ($details && !empty($details['rejection_reason'])) {
                // Parse rejection reason to extract main reason and additional notes
                $rejection_text = $details['rejection_reason'];
                
                // Extract additional notes if present
                $additional_notes = '';
                if (strpos($rejection_text, 'Additional Notes:') !== false) {
                    $reason_parts = explode('Additional Notes:', $rejection_text, 2);
                    $specific_reason = trim($reason_parts[0]);
                    $additional_notes = trim($reason_parts[1] ?? '');
                } else {
                    $specific_reason = $rejection_text;
                }
                
                $review_date = date('M j, Y', strtotime($details['review_date'] ?? $notification['created_at']));
                
                $content = "
                    <div class='notification-warning'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-clipboard-check'></i>
                        </div>
                        <h4>📋 Application Evaluation Results</h4>
                        <p>Your application for <strong>{$details['scholarship_title']}</strong> has been reviewed.</p>
                        
                        <div class='evaluation-summary'>
                            <div class='review-info'>
                                <span class='review-date'><i class='fas fa-calendar'></i> Reviewed on {$review_date}</span>
                            </div>
                        </div>
                        
                        <div class='evaluation-checklist'>
                            <h5>📊 Evaluation Checklist</h5>
                            <div class='checklist-container'>
                                " . generateEvaluationChecklist($specific_reason) . "
                            </div>
                        </div>
                        
                        " . ($additional_notes ? "
                        <div class='additional-feedback'>
                            <h5>💬 Additional Feedback</h5>
                            <div class='feedback-content'>
                                {$additional_notes}
                            </div>
                        </div>
                        " : "") . "
                        
                        <div class='improvement-guide'>
                            <h5>🎯 Next Steps & Improvement Tips</h5>
                            " . generateImprovementSuggestions($specific_reason) . "
                        </div>
                        
                        <div class='encouragement-footer'>
                            <p><strong>💪 Keep Going!</strong> Every application is a learning opportunity. Use this feedback to strengthen your next application!</p>
                        </div>
                    </div>
                ";
            } else {
                $content = "
                    <div class='notification-warning'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-times-circle'></i>
                        </div>
                        <h4>Application Update</h4>
                        <p>{$notification['message']}</p>
                        <p><em>Keep applying for other opportunities!</em></p>
                    </div>
                ";
            }
            break;
            
        case 'application_pending':
            if ($details) {
                $content = "
                    <div class='notification-info'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-clock'></i>
                        </div>
                        <h4>⏳ Application Under Review</h4>
                        <p>Your application for <strong>{$details['scholarship_title']}</strong> is being reviewed.</p>
                        
                        <div class='application-timeline'>
                            <h5>📅 Timeline:</h5>
                            <div class='detail-grid'>
                                <div class='detail-item'>
                                    <strong>Submitted:</strong> " . date('M j, Y', strtotime($details['application_date'])) . "
                                </div>
                                <div class='detail-item'>
                                    <strong>Deadline:</strong> " . date('M j, Y', strtotime($details['deadline'])) . "
                                </div>
                                <div class='detail-item'>
                                    <strong>Status:</strong> Under Review
                                </div>
                            </div>
                        </div>
                        
                        <div class='patience-note'>
                            <p><em>Please be patient while we review your application. You'll be notified once a decision is made.</em></p>
                        </div>
                    </div>
                ";
            } else {
                $content = "
                    <div class='notification-info'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-clock'></i>
                        </div>
                        <h4>⏳ Application Under Review</h4>
                        <p>{$notification['message']}</p>
                    </div>
                ";
            }
            break;
            
        case 'scholarship_deadline':
            if ($details) {
                $deadline_date = date('M j, Y', strtotime($details['deadline']));
                $days_left = ceil((strtotime($details['deadline']) - time()) / (60 * 60 * 24));
                
                $content = "
                    <div class='notification-warning'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-calendar-alt'></i>
                        </div>
                        <h4>⏰ Deadline Approaching</h4>
                        <p>Don't miss out on this opportunity!</p>
                        
                        <div class='deadline-details'>
                            <div class='scholarship-info'>
                                <h5>{$details['title']}</h5>
                                <p><strong>Sponsor:</strong> {$details['sponsor']}</p>
                                <p><strong>Amount:</strong> $" . number_format($details['amount']) . "</p>
                            </div>
                            
                            <div class='deadline-urgency'>
                                <div class='deadline-date'>
                                    <strong>Deadline: {$deadline_date}</strong>
                                </div>
                                <div class='days-left'>
                                    " . ($days_left > 0 ? "{$days_left} days remaining" : "Deadline passed") . "
                                </div>
                            </div>
                        </div>
                        
                        <div class='action-reminder'>
                            <p><strong>🚀 Apply now to secure your future!</strong></p>
                        </div>
                    </div>
                ";
            } else {
                $content = "
                    <div class='notification-warning'>
                        <div class='notification-icon-large'>
                            <i class='fas fa-calendar-alt'></i>
                        </div>
                        <h4>⏰ Deadline Reminder</h4>
                        <p>{$notification['message']}</p>
                    </div>
                ";
            }
            break;
            
        case 'general':
        default:
            $content = "
                <div class='notification-general'>
                    <div class='notification-icon-large'>
                        <i class='fas fa-info-circle'></i>
                    </div>
                    <h4>{$notification['title']}</h4>
                    <p>{$notification['message']}</p>
                </div>
            ";
            break;
    }
    
    return $content;
}

/**
 * Generate evaluation checklist based on rejection reason
 */
function generateEvaluationChecklist($specific_reason) {
    $checklist_items = [
        // Academic criteria
        'Academic Requirements' => [
            ['item' => 'Minimum GWA Requirement', 'status' => 'pass', 'icon' => 'fa-graduation-cap'],
            ['item' => 'Complete Academic Records', 'status' => 'pass', 'icon' => 'fa-file-alt'],
            ['item' => 'No Failing Grades', 'status' => 'pass', 'icon' => 'fa-check-circle'],
            ['item' => 'Academic Standing', 'status' => 'pass', 'icon' => 'fa-award']
        ],
        // Eligibility criteria
        'Eligibility Criteria' => [
            ['item' => 'Program Compatibility', 'status' => 'pass', 'icon' => 'fa-university'],
            ['item' => 'Year Level Requirements', 'status' => 'pass', 'icon' => 'fa-layer-group'],
            ['item' => 'Age Requirements', 'status' => 'pass', 'icon' => 'fa-calendar-alt'],
            ['item' => 'Residency Status', 'status' => 'pass', 'icon' => 'fa-home']
        ],
        // Documentation
        'Documentation Issues' => [
            ['item' => 'Required Documents Submitted', 'status' => 'pass', 'icon' => 'fa-folder-open'],
            ['item' => 'Document Validity', 'status' => 'pass', 'icon' => 'fa-certificate'],
            ['item' => 'Document Quality', 'status' => 'pass', 'icon' => 'fa-image'],
            ['item' => 'Submission Deadline', 'status' => 'pass', 'icon' => 'fa-clock']
        ],
        // Financial
        'Financial Assessment' => [
            ['item' => 'Income Requirements', 'status' => 'pass', 'icon' => 'fa-money-bill-wave'],
            ['item' => 'Financial Need Assessment', 'status' => 'pass', 'icon' => 'fa-chart-line'],
            ['item' => 'Financial Documents', 'status' => 'pass', 'icon' => 'fa-receipt'],
            ['item' => 'Family Financial Status', 'status' => 'pass', 'icon' => 'fa-users']
        ],
        // Application quality
        'Application Quality' => [
            ['item' => 'Complete Application Form', 'status' => 'pass', 'icon' => 'fa-edit'],
            ['item' => 'Essay/Personal Statement', 'status' => 'pass', 'icon' => 'fa-pen'],
            ['item' => 'Recommendation Letters', 'status' => 'pass', 'icon' => 'fa-envelope'],
            ['item' => 'Application Accuracy', 'status' => 'pass', 'icon' => 'fa-spell-check']
        ]
    ];
    
    // Determine which item failed based on specific reason
    $failed_item = determineFailedCriteria($specific_reason);
    
    // Determine category based on the specific reason and get appropriate checklist
    $category = determineCategoryFromReason($specific_reason);
    $items = $checklist_items[$category] ?? $checklist_items['Academic Requirements'];
    
    $html = '';
    foreach ($items as $index => $item) {
        $status = ($item['item'] === $failed_item || strpos(strtolower($specific_reason), strtolower($item['item'])) !== false) ? 'fail' : 'pass';
        $icon_class = $status === 'pass' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
        $status_class = $status === 'pass' ? 'checklist-pass' : 'checklist-fail';
        
        $html .= "
            <div class='checklist-item {$status_class}'>
                <div class='checklist-icon'>
                    <i class='fas {$icon_class}'></i>
                </div>
                <div class='checklist-content'>
                    <div class='checklist-title'>
                        <i class='fas {$item['icon']}'></i>
                        {$item['item']}
                    </div>
                    <div class='checklist-status'>
                        " . ($status === 'pass' ? 
                            "<span class='status-pass'>✓ Met requirement</span>" : 
                            "<span class='status-fail'>✗ Did not meet requirement</span>"
                        ) . "
                    </div>
                </div>
            </div>
        ";
    }
    
    return $html;
}

/**
 * Determine which criteria failed based on rejection reason
 */
function determineFailedCriteria($reason) {
    $reason_lower = strtolower($reason);
    
    $criteria_map = [
        'gwa' => 'Minimum GWA Requirement',
        'grade' => 'Complete Academic Records',
        'failing' => 'No Failing Grades',
        'probation' => 'Academic Standing',
        'program' => 'Program Compatibility',
        'year level' => 'Year Level Requirements',
        'age' => 'Age Requirements',
        'residency' => 'Residency Status',
        'document' => 'Required Documents Submitted',
        'invalid' => 'Document Validity',
        'quality' => 'Document Quality',
        'deadline' => 'Submission Deadline',
        'income' => 'Income Requirements',
        'financial need' => 'Financial Need Assessment',
        'financial' => 'Financial Documents',
        'incomplete' => 'Complete Application Form',
        'essay' => 'Essay/Personal Statement',
        'recommendation' => 'Recommendation Letters',
        'error' => 'Application Accuracy'
    ];
    
    foreach ($criteria_map as $keyword => $criteria) {
        if (strpos($reason_lower, $keyword) !== false) {
            return $criteria;
        }
    }
    
    return 'Application Requirements';
}

/**
 * Determine category from rejection reason
 */
function determineCategoryFromReason($reason) {
    $reason_lower = strtolower($reason);
    
    $category_keywords = [
        'Academic Requirements' => ['gwa', 'grade', 'failing', 'probation', 'academic', 'units'],
        'Eligibility Criteria' => ['program', 'year level', 'age', 'residency', 'scholarship'],
        'Documentation Issues' => ['document', 'invalid', 'quality', 'deadline', 'submission'],
        'Financial Assessment' => ['income', 'financial', 'need', 'family'],
        'Application Quality' => ['incomplete', 'essay', 'recommendation', 'error', 'application']
    ];
    
    foreach ($category_keywords as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($reason_lower, $keyword) !== false) {
                return $category;
            }
        }
    }
    
    return 'Academic Requirements'; // Default category
}

/**
 * Generate improvement suggestions based on rejection reason
 */
function generateImprovementSuggestions($specific_reason) {
    $category = determineCategoryFromReason($specific_reason);
    $suggestions = [
        'Academic Requirements' => [
            'Improve your GWA by focusing on your studies',
            'Seek academic support or tutoring if needed',
            'Maintain consistent academic performance',
            'Consider retaking courses to improve grades'
        ],
        'Eligibility Criteria' => [
            'Check program-specific scholarships that match your field',
            'Wait until you meet the year level requirements',
            'Look for scholarships with different eligibility criteria',
            'Verify residency requirements for future applications'
        ],
        'Documentation Issues' => [
            'Ensure all required documents are complete and valid',
            'Submit high-quality, legible copies of documents',
            'Get proper certifications and signatures',
            'Apply well before deadlines to avoid last-minute issues'
        ],
        'Financial Assessment' => [
            'Provide complete and accurate financial information',
            'Include all required financial supporting documents',
            'Consider scholarships with different income brackets',
            'Seek financial counseling if needed'
        ],
        'Application Quality' => [
            'Take time to carefully complete all application sections',
            'Have someone review your essay before submission',
            'Request strong recommendation letters early',
            'Double-check all information for accuracy'
        ]
    ];
    
    $category_suggestions = $suggestions[$category] ?? $suggestions['Academic Requirements'];
    
    $html = '<div class="improvement-list">';
    foreach ($category_suggestions as $suggestion) {
        $html .= "
            <div class='improvement-item'>
                <div class='improvement-icon'>
                    <i class='fas fa-lightbulb'></i>
                </div>
                <div class='improvement-text'>
                    {$suggestion}
                </div>
            </div>
        ";
    }
    $html .= '</div>';
    
    // Add general encouragement
    $html .= "
        <div class='next-steps'>
            <h6>📅 Recommended Next Steps:</h6>
            <div class='steps-list'>
                <div class='step-item'>
                    <span class='step-number'>1</span>
                    <span class='step-text'>Review the feedback above carefully</span>
                </div>
                <div class='step-item'>
                    <span class='step-number'>2</span>
                    <span class='step-text'>Work on improving the identified areas</span>
                </div>
                <div class='step-item'>
                    <span class='step-number'>3</span>
                    <span class='step-text'>Apply for other suitable scholarships</span>
                </div>
                <div class='step-item'>
                    <span class='step-number'>4</span>
                    <span class='step-text'>Reapply when requirements are met</span>
                </div>
            </div>
        </div>
    ";
    
    return $html;
}

mysqli_close($conn);
?>
