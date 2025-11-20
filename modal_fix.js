/**
 * Enhanced Modal Fix for ScholarSeek Application Details
 * This script provides a robust solution for the student application modal
 */

// Ensure the modal functionality works properly
(function() {
    'use strict';
    
    console.log('Modal fix script loaded');
    
    // Wait for DOM to be ready
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }
    
    ready(function() {
        console.log('DOM ready, initializing modal fix...');
        
        // Check if required elements exist
        const modal = document.getElementById('applicationModal');
        const modalBody = document.getElementById('applicationModalBody');
        
        if (!modal) {
            console.error('Application modal element not found!');
            createModalElements();
        } else {
            console.log('Modal elements found');
        }
        
        // Enhance existing student name links
        enhanceStudentLinks();
        
        // Ensure close functionality works
        setupModalCloseHandlers();
    });
    
    function createModalElements() {
        console.log('Creating missing modal elements...');
        
        const modalHTML = `
            <div id="applicationModal" class="modal" style="display: none;">
                <div class="modal-content application-modal">
                    <div class="modal-header">
                        <h3><i class="fas fa-file-alt"></i> Application Details</h3>
                        <span class="close" onclick="closeApplicationModal()">&times;</span>
                    </div>
                    <div class="modal-body" id="applicationModalBody">
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading application details...</p>
                        </div>
                    </div>
                    <div class="modal-footer" id="applicationModalFooter" style="display: none;">
                        <div class="modal-actions">
                            <button type="button" class="btn btn-secondary" onclick="closeApplicationModal()">
                                <i class="fas fa-times"></i> Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        console.log('Modal elements created');
    }
    
    function enhanceStudentLinks() {
        const studentLinks = document.querySelectorAll('.student-name-link');
        console.log('Found', studentLinks.length, 'student name links');

        // We no longer remove the existing inline onclick handlers.
        // manage_applications.php already wires links with
        // onclick="viewApplication(<id>)", and below we override
        // window.viewApplication to point to viewApplicationEnhanced.
        // This is enough to ensure clicks open the enhanced modal.

        // Optionally, we could add extra UX (e.g., cursor pointer) here,
        // but no additional JS behavior is required.
    }
    
    function setupModalCloseHandlers() {
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('applicationModal');
            if (event.target === modal) {
                closeApplicationModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('applicationModal');
                if (modal && modal.style.display === 'block') {
                    closeApplicationModal();
                }
            }
        });
    }
    
    // Enhanced viewApplication function
    window.viewApplicationEnhanced = function(applicationId) {
        console.log('viewApplicationEnhanced called with ID:', applicationId);
        
        const modal = document.getElementById('applicationModal');
        const modalBody = document.getElementById('applicationModalBody');
        
        if (!modal || !modalBody) {
            console.error('Modal elements not found!');
            alert('Error: Modal elements not found. Please refresh the page and try again.');
            return;
        }
        
        // Show modal with loading state
        modal.style.display = 'block';
        modalBody.innerHTML = `
            <div class="loading-spinner" style="text-align: center; padding: 2rem;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: #007bff; margin-bottom: 1rem;"></i>
                <p>Loading application details...</p>
            </div>
        `;
        
        // Make AJAX request with better error handling
        const url = `manage_applications.php?action=get_application_details&id=${applicationId}`;
        console.log('Making AJAX request to:', url);
        
        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response ok:', response.ok);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text.substring(0, 500) + '...');
            
            try {
                const data = JSON.parse(text);
                console.log('Parsed data:', data);
                
                if (data.success) {
                    displayApplicationDetailsEnhanced(data);
                } else {
                    throw new Error(data.error || 'Unknown error occurred');
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                console.log('Full response text:', text);
                
                // Show error with response details
                modalBody.innerHTML = `
                    <div style="padding: 2rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem;"></i>
                        <h4 style="color: #dc3545; margin-bottom: 1rem;">Error Loading Application</h4>
                        <p style="margin-bottom: 1rem;">The server response was not in the expected format.</p>
                        <details style="text-align: left; margin: 1rem 0;">
                            <summary style="cursor: pointer; font-weight: bold;">Server Response Details</summary>
                            <pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow: auto; max-height: 300px; font-size: 0.8rem;">${text}</pre>
                        </details>
                        <button onclick="closeApplicationModal()" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Close
                        </button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            
            modalBody.innerHTML = `
                <div style="padding: 2rem; text-align: center;">
                    <i class="fas fa-wifi" style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem;"></i>
                    <h4 style="color: #dc3545; margin-bottom: 1rem;">Connection Error</h4>
                    <p style="margin-bottom: 1rem;">Failed to load application details. Please check your connection and try again.</p>
                    <p style="font-size: 0.9rem; color: #6c757d; margin-bottom: 1.5rem;">Error: ${error.message}</p>
                    <div style="display: flex; gap: 1rem; justify-content: center;">
                        <button onclick="viewApplicationEnhanced(${applicationId})" style="padding: 0.75rem 1.5rem; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                        <button onclick="closeApplicationModal()" style="padding: 0.75rem 1.5rem; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Close
                        </button>
                    </div>
                </div>
            `;
        });
    };
    
    function displayApplicationDetailsEnhanced(data) {
        console.log('Displaying application details:', data);
        
        const app = data.application;
        const scholarship = data.scholarship;
        const documents = data.documents || [];

        // Keep global state in sync so approve/reject buttons work
        window.currentApplicationId = app.id;
        window.currentStudentName = app.full_name || app.student_name;
        window.currentScholarshipTitle = scholarship.title;
        
        const modalBody = document.getElementById('applicationModalBody');
        const modalFooter = document.getElementById('applicationModalFooter');
        
        modalBody.innerHTML = `
            <div class="application-details" style="padding: 1rem;">
                <!-- Student Information -->
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: #007bff; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-user"></i> Student Information
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div><strong>Name:</strong> ${app.full_name || 'N/A'}</div>
                        <div><strong>Email:</strong> ${app.email || 'N/A'}</div>
                        <div><strong>Student Number:</strong> ${app.student_number || 'N/A'}</div>
                        <div><strong>Year Level:</strong> ${app.year_level || 'N/A'}</div>
                        <div><strong>Program:</strong> ${app.program || 'N/A'}</div>
                        <div><strong>Department:</strong> ${app.department || 'N/A'}</div>
                        <div><strong>GWA:</strong> ${app.gwa ? parseFloat(app.gwa).toFixed(2) : 'N/A'}</div>
                        <div><strong>Date of Birth:</strong> ${app.date_of_birth ? new Date(app.date_of_birth).toLocaleDateString() : 'N/A'}</div>
                    </div>
                </div>
                
                <!-- Scholarship Information -->
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: #28a745; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-graduation-cap"></i> Scholarship Information
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div><strong>Title:</strong> ${scholarship.title || 'N/A'}</div>
                        <div><strong>Amount:</strong> ₱${scholarship.amount ? parseFloat(scholarship.amount).toLocaleString() : 'N/A'}</div>
                    </div>
                    ${scholarship.description ? `<div style="margin-top: 1rem;"><strong>Description:</strong><br>${scholarship.description}</div>` : ''}
                </div>
                
                <!-- Application Status -->
                <div style="margin-bottom: 2rem;">
                    <h4 style="color: #6f42c1; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-info-circle"></i> Application Status
                    </h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                        <div><strong>Status:</strong> 
                            <span style="padding: 0.25rem 0.75rem; border-radius: 4px; font-size: 0.875rem; background: ${getStatusColor(app.status)}; color: white;">
                                ${app.status ? app.status.charAt(0).toUpperCase() + app.status.slice(1) : 'Unknown'}
                            </span>
                        </div>
                        <div><strong>Application Date:</strong> ${app.application_date ? new Date(app.application_date).toLocaleDateString() : 'N/A'}</div>
                        ${app.review_date ? `<div><strong>Review Date:</strong> ${new Date(app.review_date).toLocaleDateString()}</div>` : ''}
                        ${app.reviewer_name ? `<div><strong>Reviewed By:</strong> ${app.reviewer_name}</div>` : ''}
                    </div>
                </div>
                
                <!-- Documents -->
                <div>
                    <h4 style="color: #dc3545; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-file-pdf"></i> Submitted Documents
                    </h4>
                    ${documents.length > 0 ? 
                        documents.map(doc => `
                            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i class="fas fa-file-${doc.extension === 'pdf' ? 'pdf' : 'alt'}" style="color: #dc3545;"></i>
                                    <div>
                                        <div style="font-weight: bold;">${doc.type}</div>
                                        <div style="font-size: 0.875rem; color: #6c757d;">${doc.filename}</div>
                                    </div>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button onclick="window.open('${doc.path}', '_blank')" style="padding: 0.25rem 0.5rem; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem;">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </div>
                        `).join('') 
                        : '<p style="color: #6c757d; font-style: italic;">No documents submitted</p>'
                    }
                </div>
            </div>
        `;
        
        // Show/hide footer based on status
        if (modalFooter) {
            modalFooter.style.display = app.status === 'pending' ? 'block' : 'none';
        }
    }
    
    function getStatusColor(status) {
        const colors = {
            'pending': '#ffc107',
            'approved': '#28a745',
            'rejected': '#dc3545'
        };
        return colors[status] || '#6c757d';
    }
    
    // Enhanced close function
    window.closeApplicationModal = function() {
        console.log('closeApplicationModal called');
        const modal = document.getElementById('applicationModal');
        const modalFooter = document.getElementById('applicationModalFooter');
        
        if (modal) {
            modal.style.display = 'none';
        }
        
        if (modalFooter) {
            modalFooter.style.display = 'none';
        }
        
        // Reset global variables
        if (window.currentApplicationId) {
            window.currentApplicationId = null;
        }
        if (window.currentStudentName) {
            window.currentStudentName = null;
        }
        if (window.currentScholarshipTitle) {
            window.currentScholarshipTitle = null;
        }
    };
    
    // Override the original viewApplication function
    window.viewApplication = window.viewApplicationEnhanced;
    
    console.log('Modal fix script initialization complete');
    
})();
