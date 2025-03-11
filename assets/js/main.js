/**
 * QA Reporter - Main JavaScript
 * Handles common UI interactions across the application
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Document loaded - initializing UI components');

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    if (tooltipTriggerList.length > 0) {
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    // Initialize Bootstrap popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    if (popoverTriggerList.length > 0) {
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }

    // Handle QA Assignment Modal
    initQAAssignmentModal();
});

/**
 * Initialize the QA Assignment Modal
 */
function initQAAssignmentModal() {
    console.log('Initializing QA Assignment Modal');

    // Get the modal element
    const assignQAModal = document.getElementById('assignQAModal');

    if (assignQAModal) {
        console.log('QA Modal found in DOM');

        // Set up event listener for modal show event
        assignQAModal.addEventListener('show.bs.modal', function(event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            if (button) {
                // Extract info from data attributes
                const projectId = button.getAttribute('data-project-id');
                const projectName = button.getAttribute('data-project-name');

                console.log(`Modal triggered for project: ${projectName} (ID: ${projectId})`);

                // Update the modal's content
                const modalProjectId = assignQAModal.querySelector('#modal-project-id');
                const modalProjectName = assignQAModal.querySelector('#modal-project-name');

                if (modalProjectId && modalProjectName) {
                    modalProjectId.value = projectId;
                    modalProjectName.value = projectName;
                    console.log('Modal fields updated successfully');
                } else {
                    console.error('Modal form fields not found');
                }
            } else {
                console.error('Modal trigger button not provided');
            }
        });

        // Add form submission validation
        const form = assignQAModal.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(event) {
                const qaUserSelect = form.querySelector('#qa_user_id');
                const projectIdInput = form.querySelector('#modal-project-id');

                if (!qaUserSelect.value) {
                    event.preventDefault();
                    alert('Please select a QA Reporter');
                    return false;
                }

                if (!projectIdInput.value) {
                    event.preventDefault();
                    console.error('No project ID set in form');
                    return false;
                }

                console.log(`Submitting form - assigning project ${projectIdInput.value} to QA ${qaUserSelect.value}`);
                return true;
            });
        } else {
            console.error('Form not found in QA modal');
        }
    } else {
        console.error('QA Modal element not found in DOM');
    }
}