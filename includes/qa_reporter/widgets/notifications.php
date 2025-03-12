<?php
// Get recent notifications for this user
$notifications_query = "SELECT * FROM notifications
                      WHERE user_id = ? OR user_id IS NULL
                      ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($notifications_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count unread notifications
$unread_count_query = "SELECT COUNT(*) as count FROM notifications
                       WHERE (user_id = ? OR user_id IS NULL)
                       AND is_read = 0";
$stmt = $conn->prepare($unread_count_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unread_count = $stmt->get_result()->fetch_assoc()['count'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bell me-2 text-primary"></i>
            Notifications
            <?php if ($unread_count > 0): ?>
            <span class="badge bg-danger ms-2"><?php echo $unread_count; ?> new</span>
            <?php endif; ?>
        </h5>
        <?php if (count($notifications) > 0): ?>
        <a href="<?php echo BASE_URL; ?>/notifications.php" class="btn btn-sm btn-outline-primary">
            View All
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (count($notifications) === 0): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-bell-slash text-muted" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="fw-normal text-muted">No notifications at the moment</h6>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notification): ?>
            <div class="list-group-item border-0 py-3 px-4 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                <div class="d-flex">
                    <?php
                    $icon_class = 'info';
                    switch ($notification['type']) {
                        case 'warning':
                            $icon_class = 'warning';
                            break;
                        case 'success':
                            $icon_class = 'success';
                            break;
                        case 'danger':
                            $icon_class = 'danger';
                            break;
                    }
                    ?>
                    <div class="me-3">
                        <span class="avatar avatar-sm bg-<?php echo $icon_class; ?> bg-opacity-10 rounded-circle">
                            <i class="bi bi-bell-fill text-<?php echo $icon_class; ?>"></i>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="mb-1">
                            <?php echo htmlspecialchars($notification['message']); ?>
                            <?php if (!$notification['is_read']): ?>
                            <span class="badge bg-danger ms-2">New</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">
                            <?php echo timeAgo($notification['created_at']); ?>
                        </div>
                    </div>
                    <?php if (!$notification['is_read']): ?>
                    <div>
                        <button class="btn btn-sm btn-light mark-read-btn" data-id="<?php echo $notification['id']; ?>">
                            <i class="bi bi-check"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark notification as read
    const markReadButtons = document.querySelectorAll('.mark-read-btn');
    markReadButtons.forEach(button => {
        button.addEventListener('click', function() {
            const notificationId = this.getAttribute('data-id');
            fetch('<?php echo BASE_URL; ?>/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove new badge and change background color
                    const listItem = this.closest('.list-group-item');
                    listItem.classList.remove('bg-light');
                    const newBadge = listItem.querySelector('.badge.bg-danger');
                    if (newBadge) newBadge.remove();
                    this.remove();

                    // Update counter in header
                    const counterBadge = document.querySelector('.card-header .badge.bg-danger');
                    if (counterBadge) {
                        const currentCount = parseInt(counterBadge.textContent);
                        if (currentCount > 1) {
                            counterBadge.textContent = `${currentCount - 1} new`;
                        } else {
                            counterBadge.remove();
                        }
                    }
                }
            });
        });
    });
});
</script>