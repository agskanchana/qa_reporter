<?php
// filepath: c:\wamp64\www\qa_reporter\includes\admin\widgets\notifications.php

// Get missed deadlines (that don't have an explanation yet)
$missed_deadlines_query = "SELECT md.id, md.project_id, md.deadline_type, md.original_deadline, p.name as project_name,
                          u.username as webmaster_name
                          FROM missed_deadlines md
                          JOIN projects p ON md.project_id = p.id
                          JOIN users u ON p.webmaster_id = u.id
                          WHERE md.reason IS NULL
                          ORDER BY md.recorded_at DESC
                          LIMIT 5";

$missed_deadlines = $conn->query($missed_deadlines_query)->fetch_all(MYSQLI_ASSOC);

// Get new projects awaiting QA assignment
$new_projects_query = "SELECT p.id, p.name, p.created_at, u.username as webmaster_name
                       FROM projects p
                       LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                       JOIN users u ON p.webmaster_id = u.id
                       WHERE qa.id IS NULL
                       AND (p.current_status LIKE '%wp_conversion_qa%'
                            OR p.current_status LIKE '%page_creation_qa%'
                            OR p.current_status LIKE '%golive_qa%')
                       ORDER BY p.created_at DESC
                       LIMIT 5";

$new_projects = $conn->query($new_projects_query)->fetch_all(MYSQLI_ASSOC);

// Get only unread system notifications
$notifications_query = "SELECT n.id, n.type, n.message, n.created_at
                        FROM notifications n
                        WHERE (n.user_id = $user_id OR n.user_id IS NULL)
                        AND n.is_read = 0
                        ORDER BY n.created_at DESC
                        LIMIT 10";

$notifications = $conn->query($notifications_query)->fetch_all(MYSQLI_ASSOC);

// Count all unread notifications
$total_unread = count($notifications) + count($new_projects) + count($missed_deadlines);
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
            <a href="admin/mark_all_read.php" class="btn btn-sm btn-light" title="Mark all as read">
                <i class="bi bi-check2-all"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            <?php if (empty($missed_deadlines) && empty($new_projects) && empty($notifications)): ?>
                <div class="list-group-item py-4 text-center">
                    <p class="text-muted mb-0">No unread notifications</p>
                </div>
            <?php else: ?>
                <!-- System Notifications -->
                <?php foreach ($notifications as $notification):
                    $icon = 'bell';
                    $border_color = 'primary';

                    if ($notification['type'] == 'warning') {
                        $icon = 'exclamation-triangle';
                        $border_color = 'warning';
                    } elseif ($notification['type'] == 'info') {
                        $icon = 'info-circle';
                        $border_color = 'info';
                    } elseif ($notification['type'] == 'success') {
                        $icon = 'check-circle';
                        $border_color = 'success';
                    }
                ?>
                    <div class="list-group-item p-3 border-start border-4 mt-1 mb-1 border-<?php echo $border_color; ?>">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-sm bg-light text-<?php echo $border_color; ?> rounded-circle">
                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1 small">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo timeAgo($notification['created_at']); ?>
                                </p>
                            </div>
                            <div class="ms-2">
                                <a href="mark_read.php?id=<?php echo $notification['id']; ?>" class="btn btn-sm btn-light" title="Mark as read">
                                    <i class="bi bi-check2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- New Projects Needing QA -->
                <?php foreach ($new_projects as $project): ?>
                    <div class="list-group-item p-3 border-start border-4 border-primary">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-sm bg-light text-primary rounded-circle">
                                    <i class="bi bi-file-earmark-plus"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">New Project Needs QA</h6>
                                <p class="mb-1 small">
                                    <strong><?php echo htmlspecialchars($project['name']); ?></strong> by
                                    <?php echo htmlspecialchars($project['webmaster_name']); ?>
                                </p>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-clock me-1"></i>
                                    <?php echo timeAgo($project['created_at']); ?>
                                </p>
                            </div>
                            <div class="ms-auto">
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#assignQAModal"
                                        data-project-id="<?php echo $project['id']; ?>"
                                        data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                                    <i class="bi bi-person-plus me-1"></i> Assign
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Missed Deadlines -->
                <?php foreach ($missed_deadlines as $deadline): ?>
                    <div class="list-group-item p-3 border-start border-4 border-warning">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-sm bg-light text-warning rounded-circle">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Missed Deadline</h6>
                                <p class="mb-1 small">
                                    <strong><?php echo htmlspecialchars($deadline['project_name']); ?></strong>
                                    (<?php echo ucfirst(str_replace('_', ' ', $deadline['deadline_type'])); ?>)
                                </p>
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-calendar-x me-1"></i>
                                    Due: <?php echo date('M j, Y', strtotime($deadline['original_deadline'])); ?>
                                </p>
                            </div>
                            <div class="ms-auto">
                                <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $deadline['project_id']; ?>"
                                   class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($total_unread > 0): ?>
        <div class="card-footer bg-white text-center p-2">
            <a href="notifications.php" class="text-decoration-none">View all notifications</a>
        </div>
    <?php endif; ?>
</div>