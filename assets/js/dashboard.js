document.addEventListener('DOMContentLoaded', function() {
    // Toggle notifications collapse
    const collapseBtn = document.getElementById('collapseNotifications');
    const notificationsBody = document.getElementById('notificationsBody');

    if (collapseBtn && notificationsBody) {
        collapseBtn.addEventListener('click', function() {
            if (notificationsBody.style.display === 'none') {
                notificationsBody.style.display = 'block';
                collapseBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
                collapseBtn.setAttribute('aria-expanded', 'true');
            } else {
                notificationsBody.style.display = 'none';
                collapseBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
                collapseBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    // Mark notifications as read
    const markReadButtons = document.querySelectorAll('.mark-read');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            const notificationElement = this.closest('.alert');

            // Add fade-out animation
            notificationElement.style.opacity = '0';
            notificationElement.style.transition = 'opacity 0.3s ease-out';

            // Send AJAX request to mark as read
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove element after animation completes
                    setTimeout(() => {
                        notificationElement.remove();

                        // Update badge count
                        const badge = document.querySelector('.card-header .badge');
                        if (badge) {
                            let count = parseInt(badge.textContent);
                            badge.textContent = count - 1;

                            // If no more notifications, hide the card with animation
                            if (count - 1 <= 0) {
                                const notificationCard = document.querySelector('.notifications-card');
                                if (notificationCard) {
                                    notificationCard.style.opacity = '0';
                                    notificationCard.style.transition = 'opacity 0.5s ease-out';
                                    setTimeout(() => {
                                        notificationCard.style.display = 'none';
                                    }, 500);
                                }
                            }
                        }
                    }, 300);
                } else {
                    // If error, revert opacity
                    notificationElement.style.opacity = '1';
                    console.error('Error marking notification as read');
                }
            })
            .catch(error => {
                notificationElement.style.opacity = '1';
                console.error('Error:', error);
            });
        });
    });

    // Project status filter
    const statusFilters = document.querySelectorAll('.status-filter');
    if (statusFilters.length) {
        statusFilters.forEach(filter => {
            filter.addEventListener('click', function(e) {
                e.preventDefault();
                const status = this.getAttribute('data-status');

                // Update active state
                statusFilters.forEach(f => f.classList.remove('active'));
                this.classList.add('active');

                // Filter table rows
                const projectRows = document.querySelectorAll('.project-row');
                projectRows.forEach(row => {
                    if (status === 'all') {
                        row.style.display = '';
                    } else {
                        const rowStatus = row.getAttribute('data-status');
                        row.style.display = rowStatus === status ? '' : 'none';
                    }
                });

                // Update counter
                const visibleProjects = document.querySelectorAll('.project-row[style=""]').length;
                const counter = document.getElementById('visible-projects-count');
                if (counter) {
                    counter.textContent = visibleProjects;
                }
            });
        });
    }

    // Table row hover effect
    const tableRows = document.querySelectorAll('.table-hover tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.classList.add('row-highlight');
        });
        row.addEventListener('mouseleave', function() {
            this.classList.remove('row-highlight');
        });
    });

    // Project cards hover effect
    const projectCards = document.querySelectorAll('.project-card');
    projectCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('card-highlight');
        });
        card.addEventListener('mouseleave', function() {
            this.classList.remove('card-highlight');
        });
    });

    // Expand/collapse project details
    const detailsToggles = document.querySelectorAll('.toggle-project-details');
    detailsToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const projectId = this.getAttribute('data-project-id');
            const detailsRow = document.getElementById(`project-details-${projectId}`);

            if (detailsRow) {
                if (detailsRow.classList.contains('d-none')) {
                    // Show details
                    detailsRow.classList.remove('d-none');
                    this.innerHTML = '<i class="bi bi-dash-circle"></i>';
                    this.setAttribute('title', 'Hide details');
                } else {
                    // Hide details
                    detailsRow.classList.add('d-none');
                    this.innerHTML = '<i class="bi bi-plus-circle"></i>';
                    this.setAttribute('title', 'Show details');
                }
            }
        });
    });

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Search functionality for projects
    const projectSearch = document.getElementById('project-search');
    if (projectSearch) {
        projectSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const projectItems = document.querySelectorAll('.project-row, .project-card');

            projectItems.forEach(item => {
                const projectName = item.querySelector('.project-name').textContent.toLowerCase();
                const webmasterName = item.querySelector('.webmaster-name')?.textContent.toLowerCase() || '';

                if (projectName.includes(searchTerm) || webmasterName.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }

    // Progress bar animation
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const targetWidth = bar.getAttribute('aria-valuenow') + '%';
        // Start with 0 width
        bar.style.width = '0%';

        // Animate to target width
        setTimeout(() => {
            bar.style.transition = 'width 1s ease-in-out';
            bar.style.width = targetWidth;
        }, 200);
    });

    // QA Assignment modal functionality
    const assignQAModal = document.getElementById('assignQAModal');
    if (assignQAModal) {
        assignQAModal.addEventListener('show.bs.modal', function (event) {
            // Button that triggered the modal
            const button = event.relatedTarget;

            // Extract project info from data attributes
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name');

            // Update the modal's content
            const modalProjectId = assignQAModal.querySelector('#modal-project-id');
            const modalProjectName = assignQAModal.querySelector('#modal-project-name');

            modalProjectId.value = projectId;
            modalProjectName.value = projectName;
        });

        // Form validation
        const assignForm = assignQAModal.querySelector('form');
        assignForm.addEventListener('submit', function(e) {
            if (!assignForm.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }

            assignForm.classList.add('was-validated');
        });
    }

    // Advanced filtering for admin
    const webmasterFilters = document.querySelectorAll('.webmaster-filter');
    const qaFilters = document.querySelectorAll('.qa-filter');

    function applyFilters() {
        const selectedStatus = document.querySelector('.status-filter.active')?.getAttribute('data-status') || 'all';
        const selectedWebmaster = document.querySelector('.webmaster-filter.active')?.getAttribute('data-webmaster') || 'all';
        const selectedQa = document.querySelector('.qa-filter.active')?.getAttribute('data-qa') || 'all';

        const projectRows = document.querySelectorAll('.project-row');
        let visibleCount = 0;

        projectRows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowWebmaster = row.getAttribute('data-webmaster');
            const rowQa = row.getAttribute('data-qa');

            const statusMatch = selectedStatus === 'all' || rowStatus === selectedStatus;
            const webmasterMatch = selectedWebmaster === 'all' || rowWebmaster === selectedWebmaster;
            const qaMatch = selectedQa === 'all' || rowQa === selectedQa;

            if (statusMatch && webmasterMatch && qaMatch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update counter if it exists
        const counter = document.getElementById('visible-projects-count');
        if (counter) {
            counter.textContent = visibleCount;
        }
    }

    // Add event listeners to all filter buttons
    [...statusFilters, ...webmasterFilters, ...qaFilters].forEach(filter => {
        filter.addEventListener('click', function(e) {
            e.preventDefault();

            // Get the filter group this button belongs to
            const filterGroup = this.closest('.btn-group').querySelectorAll('button');

            // Remove active class from all buttons in the group
            filterGroup.forEach(btn => btn.classList.remove('active'));

            // Add active class to clicked button
            this.classList.add('active');

            // Apply all active filters
            applyFilters();
        });
    });

    // Project detail expansion with animation
    const detailToggles = document.querySelectorAll('.toggle-project-details');
    detailToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const projectId = this.getAttribute('data-project-id');
            const detailsRow = document.getElementById(`project-details-${projectId}`);

            if (detailsRow) {
                if (detailsRow.classList.contains('d-none')) {
                    // Show details with animation
                    detailsRow.classList.remove('d-none');
                    detailsRow.style.maxHeight = '0';
                    detailsRow.style.overflow = 'hidden';
                    detailsRow.style.transition = 'max-height 0.3s ease-in-out';

                    // Trigger reflow
                    detailsRow.offsetHeight;

                    // Set max height to a large value to allow expansion
                    detailsRow.style.maxHeight = '1000px';

                    this.innerHTML = '<i class="bi bi-dash-circle"></i>';
                    this.setAttribute('title', 'Hide details');
                    this.setAttribute('data-bs-original-title', 'Hide details');
                } else {
                    // Hide details with animation
                    detailsRow.style.maxHeight = detailsRow.scrollHeight + 'px';

                    // Trigger reflow
                    detailsRow.offsetHeight;

                    // Collapse
                    detailsRow.style.maxHeight = '0';

                    // After animation, hide completely
                    setTimeout(() => {
                        detailsRow.classList.add('d-none');
                    }, 300);

                    this.innerHTML = '<i class="bi bi-plus-circle"></i>';
                    this.setAttribute('title', 'Show details');
                    this.setAttribute('data-bs-original-title', 'Show details');
                }

                // Update tooltip
                if (bootstrap.Tooltip.getInstance(this)) {
                    bootstrap.Tooltip.getInstance(this).dispose();
                    new bootstrap.Tooltip(this);
                }
            }
        });
    });

    // Charts initialization (if Chart.js is included)
    if (typeof Chart !== 'undefined') {
        // Initialize dashboard charts
        initCharts();
    }
});

function initCharts() {
    // This function would be called if Chart.js is loaded
    // Add any chart initialization code here
}