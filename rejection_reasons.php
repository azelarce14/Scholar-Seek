<?php
/**
 * Predefined Rejection Reasons for Scholarship Applications
 */

class RejectionReasons {
    
    public static function getAllReasons() {
        return [
            'academic' => [
                'title' => 'Academic Requirements',
                'reasons' => [
                    'gwa_below_requirement' => 'GWA does not meet the minimum requirement',
                    'incomplete_grades' => 'Incomplete or missing academic records',
                    'failed_subjects' => 'Has failing grades in major subjects'
                ]
            ],
            'eligibility' => [
                'title' => 'Eligibility Criteria',
                'reasons' => [
                    'program_mismatch' => 'Program does not match scholarship requirements',
                    'already_has_scholarship' => 'Already receiving another scholarship'
                ]
            ],
            'documentation' => [
                'title' => 'Documentation Issues',
                'reasons' => [
                    'incomplete_documents' => 'Missing required documents',
                    'invalid_documents' => 'Invalid or expired documents'
                ]
            ],
            'financial' => [
                'title' => 'Financial Assessment',
                'reasons' => [
                    'income_exceeds_limit' => 'Family income exceeds scholarship limit',
                    'insufficient_financial_need' => 'Does not demonstrate sufficient financial need'
                ]
            ],
            'application' => [
                'title' => 'Application Quality',
                'reasons' => [
                    'incomplete_application' => 'Application form is incomplete'
                ]
            ],
            'competitive' => [
                'title' => 'Competitive Selection',
                'reasons' => [
                    'limited_slots' => 'Limited slots available, other applicants were more qualified',
                    'quota_filled' => 'Scholarship quota has been filled'
                ]
            ],
            'other' => [
                'title' => 'Other Reasons',
                'reasons' => [
                    'custom_reason' => 'Other reason (please specify)'
                ]
            ]
        ];
    }
    
    public static function getReasonText($reason_key) {
        $all_reasons = self::getAllReasons();
        
        foreach ($all_reasons as $category) {
            if (isset($category['reasons'][$reason_key])) {
                return $category['reasons'][$reason_key];
            }
        }
        
        return $reason_key; // Return the key if not found
    }
    
    public static function getCategoryTitle($category_key) {
        $all_reasons = self::getAllReasons();
        return $all_reasons[$category_key]['title'] ?? $category_key;
    }
}
?>
