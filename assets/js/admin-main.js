/**
 * QA Reporter - Main JavaScript
 * Handles common UI interactions across the application
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('QA Reporter - Initializing UI components');

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

    // Initialize auto-hide alerts
    const autoHideAlerts = document.querySelectorAll('.alert-dismissible:not(.no-auto-hide)');
    autoHideAlerts.forEach(function(alert) {
        setTimeout(function() {
            const closeButton = alert.querySelector('.btn-close');
            if (closeButton) {
                closeButton.click();
            }
        }, 5000); // Auto-hide after 5 seconds
    });

    // Handle QA Assignment Modal
    const assignQAModal = document.getElementById('assignQAModal');
    if (assignQAModal) {
        assignQAModal.addEventListener('show.bs.modal', function(event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract info from data attributes
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name');

            // Update the modal's content
            const modalProjectId = assignQAModal.querySelector('#modal-project-id');
            const modalProjectName = assignQAModal.querySelector('#modal-project-name');

            if (modalProjectId && modalProjectName) {
                modalProjectId.value = projectId;
                modalProjectName.value = projectName;
            }
        });
    }
});