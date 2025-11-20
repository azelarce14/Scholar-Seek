<?php
session_start();
include 'db_connect.php';

// Only allow students
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] != 'student') {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['user_id'];
$scholarship_id = $_GET['id'] ?? 0;

// Fetch scholarship details
$scholarship = null;
$scholarship_query = "SELECT * FROM scholarships WHERE id = ? AND status = 'active'";
$stmt = mysqli_prepare($conn, $scholarship_query);
mysqli_stmt_bind_param($stmt, "i", $scholarship_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $scholarship = mysqli_fetch_assoc($result);
} else {
    header("Location: student_dashboard.php");
    exit();
}

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

// Fetch student details to pre-fill form
$student = [];
$student_query = "SELECT * FROM students WHERE id = ?";
$stmt = mysqli_prepare($conn, $student_query);
mysqli_stmt_bind_param($stmt, "i", $student_id);
mysqli_stmt_execute($stmt);
$student_result = mysqli_stmt_get_result($stmt);

if ($student_result && mysqli_num_rows($student_result) > 0) {
    $student = mysqli_fetch_assoc($student_result);
}

// Always require these core documents for any scholarship application
$core_required_documents = [
    'Certificate of Enrollment',
    'Certificate of Good Moral Character', 
    'Report Card (Grades)',
    'Study Load'
];

// Parse additional documents from scholarship configuration
$additional_documents = json_decode($scholarship['required_documents'] ?? '[]', true);

// Combine core required documents with additional ones, removing duplicates
$required_documents = array_unique(array_merge($core_required_documents, $additional_documents));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ScholarSeek</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/img/icon.png">
    <link rel="apple-touch-icon" href="assets/img/icon.png">
    <link rel="stylesheet" href="assets/css/apply_scholarship.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="ScholarSeek Logo" class="logo" />
            </div>
            <div class="header-text">
                <p>Scholarship Application</p>
            </div>
        </div>
        <div class="student-info">
            <span>Welcome, <?php echo htmlspecialchars($student['fullname'] ?? 'Student'); ?></span>
            <a href="student_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </header>

    <div class="application-container">
        <!-- Scholarship Info Card -->
        <div class="scholarship-info-card">
            <h2><?php echo htmlspecialchars($scholarship['title']); ?></h2>
            <p class="sponsor"><i class="fas fa-building"></i> <?php echo htmlspecialchars($scholarship['sponsor']); ?></p>
            <div class="scholarship-details">
                <div class="detail">
                    <i class="fas fa-money-bill-wave"></i>
                    <span><strong>Amount:</strong> ₱<?php echo number_format($scholarship['amount']); ?></span>
                </div>
                <div class="detail">
                    <i class="fas fa-calendar-alt"></i>
                    <span><strong>Deadline:</strong> <?php echo date('F d, Y', strtotime($scholarship['deadline'])); ?></span>
                </div>
                <?php if (!empty($scholarship['min_gwa'])): ?>
                <div class="detail">
                    <i class="fas fa-graduation-cap"></i>
                    <span><strong>Minimum GWA:</strong> <?php echo $scholarship['min_gwa']; ?> or better</span>
                </div>
                <?php endif; ?>
                <div class="detail">
                    <i class="fas fa-file-alt"></i>
                    <span><strong>Required Documents:</strong> <?php echo count($required_documents); ?> files needed (4 core + <?php echo count($additional_documents); ?> additional)</span>
                </div>
            </div>
        </div>

        <!-- Application Form -->
        <div class="application-form-card">
            <h3><i class="fas fa-file-alt"></i> Application Form</h3>
            <p class="form-description">Please fill out all required fields and upload the necessary documents to complete your scholarship application.</p>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form action="process_application.php" method="POST" enctype="multipart/form-data" id="applicationForm">
                <input type="hidden" name="scholarship_id" value="<?php echo $scholarship_id; ?>">

                <!-- Personal Information Section -->
                <div class="form-section">
                    <h4><i class="fas fa-user"></i> Personal Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($student['fullname'] ?? ''); ?>" 
                                   readonly class="readonly-field">
                            <small>This information is from your account profile</small>
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" 
                                   readonly class="readonly-field">
                            <small>This information is from your account profile</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="student_number"><i class="fas fa-id-card"></i> Student Number</label>
                            <input type="text" id="student_number" name="student_number" 
                                   value="<?php echo htmlspecialchars($student['student_number'] ?? ''); ?>" 
                                   readonly class="readonly-field">
                            <small>This information is from your account profile</small>
                        </div>

                        <div class="form-group">
                            <label for="date_of_birth"><i class="fas fa-birthday-cake"></i> Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" 
                                   value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address *</label>
                        <textarea id="address" name="address" rows="3" 
                                  placeholder="Enter your complete address" 
                                  required><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="form-section">
                    <h4><i class="fas fa-graduation-cap"></i> Academic Information</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="program"><i class="fas fa-book"></i> Program/Course</label>
                            <input type="text" id="program" name="program" 
                                   value="<?php echo htmlspecialchars($student['program'] ?? ''); ?>" 
                                   readonly class="readonly-field">
                            <small>This information is from your account profile</small>
                        </div>

                        <div class="form-group">
                            <label for="year_level"><i class="fas fa-layer-group"></i> Year Level</label>
                            <input type="text" id="year_level" name="year_level" 
                                   value="<?php echo htmlspecialchars($student['year_level'] ?? ''); ?>" 
                                   readonly class="readonly-field">
                            <small>This information is from your account profile</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <?php if (!empty($scholarship['min_gwa'])): ?>
                        <div class="form-group">
                            <label for="gwa"><i class="fas fa-chart-line"></i> GWA (General Weighted Average) *</label>
                            <input type="number" id="gwa" name="gwa" 
                                   value="<?php echo htmlspecialchars($student['gwa'] ?? ''); ?>" 
                                   step="0.01" min="1.0" max="5.0" 
                                   placeholder="e.g., 1.5"
                                   required>
                            <small>Enter your current GWA (1.0 = highest, 2.5 = lowest, 5.0 = fail)</small>
                        </div>
                        <?php else: ?>
                        <div class="form-group">
                            <label for="gwa"><i class="fas fa-chart-line"></i> GWA (General Weighted Average) <span class="optional">(Optional)</span></label>
                            <input type="number" id="gwa" name="gwa" 
                                   value="<?php echo htmlspecialchars($student['gwa'] ?? ''); ?>" 
                                   step="0.01" min="1.0" max="5.0" 
                                   placeholder="e.g., 1.5">
                            <small>Enter your current GWA (1.0 = highest, 2.5 = lowest, 5.0 = fail). Leave empty if not available.</small>
                        </div>
                        <?php endif; ?>

                    </div>

                </div>

                <!-- Required Documents Section -->
                <div class="form-section">
                    <h4><i class="fas fa-paperclip"></i> Required Documents</h4>
                    <p class="section-description">Please upload the following documents in PDF format (max 5MB each):</p>
                    
                    <!-- Core Required Documents (Always Required) -->
                    <div class="core-documents">
                        <h5>Core Required Documents (Always Required)</h5>
                        <div class="documents-grid">
                            <?php foreach ($core_required_documents as $index => $doc): ?>
                                <div class="document-upload core-document">
                                    <label for="core_document_<?php echo $index; ?>">
                                        <i class="fas fa-file-pdf"></i>
                                        <?php echo htmlspecialchars($doc); ?> *
                                    </label>
                                    <input type="file" 
                                           id="core_document_<?php echo $index; ?>" 
                                           name="documents[]" 
                                           accept=".pdf" 
                                           required>
                                    <input type="hidden" name="document_types[]" value="<?php echo htmlspecialchars($doc); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Additional Documents (Scholarship Specific) -->
                    <?php if (!empty($additional_documents)): ?>
                    <div class="additional-documents">
                        <h5><i class="fas fa-plus-circle"></i> Additional Required Documents</h5>
                        <div class="documents-grid">
                            <?php foreach ($additional_documents as $index => $doc): ?>
                                <?php if (!in_array($doc, $core_required_documents)): // Avoid duplicates ?>
                                    <div class="document-upload additional-document">
                                        <label for="additional_document_<?php echo $index; ?>">
                                            <i class="fas fa-file-pdf"></i>
                                            <?php echo htmlspecialchars($doc); ?> *
                                        </label>
                                        <input type="file" 
                                               id="additional_document_<?php echo $index; ?>" 
                                               name="documents[]" 
                                               accept=".pdf" 
                                               required>
                                        <input type="hidden" name="document_types[]" value="<?php echo htmlspecialchars($doc); ?>">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-actions">
                    <a href="student_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div id="confirmModal" class="confirm-modal-overlay" aria-hidden="true">
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
            <div class="confirm-modal-header">
                <h3 id="confirmModalTitle">Submit application?</h3>
            </div>
            <div class="confirm-modal-body">
                <p id="confirmModalMessage">Please review all information and documents before submitting.</p>
            </div>
            <div class="confirm-modal-actions">
                <button type="button" class="btn btn-secondary" id="confirmModalCancel">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmModalOk">Submit</button>
            </div>
        </div>
    </div>

    <script>
        const applicationForm = document.getElementById('applicationForm');
        const confirmModal = document.getElementById('confirmModal');
        const confirmOkBtn = document.getElementById('confirmModalOk');
        const confirmCancelBtn = document.getElementById('confirmModalCancel');
        let pendingSubmitForm = null;

        // Enhanced form validation with animations
        applicationForm.addEventListener('submit', function(e) {
            const gwaField = document.getElementById('gwa');
            const gwaValue = parseFloat(gwaField.value);
            const minGwa = <?php echo !empty($scholarship['min_gwa']) ? $scholarship['min_gwa'] : 'null'; ?>;
            
            // Only validate GWA if it's required and has a value
            if (minGwa !== null && gwaField.hasAttribute('required') && gwaValue) {
                if (gwaValue > minGwa) {
                    e.preventDefault();
                    showErrorAlert(`Your GWA (${gwaValue}) does not meet the minimum requirement (${minGwa} or better) for this scholarship. Lower GWA is better in this scale.`);
                    gwaField.focus();
                    shakeElement(gwaField);
                    return false;
                }
            }
            
            // Validate GWA is within valid range if provided
            if (gwaValue && (gwaValue < 1.0 || (gwaValue > 2.5 && gwaValue !== 5.0))) {
                e.preventDefault();
                showErrorAlert('GWA must be between 1.0 (highest) and 2.5 (lowest), or 5.0 (fail).');
                gwaField.focus();
                shakeElement(gwaField);
                return false;
            }
            
            // Validate file uploads
            const fileInputs = document.querySelectorAll('input[type="file"]');
            for (let input of fileInputs) {
                if (input.files.length > 0) {
                    const file = input.files[0];
                    if (file.size > 5 * 1024 * 1024) {
                        e.preventDefault();
                        showErrorAlert(`File "${file.name}" is too large. Maximum file size is 5MB.`);
                        input.focus();
                        shakeElement(input.closest('.document-upload'));
                        return false;
                    }
                    if (file.type !== 'application/pdf') {
                        e.preventDefault();
                        showErrorAlert(`File "${file.name}" must be a PDF file.`);
                        input.focus();
                        shakeElement(input.closest('.document-upload'));
                        return false;
                    }
                }
            }

            // Open custom confirmation modal instead of native confirm
            e.preventDefault();
            pendingSubmitForm = this;
            openConfirmModal();
            return false;
        });

        // Enhanced file input handling with animations
        document.querySelectorAll('input[type="file"]').forEach(input => {
            const container = input.closest('.document-upload');
            const label = input.previousElementSibling;
            
            // Store original label text
            if (!label.hasAttribute('data-original')) {
                label.setAttribute('data-original', label.innerHTML);
            }
            
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const file = this.files[0];
                    const fileName = file.name;
                    
                    // Show loading state
                    container.classList.add('uploading');
                    
                    // Simulate upload delay for better UX
                    setTimeout(() => {
                        container.classList.remove('uploading');
                        container.classList.add('uploaded');
                        
                        label.innerHTML = `<i class="fas fa-check-circle"></i> ${fileName}`;
                        
                        // Add success animation
                        pulseElement(container);
                        
                        // Show file size (if helper element exists)
                        const small = container.querySelector('small');
                        if (small) {
                            small.innerHTML = `<strong>✓ Uploaded:</strong> ${fileName} (${formatFileSize(file.size)})`;
                        }
                    }, 800);
                } else {
                    container.classList.remove('uploaded', 'uploading');
                    label.innerHTML = label.getAttribute('data-original');

                    const small = container.querySelector('small');
                    if (small) {
                        small.innerHTML = small.getAttribute('data-original') || 'Upload PDF file (max 5MB)';
                    }
                }
            });
            
            // Drag and drop functionality
            container.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.borderColor = 'var(--primary-color)';
                this.style.backgroundColor = 'rgba(102, 126, 234, 0.1)';
            });
            
            container.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.backgroundColor = '';
            });
            
            container.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderColor = '';
                this.style.backgroundColor = '';
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                }
            });
        }, observerOptions);

        // Observe form sections for scroll animations
        document.querySelectorAll('.form-section').forEach(section => {
            observer.observe(section);
        });

        // Utility functions
        function showErrorAlert(message) {
            const alert = createAlert('error', message);
            insertAlert(alert);
        }

        function createAlert(type, message) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.innerHTML = `
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i>
                ${message}
            `;
            return alert;
        }

        function insertAlert(alert) {
            const form = document.getElementById('applicationForm');
            form.insertBefore(alert, form.firstChild);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }

        function shakeElement(element) {
            element.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                element.style.animation = '';
            }, 500);
        }

        function pulseElement(element) {
            element.style.animation = 'pulse 1s ease-in-out';
            setTimeout(() => {
                element.style.animation = '';
            }, 1000);
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Add shake animation to CSS if not present
        if (!document.querySelector('style[data-shake]')) {
            const style = document.createElement('style');
            style.setAttribute('data-shake', 'true');
            style.textContent = `
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
                    20%, 40%, 60%, 80% { transform: translateX(5px); }
                }
            `;
            document.head.appendChild(style);
        }

        // Enhanced form field interactions
        document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(field => {
            field.addEventListener('focus', function() {
                this.closest('.form-group').classList.add('focused');
            });
            
            field.addEventListener('blur', function() {
                this.closest('.form-group').classList.remove('focused');
            });
        });

        // Progress indicator
        function updateProgress() {
            const totalFields = document.querySelectorAll('input[required], select[required], textarea[required]').length;
            const filledFields = document.querySelectorAll('input[required]:valid, select[required]:valid, textarea[required]:valid').length;
            const progress = (filledFields / totalFields) * 100;
            
            // You can add a progress bar here if needed
            console.log(`Form completion: ${progress.toFixed(1)}%`);
        }

        // Update progress on field changes
        document.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('input', updateProgress);
            field.addEventListener('change', updateProgress);
        });

        // Initial progress calculation
        updateProgress();

        // --- Confirmation modal helpers ---
        function openConfirmModal() {
            if (!confirmModal) return;
            confirmModal.classList.add('show');
            confirmModal.setAttribute('aria-hidden', 'false');
        }

        function closeConfirmModal() {
            if (!confirmModal) return;
            confirmModal.classList.remove('show');
            confirmModal.setAttribute('aria-hidden', 'true');
        }

        if (confirmOkBtn) {
            confirmOkBtn.addEventListener('click', function () {
                if (pendingSubmitForm) {
                    const formToSubmit = pendingSubmitForm;
                    pendingSubmitForm = null;
                    closeConfirmModal();
                    // form.submit() does not trigger the submit event handler again
                    formToSubmit.submit();
                } else {
                    closeConfirmModal();
                }
            });
        }

        if (confirmCancelBtn) {
            confirmCancelBtn.addEventListener('click', function () {
                pendingSubmitForm = null;
                closeConfirmModal();
            });
        }

        if (confirmModal) {
            confirmModal.addEventListener('click', function (e) {
                if (e.target === confirmModal) {
                    pendingSubmitForm = null;
                    closeConfirmModal();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && confirmModal.classList.contains('show')) {
                    pendingSubmitForm = null;
                    closeConfirmModal();
                }
            });
        }
    </script>
</body>
</html>