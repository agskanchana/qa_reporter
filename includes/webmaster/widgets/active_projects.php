<?php
// Current page for pagination
$page = isset($_GET['active_page']) ? (int)$_GET['active_page'] : 1;
$items_per_page = 5;  // Number of projects per page
$offset = ($page - 1) * $items_per_page;

// Get total active projects count for pagination
$count_query = "SELECT COUNT(*) as total FROM projects
                WHERE webmaster_id = ?
                AND current_status NOT LIKE '%complete%'";

$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $webmaster_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_projects = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_projects / $items_per_page);

// Get all active projects assigned to this webmaster (not completed) with pagination
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
                           p.created_at DESC
                       LIMIT ? OFFSET ?";

$stmt = $conn->prepare($active_projects_query);
$stmt->bind_param("iii", $webmaster_id, $items_per_page, $offset);
$stmt->execute();
$active_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count projects by status (use the total count, not just the paginated results)
$status_count_query = "SELECT
                      SUM(CASE WHEN current_status LIKE '%wp_conversion%' THEN 1 ELSE 0 END) as wp_conversion_count,
                      SUM(CASE WHEN current_status LIKE '%page_creation%' THEN 1 ELSE 0 END) as page_creation_count,
                      SUM(CASE WHEN current_status LIKE '%golive%' THEN 1 ELSE 0 END) as golive_count,
                      SUM(CASE WHEN current_status LIKE '%_qa%' THEN 1 ELSE 0 END) as in_qa_count
                      FROM projects
                      WHERE webmaster_id = ?
                      AND current_status NOT LIKE '%complete%'";

$status_stmt = $conn->prepare($status_count_query);
$status_stmt->bind_param("i", $webmaster_id);
$status_stmt->execute();
$status_counts = $status_stmt->get_result()->fetch_assoc();

$wp_conversion_count = $status_counts['wp_conversion_count'] ?? 0;
$page_creation_count = $status_counts['page_creation_count'] ?? 0;
$golive_count = $status_counts['golive_count'] ?? 0;
$in_qa_count = $status_counts['in_qa_count'] ?? 0;
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
                            <a href="<?php echo BASE_URL; ?>/view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
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
                            <a href="<?php echo BASE_URL; ?>/view_project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye me-1"></i> View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-container p-3 border-top">
            <nav aria-label="Active projects pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>webmaster/index.php?active_page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?active_page=1">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item ' . (($i == $page) ? 'active' : '') . '"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?active_page=' . $i . '">' . $i . '</a></li>';
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?active_page=' . $total_pages . '">' . $total_pages . '</a></li>';
                    }
                    ?>

                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>webmaster/index.php?active_page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>