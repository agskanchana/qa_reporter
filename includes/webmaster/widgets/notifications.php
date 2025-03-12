<?php
// Get current webmaster ID
$webmaster_id = $_SESSION['user_id'];

// Get notifications for this webmaster
// Note: Based on your DB schema, notifications table doesn't have project_id/related_id columns
// so we'll just fetch notifications without joining to projects
$notifications_query = "SELECT n.*
                      FROM notifications n
                      WHERE n.user_id = ? AND n.is_read = 0
                      ORDER BY n.created_at DESC
                      LIMIT 10";

$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $webmaster_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);

// Get projects that have received QA feedback in the last 7 days
// Note: Assuming qa_feedback table exists and has a structure similar to what was used previously
// If this table doesn't exist or has different structure, this query will need adjustment
$qa_feedback_query = "SELECT qf.*, p.name as project_name, p.id as project_id,
                     DATEDIFF(NOW(), qf.created_at) as days_ago
                     FROM qa_feedback qf
                     JOIN projects p ON qf.project_id = p.id
                     WHERE p.webmaster_id = ?
                     AND qf.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                     AND qf.is_read = 0
                     ORDER BY qf.created_at DESC
                     LIMIT 5";

// Since we don't have information about qa_feedback table structure,
// let's not execute this query yet - uncomment when table confirmed
/*
$qa_feedback_stmt = $conn->prepare($qa_feedback_query);
$qa_feedback_stmt->bind_param("i", $webmaster_id);
$qa_feedback_stmt->execute();
$qa_feedback_result = $qa_feedback_stmt->get_result();
$qa_feedback = $qa_feedback_result->fetch_all(MYSQLI_ASSOC);
*/
// For now, initialize as empty array
$qa_feedback = [];

// Get upcoming deadlines (3 days)
$deadlines_query = "SELECT p.*,
                 DATEDIFF(p.project_deadline, CURDATE()) as days_remaining
                 FROM projects p
                 WHERE p.webmaster_id = ?
                 AND p.current_status NOT LIKE '%complete%'
                 AND p.project_deadline IS NOT NULL
                 AND p.project_deadline > CURDATE()
                 AND DATEDIFF(p.project_deadline, CURDATE()) <= 3
                 ORDER BY p.project_deadline ASC
                 LIMIT 5";

$deadlines_stmt = $conn->prepare($deadlines_query);
$deadlines_stmt->bind_param("i", $webmaster_id);
$deadlines_stmt->execute();
$deadlines_result = $deadlines_stmt->get_result();
$upcoming_deadlines = $deadlines_result->fetch_all(MYSQLI_ASSOC);

// Count all unread notifications
$total_unread = count($notifications) + count($qa_feedback) + count($upcoming_deadlines);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-bell me-2 text-primary"></i>
            Notifications
            <?php if ($total_unread > 0): ?>
                <span class="badge rounded-pill bg-danger ms-2"><?php echo $total_unread; ?></span>
            <?php endif; ?>
        </h5>
        <?php if ($total_unread > 0): ?>
            <a href="<?php echo BASE_URL; ?>/webmaster/mark_all_read.php" class="btn btn-sm btn-light" title="Mark all as read">
                <i class="bi bi-check2-all"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php if (empty($upcoming_deadlines) && empty($qa_feedback) && empty($notifications)): ?>
                <div class="list-group-item py-4 text-center">
                    <p class="text-muted mb-0">No unread notifications</p>
                </div>
            <?php else: ?>
                <!-- System Notifications -->
                <?php foreach ($notifications as $notification): ?>
                    <div class="list-group-item d-flex align-items-center p-3">
                        <div class="notification-icon me-3">
                            <?php if ($notification['type'] === 'info'): ?>
                                <div class="avatar avatar-sm bg-primary text-white">
                                    <i class="bi bi-info-circle"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'warning'): ?>
                                <div class="avatar avatar-sm bg-warning text-dark">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'success'): ?>
                                <div class="avatar avatar-sm bg-success text-white">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'danger'): ?>
                                <div class="avatar avatar-sm bg-danger text-white">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                            <?php else: ?>
                                <div class="avatar avatar-sm bg-primary text-white">
                                    <i class="bi bi-bell"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php
                                        $date = new DateTime($notification['created_at']);
                                        echo $date->format('M j, g:i A');
                                    ?>
                                </small>

                                <?php
                                // Extract project ID from notification message using regex
                                // This is a fallback method since we don't have a direct project_id in notifications
                                $project_id = null;
                                if (preg_match('/project \'([^\']+)\'/', $notification['message'], $matches)) {
                                    // Look up the project ID based on the name
                                    $project_name = $matches[1];
                                    $stmt = $conn->prepare("SELECT id FROM projects WHERE name = ? AND webmaster_id = ? LIMIT 1");
                                    $stmt->bind_param("si", $project_name, $webmaster_id);
                                    $stmt->execute();
                                    $project_result = $stmt->get_result();
                                    if ($project_result && $project_row = $project_result->fetch_assoc()) {
                                        $project_id = $project_row['id'];
                                    }
                                }
                                ?>

                                <?php if ($project_id): ?>
                                <a href="<?php echo BASE_URL; ?>/view_project.php?id=<?php echo $project_id; ?>" class="btn btn-sm btn-outline-primary">View Project</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ms-3">
                            <a href="<?php echo BASE_URL; ?>/webmaster/mark_read.php?id=<?php echo $notification['id']; ?>&type=notification" class="btn btn-sm btn-light">
                                <i class="bi bi-check2"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- QA Feedback Notifications - Commented out until table structure confirmed -->
                <?php /* foreach ($qa_feedback as $feedback): ?>
                    <div class="list-group-item d-flex align-items-center p-3">
                        <!-- QA feedback display here -->
                    </div>
                <?php endforeach; */ ?>

                <!-- Upcoming Deadline Notifications -->
                <?php foreach ($upcoming_deadlines as $deadline): ?>
                    <div class="list-group-item d-flex align-items-center p-3">
                        <div class="notification-icon me-3">
                            <div class="avatar avatar-sm bg-danger text-white">
                                <i class="bi bi-calendar-exclamation"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">Upcoming Deadline</h6>
                            <p class="mb-1 small">
                                <strong><?php echo htmlspecialchars($deadline['name']); ?></strong> is due soon
                                <span class="badge bg-danger">
                                    <?php echo date('M j', strtotime($deadline['project_deadline'])); ?>
                                </span>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-danger fw-bold">
                                    <?php
                                    if ($deadline['days_remaining'] == 0) {
                                        echo 'Due today!';
                                    } elseif ($deadline['days_remaining'] == 1) {
                                        echo '1 day left';
                                    } else {
                                        echo $deadline['days_remaining'] . ' days left';
                                    }
                                    ?>
                                </small>
                                <a href="<?php echo BASE_URL; ?>/view_project.php?id=<?php echo $deadline['id']; ?>" class="btn btn-sm btn-outline-danger">View Project</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($total_unread > 10): ?>
    <div class="card-footer bg-white text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/notifications.php" class="text-decoration-none">View all notifications</a>
    </div>
    <?php endif; ?>
</div>