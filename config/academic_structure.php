<?php
// ============================================================================
// ACADEMIC STRUCTURE CONFIGURATION
// ============================================================================
// Define the complete academic structure
$academic_structure = [
    'School of Arts and Sciences' => [
        'Bachelor of Arts in Communication',
        'Bachelor of Arts in Economics',
        'Bachelor of Science in Business Administration'
    ],
    'School of Criminal Justice Education' => [
        'Bachelor of Science in Criminology'
    ],
    'School of Management and Entrepreneurship' => [
        'Bachelor of Science in Tourism Management',
        'Bachelor of Science in Hospitality Management'
    ],
    'School of Nursing and Health Sciences' => [
        'Bachelor of Science in Nursing'
    ],
    'School of Engineering' => [
        'Bachelor of Science in Civil Engineering',
        'Bachelor of Science in Electrical Engineering',
        'Bachelor of Science in Mechanical Engineering',
        'Bachelor of Science in Computer Engineering'
    ],
    'School of Technology and Computer Studies' => [
        'Bachelor of Science in Industrial Technology',
        'Bachelor of Science in Computer Science',
        'Bachelor of Science in Information System'
    ],
    'School of Teacher Education' => [
        'Bachelor of Elementary Education',
        'Bachelor of Secondary Education',
        'Bachelor of Physical Education',
        'Bachelor of Early Childhood Education',
        'Bachelor of Special Needs Education',
        'Bachelor of Technology and Livelihood Education'
    ]
];

// Extract departments (schools) in order
$departments = array_keys($academic_structure);

// Extract all programs in order
$programs = [];
foreach ($academic_structure as $school => $school_programs) {
    $programs = array_merge($programs, $school_programs);
}

// Year levels
$year_levels = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

/**
 * Get programs for a specific department/school
 * @param string $department The department/school name
 * @return array Array of programs for the department
 */
function getProgramsByDepartment($department) {
    global $academic_structure;
    return $academic_structure[$department] ?? [];
}

/**
 * Get department for a specific program
 * @param string $program The program name
 * @return string|null The department name or null if not found
 */
function getDepartmentByProgram($program) {
    global $academic_structure;
    foreach ($academic_structure as $department => $programs) {
        if (in_array($program, $programs)) {
            return $department;
        }
    }
    return null;
}

/**
 * Validate if a department exists
 * @param string $department The department name
 * @return bool True if valid, false otherwise
 */
function isValidDepartment($department) {
    global $academic_structure;
    return array_key_exists($department, $academic_structure);
}

/**
 * Validate if a program exists
 * @param string $program The program name
 * @return bool True if valid, false otherwise
 */
function isValidProgram($program) {
    global $programs;
    return in_array($program, $programs);
}

/**
 * Validate if a department-program combination is valid
 * @param string $department The department name
 * @param string $program The program name
 * @return bool True if valid combination, false otherwise
 */
function isValidDepartmentProgramCombination($department, $program) {
    global $academic_structure;
    if (!isset($academic_structure[$department])) {
        return false;
    }
    return in_array($program, $academic_structure[$department]);
}

/**
 * Validate if a year level exists
 * @param string $year_level The year level
 * @return bool True if valid, false otherwise
 */
function isValidYearLevel($year_level) {
    global $year_levels;
    return in_array($year_level, $year_levels);
}

/**
 * Get all departments as options for select elements
 * @return array Associative array with department as both key and value
 */
function getDepartmentOptions() {
    global $departments;
    $options = [];
    foreach ($departments as $department) {
        $options[$department] = $department;
    }
    return $options;
}

/**
 * Get all programs as options for select elements
 * @return array Associative array with program as both key and value
 */
function getProgramOptions() {
    global $programs;
    $options = [];
    foreach ($programs as $program) {
        $options[$program] = $program;
    }
    return $options;
}

/**
 * Get year levels as options for select elements
 * @return array Associative array with year level as both key and value
 */
function getYearLevelOptions() {
    global $year_levels;
    $options = [];
    foreach ($year_levels as $year_level) {
        $options[$year_level] = $year_level;
    }
    return $options;
}

/**
 * Get academic structure statistics
 * @return array Statistics about the academic structure
 */
function getAcademicStats() {
    global $academic_structure, $programs, $departments;
    
    $stats = [
        'total_schools' => count($departments),
        'total_programs' => count($programs),
        'programs_per_school' => []
    ];
    
    foreach ($academic_structure as $school => $school_programs) {
        $stats['programs_per_school'][$school] = count($school_programs);
    }
    
    return $stats;
}

/**
 * Generate HTML options for department select
 * @param string $selected_department Currently selected department
 * @return string HTML option elements
 */
function generateDepartmentOptions($selected_department = '') {
    global $departments;
    $html = '<option value="">Select Department</option>';
    foreach ($departments as $department) {
        $selected = ($department === $selected_department) ? 'selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($department) . "\" $selected>" . htmlspecialchars($department) . "</option>";
    }
    return $html;
}

/**
 * Generate HTML options for program select
 * @param string $selected_program Currently selected program
 * @param string $department Filter by department (optional)
 * @return string HTML option elements
 */
function generateProgramOptions($selected_program = '', $department = '') {
    global $academic_structure, $programs;
    
    $html = '<option value="">Select Program</option>';
    
    if ($department && isset($academic_structure[$department])) {
        // Show only programs for the selected department
        $available_programs = $academic_structure[$department];
    } else {
        // Show all programs
        $available_programs = $programs;
    }
    
    foreach ($available_programs as $program) {
        $selected = ($program === $selected_program) ? 'selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($program) . "\" $selected>" . htmlspecialchars($program) . "</option>";
    }
    return $html;
}

/**
 * Generate HTML options for year level select
 * @param string $selected_year_level Currently selected year level
 * @return string HTML option elements
 */
function generateYearLevelOptions($selected_year_level = '') {
    global $year_levels;
    $html = '<option value="">Select Year Level</option>';
    foreach ($year_levels as $year_level) {
        $selected = ($year_level === $selected_year_level) ? 'selected' : '';
        $html .= "<option value=\"" . htmlspecialchars($year_level) . "\" $selected>" . htmlspecialchars($year_level) . "</option>";
    }
    return $html;
}

/**
 * Get programs for JavaScript/AJAX usage
 * @return string JSON encoded programs by department
 */
function getProgramsForJS() {
    global $academic_structure;
    return json_encode($academic_structure);
}
?>
