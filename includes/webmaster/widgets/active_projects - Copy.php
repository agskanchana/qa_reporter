<?php
// Get all active projects assigned to this webmaster (not completed)
$active_projects_query = "SELECT p.*,
                       DATEDIFF(p.project_deadline, CURDATE()) as days_remaining,
                       qa.qa_user_id,
                       u.username as qa_username
                       FROM projects p
                       LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                       LEFT JOIN users u ON qa.qa_user_id = u.id
                       WHERE p.webmaster_id = ?
                       AND p.current_status NOT LIKE '%complete%'
                       ORDER BY
                           CASE
                               WHEN DATEDIFF(p.project_deadline, CURDATE()) < 0 THEN 0
                               ELSE 1
                           END,
                           DATEDIFF(p.project_deadline, CURDATE()),
                           p.created_at DESC";

$stmt = $conn->prepare($active_projects_query);
$stmt->bind_param("i", $webmaster_id);
$stmt->execute();
$active_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count projects by status
$wp_conversion_count = 0;
$page_creation_count = 0;
$golive_count = 0;
$in_qa_count = 0;

foreach ($active_projects as $project) {
    if (strpos($project['current_status'], 'wp_conversion') !== false) {
        $wp_conversion_count++;
    } elseif (strpos($project['current_status'], 'page_creation') !== false) {
        $page_creation_count++;
    } elseif (strpos($project['current_status'], 'golive') !== false) {
        $golive_count++;
    }

    if (strpos($project['current_status'], '_qa') !== false) {
        $in_qa_count++;
    }
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-kanban me-2 text-primary"></i>
            Your Projects
        </h5>
        <div class="d-flex">
            <span class="badge bg-primary me-2" title="WP Conversion">WP: <?php echo $wp_conversion_count; ?></span>
            <span class="badge bg-warning text-dark me-2" title="Page Creation">Pages: <?php echo $page_creation_count; ?></span>
            <span class="badge bg-success me-2" title="Go-Live">GoLive: <?php echo $golive_count; ?></span>
            <span class="badge bg-info text-white" title="In QA">QA: <?php echo $in_qa_count; ?></span>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($active_projects)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-clipboard-check text-muted" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="fw-normal text-muted">You don't have any active projects</h6>
            <div class="mt-3">
                <a href="<?php echo BASE_URL; ?>/webmaster/create_project.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i> Create New Project
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project</th>
                        <th>Status</th>
                        <th>Deadline</th>
                        <th>QA Assigned</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_projects as $project): ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </a>
                            <div class="small text-muted">
                                Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            $badge_class = 'secondary';
                            $icon = 'arrow-right';
                            $status_name = 'Unknown';

                            if (strpos($project['current_status'], 'wp_conversion') !== false) {
                                if (strpos($project['current_status'], '_qa') !== false) {
                                    $badge_class = 'info';
                                    $icon = 'clipboard-check';
                                    $status_name = 'WP Conversion QA';
                                } else {
                                    $badge_class = 'primary';
                                    $icon = 'wordpress';
                                    $status_name = 'WP Conversion';
                                }
                            } elseif (strpos($project['current_status'], 'page_creation') !== false) {
                                if (strpos($project['current_status'], '_qa') !== false) {
                                    $badge_class = 'info';
                                    $icon = 'clipboard-check';
                                    $status_name = 'Page Creation QA';
                                } else {
                                    $badge_class = 'warning';
                                    $icon = 'file-earmark-plus';
                                    $status_name = 'Page Creation';
                                }
                            } elseif (strpos($project['current_status'], 'golive') !== false) {
                                if (strpos($project['current_status'], '_qa') !== false) {
                                    $badge_class = 'info';
                                    $icon = 'clipboard-check';
                                    $status_name = 'Go-Live QA';
                                } else {
                                    $badge_class = 'success';
                                    $icon = 'rocket';
                                    $status_name = 'Go-Live';
                                }
                            }
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>">
                                <i class="bi bi-<?php echo $icon; ?> me-1"></i>
                                <?php echo $status_name; ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($project['project_deadline'])): ?>
                                <?php
                                $deadline_class = 'success';
                                $days_remaining = $project['days_remaining'];

                                if ($days_remaining < 0) {
                                    $deadline_class = 'danger';
                                    $days_text = abs($days_remaining) . ' days overdue';
                                } elseif ($days_remaining === 0) {
                                    $deadline_class = 'warning';
                                    $days_text = 'Due today';
                                } elseif ($days_remaining <= 3) {
                                    $deadline_class = 'warning';
                                    $days_text = $days_remaining . ' days left';
                                } else {
                                    $days_text = $days_remaining . ' days left';
                                }
                                ?>
                                <span class="badge bg-<?php echo $deadline_class; ?>">
                                    <?php echo date('M j', strtotime($project['project_deadline'])); ?>
                                </span>
                                <div class="small text-muted mt-1">
                                    <?php echo $days_text; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No deadline</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($project['qa_username'])): ?>
                                <div class="d-flex align-items-center">
                                    <span class="avatar avatar-sm bg-info text-white me-2">
                                        <?php echo strtoupper(substr($project['qa_username'], 0, 1)); ?>
                                    </span>
                                    <span><?php echo htmlspecialchars($project['qa_username']); ?></span>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-secondary">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo BASE_URL; ?>view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if (count($active_projects) > 10): ?>
    <div class="card-footer bg-white text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/projects.php" class="text-decoration-none">
            View All Projects
        </a>
    </div>
    <?php endif; ?>
</div>