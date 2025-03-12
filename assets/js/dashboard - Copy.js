document.addEventListener('DOMContentLoaded', function() {
    // Toggle notifications collapse
    const collapseBtn = document.getElementById('collapseNotifications');
    const notificationsBody = document.getElementById('notificationsBody');

    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            if (notificationsBody.style.display === 'none') {
                notificationsBody.style.display = 'block';
                collapseBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
            } else {
                notificationsBody.style.display = 'none';
                collapseBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
            }
        });
    }

    // Mark notifications as read
    const markReadButtons = document.querySelectorAll('.mark-read');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            const notificationElement = this.closest('.alert');

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
                    notificationElement.remove();

                    // Update badge count
                    const badge = document.querySelector('.card-header .badge');
                    if (badge) {
                        let count = parseInt(badge.textContent);
                        badge.textContent = count - 1;

                        // If no more notifications, hide the card
                        if (count - 1 <= 0) {
                            document.querySelector('.card').style.display = 'none';
                        }
                    }
                }
            });
        });
    });
});