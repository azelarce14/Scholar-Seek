<?php
require_once 'rejection_reasons.php';
$rejection_reasons = RejectionReasons::getAllReasons();
?>

<!-- Rejection Modal -->
<div id="rejectionModal" class="modal">
    <div class="modal-content rejection-modal">
        <div class="modal-header">
            <h3><i class="fas fa-times-circle"></i> Reject Application</h3>
            <span class="close" id="rejectionModalClose">&times;</span>
        </div>
        <div class="modal-body">
            <form id="rejectionForm" method="POST" action="manage_applications.php">
                <input type="hidden" name="reject_application" value="1">
                <input type="hidden" name="application_id" id="rejectionApplicationId">
                
                <div class="rejection-info">
                    <div class="student-info">
                        <h4 id="rejectionStudentName">Student Name</h4>
                        <p id="rejectionScholarshipTitle">Scholarship Title</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="rejection_reason">
                        <i class="fas fa-exclamation-triangle"></i> Rejection Reason
                    </label>
                    <select id="rejection_reason" name="rejection_reason" required>
                        <option value="">Select a reason...</option>
                        <?php 
                        // Flatten all reasons into a single list
                        foreach ($rejection_reasons as $category_key => $category): 
                            foreach ($category['reasons'] as $reason_key => $reason_text):
                        ?>
                            <option value="<?php echo $reason_key; ?>" data-category="<?php echo $category_key; ?>">
                                <?php echo htmlspecialchars($reason_text); ?>
                            </option>
                        <?php 
                            endforeach;
                        endforeach; 
                        ?>
                    </select>
                </div>
                
                <div class="form-group" id="customReasonGroup" style="display: none;">
                    <label for="custom_reason">
                        <i class="fas fa-edit"></i> Custom Reason
                    </label>
                    <textarea id="custom_reason" name="custom_reason" rows="3" 
                              placeholder="Please provide a detailed explanation..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="additional_notes">
                        <i class="fas fa-sticky-note"></i> Additional Notes (Optional)
                    </label>
                    <textarea id="additional_notes" name="additional_notes" rows="2" 
                              placeholder="Any additional feedback or suggestions for the student..."></textarea>
                </div>

                
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="cancelRejection">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" form="rejectionForm" class="btn-danger">
                <i class="fas fa-ban"></i> Reject Application
            </button>
        </div>
    </div>
</div>

<script>
// Handle reason selection (show/hide custom reason field)
document.getElementById('rejection_reason').addEventListener('change', function() {
    const customReasonGroup = document.getElementById('customReasonGroup');
    const customReason = document.getElementById('custom_reason');
    
    if (this.value === 'custom_reason') {
        customReasonGroup.style.display = 'block';
        customReason.required = true;
    } else {
        customReasonGroup.style.display = 'none';
        customReason.required = false;
        customReason.value = '';
    }
});

// Modal functions
function showRejectionModal(applicationId, studentName, scholarshipTitle) {
    document.getElementById('rejectionApplicationId').value = applicationId;
    document.getElementById('rejectionStudentName').textContent = studentName;
    document.getElementById('rejectionScholarshipTitle').textContent = scholarshipTitle;
    
    // Reset form
    document.getElementById('rejectionForm').reset();
    document.getElementById('customReasonGroup').style.display = 'none';
    document.getElementById('custom_reason').required = false;
    
    document.getElementById('rejectionModal').style.display = 'block';
}

function hideRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
}

// Event listeners
document.getElementById('rejectionModalClose').addEventListener('click', hideRejectionModal);
document.getElementById('cancelRejection').addEventListener('click', hideRejectionModal);

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('rejectionModal');
    if (event.target === modal) {
        hideRejectionModal();
    }
});
</script>
