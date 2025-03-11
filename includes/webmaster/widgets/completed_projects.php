<?php
// Current page for pagination
$page = isset($_GET['completed_page']) ? (int)$_GET['completed_page'] : 1;
$items_per_page = 5;  // Number of projects per page
$offset = ($page - 1) * $items_per_page;

// Get total completed projects for pagination
$count_query = "SELECT COUNT(*) as total FROM projects
                WHERE webmaster_id = ?
                AND current_status LIKE '%complete%'";

$count_stmt = $conn->prepare($count_query);
$count_stmt->bind_param("i", $webmaster_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_projects = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_projects / $items_per_page);

// Get completed projects for current page with all needed fields
$completed_projects_query = "SELECT p.*,
                           p.wp_conversion_deadline as wp_deadline,
                           (SELECT COUNT(*) FROM project_checklist_status pcs
                            WHERE pcs.project_id = p.id AND pcs.status = 'failed') as failed_items_count,
                           (SELECT COUNT(*) FROM project_checklist_status pcs
                            WHERE pcs.project_id = p.id AND pcs.status = 'passed') as passed_items_count,
                           (SELECT COUNT(*) FROM missed_deadlines md
                            WHERE md.project_id = p.id AND md.deadline_type = 'wp_conversion') as wp_deadline_missed,
                           (SELECT COUNT(*) FROM missed_deadlines md
                            WHERE md.project_id = p.id AND md.deadline_type = 'project') as project_deadline_missed
                           FROM projects p
                           WHERE p.webmaster_id = ?
                           AND p.current_status LIKE '%complete%'
                           ORDER BY p.updated_at DESC
                           LIMIT ? OFFSET ?";

$projects_stmt = $conn->prepare($completed_projects_query);
$projects_stmt->bind_param("iii", $webmaster_id, $items_per_page, $offset);
$projects_stmt->execute();
$completed_projects = $projects_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get WP and Golive status history for each project
if (!empty($completed_projects)) {
    $project_ids = array_column($completed_projects, 'id');

    if (!empty($project_ids)) {
        $ids_str = implode(',', $project_ids);

        // WP Conversion Status History
        $wp_status_history = [];
        $wp_history_query = "SELECT project_id, created_at
                            FROM project_status_history
                            WHERE project_id IN ($ids_str)
                            AND status = 'wp_conversion_qa'
                            ORDER BY created_at ASC";

        $wp_history_result = $conn->query($wp_history_query);
        if ($wp_history_result) {
            while ($row = $wp_history_result->fetch_assoc()) {
                if (!isset($wp_status_history[$row['project_id']])) {
                    $wp_status_history[$row['project_id']] = $row['created_at'];
                }
            }
        }

        // Golive Status History
        $golive_status_history = [];
        $golive_history_query = "SELECT project_id, created_at
                                FROM project_status_history
                                WHERE project_id IN ($ids_str)
                                AND status = 'golive_qa'
                                ORDER BY created_at ASC";

        $golive_history_result = $conn->query($golive_history_query);
        if ($golive_history_result) {
            while ($row = $golive_history_result->fetch_assoc()) {
                if (!isset($golive_status_history[$row['project_id']])) {
                    $golive_status_history[$row['project_id']] = $row['created_at'];
                }
            }
        }
    }
}

// Get key stats - without final_status column
$stats_query = "SELECT
                COUNT(*) as total_completed,
                AVG(DATEDIFF(updated_at, created_at)) as avg_completion_days
                FROM projects
                WHERE webmaster_id = ?
                AND current_status LIKE '%complete%'";

$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("i", $webmaster_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Calculate overall success metrics based on checklist items
$overall_stats_query = "SELECT
                       SUM(CASE WHEN pcs.status = 'passed' THEN 1 ELSE 0 END) as items_passed,
                       SUM(CASE WHEN pcs.status = 'failed' THEN 1 ELSE 0 END) as items_failed,
                       COUNT(*) as total_items
                       FROM project_checklist_status pcs
                       JOIN projects p ON pcs.project_id = p.id
                       WHERE p.webmaster_id = ?
                       AND p.current_status LIKE '%complete%'";

$overall_stmt = $conn->prepare($overall_stats_query);
$overall_stmt->bind_param("i", $webmaster_id);
$overall_stmt->execute();
$overall_stats = $overall_stmt->get_result()->fetch_assoc();

// Calculate pass rate from checklist items
$pass_rate = 0;
if (!empty($overall_stats) && $overall_stats['total_items'] > 0) {
    $pass_rate = ($overall_stats['items_passed'] / $overall_stats['total_items']) * 100;
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-check2-circle me-2 text-success"></i>
            Completed Projects
        </h5>

        <?php if (!empty($completed_projects)): ?>
        <div class="stats-pills d-flex">
            <div class="text-center me-3">
                <span class="d-block h4 mb-0"><?php echo $stats['total_completed']; ?></span>
                <small class="text-muted">Total</small>
            </div>
            <div class="text-center me-3">
                <span class="d-block h4 mb-0"><?php echo round($stats['avg_completion_days']); ?></span>
                <small class="text-muted">Avg Days</small>
            </div>
            <div class="text-center">
                <span class="d-block h4 mb-0">
                    <span class="<?php echo $pass_rate >= 70 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo round($pass_rate); ?>%
                    </span>
                </span>
                <small class="text-muted">Pass Rate</small>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <?php if (empty($completed_projects)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-clipboard-check text-muted" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="fw-normal text-muted mb-3">You don't have any completed projects yet</h6>
            <p class="text-muted small">Complete your first project to see statistics here</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 30%">Project</th>
                        <th>Completion Date</th>
                        <th>WP Deadline</th>
                        <th>Project Deadline</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed_projects as $project): ?>
                    <tr>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </a>
                            <div class="small text-muted">
                                Created: <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            // Use the most recent status update as completion date
                            $completion_date = $project['updated_at'];
                            ?>
                            <div class="fw-medium"><?php echo date('M j, Y', strtotime($completion_date)); ?></div>
                            <div class="small text-muted">
                                <?php
                                $completed_time = strtotime($completion_date);
                                $now = time();
                                $days_ago = floor(($now - $completed_time) / (60 * 60 * 24));

                                if ($days_ago == 0) {
                                    echo "Today";
                                } elseif ($days_ago == 1) {
                                    echo "Yesterday";
                                } else {
                                    echo $days_ago . " days ago";
                                }
                                ?>
                            </div>
                        </td>
                        <td>
                            <?php
                            // Use same logic as view_project.php for WP deadline
                            $wp_deadline = !empty($project['wp_deadline']) ? $project['wp_deadline'] : null;
                            if (!empty($wp_deadline)):
                            ?>
                                <div class="fw-medium"><?php echo date('M j, Y', strtotime($wp_deadline)); ?></div>
                                <?php
                                // Check if this project ever reached WP conversion QA status
                                $has_reached_wp_qa = isset($wp_status_history[$project['id']]);

                                // If it reached WP QA status, check if it was before the deadline
                                if ($has_reached_wp_qa) {
                                    $first_wp_qa_date = new DateTime($wp_status_history[$project['id']]);
                                    $wp_deadline_obj = new DateTime($wp_deadline);
                                    $wp_deadline_obj->setTime(23, 59, 59);

                                    $deadline_achieved = ($first_wp_qa_date <= $wp_deadline_obj);
                                } else {
                                    $deadline_achieved = false;
                                }
                                ?>
                                <span class="badge <?php echo $deadline_achieved ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $deadline_achieved ? 'Achieved' : 'Missed'; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            // Use same logic as view_project.php for project deadline
                            $project_deadline = !empty($project['project_deadline']) ? $project['project_deadline'] : null;
                            if (!empty($project_deadline)):
                            ?>
                                <div class="fw-medium"><?php echo date('M j, Y', strtotime($project_deadline)); ?></div>
                                <?php
                                // Check if this project ever reached Golive QA status
                                $has_reached_golive_qa = isset($golive_status_history[$project['id']]);

                                // If it reached Golive QA status, check if it was before the deadline
                                if ($has_reached_golive_qa) {
                                    $first_golive_qa_date = new DateTime($golive_status_history[$project['id']]);
                                    $project_deadline_obj = new DateTime($project_deadline);
                                    $project_deadline_obj->setTime(23, 59, 59);

                                    $deadline_achieved = ($first_golive_qa_date <= $project_deadline_obj);
                                } else {
                                    $deadline_achieved = false;
                                }
                                ?>
                                <span class="badge <?php echo $deadline_achieved ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $deadline_achieved ? 'Achieved' : 'Missed'; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-graph-up me-1"></i> Project Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-container p-3 border-top">
            <nav aria-label="Completed projects pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo BASE_URL; ?>webmaster/index.php?completed_page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    if ($start_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?completed_page=1">1</a></li>';
                        if ($start_page > 2) {
                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                        }
                    }

                    for ($i = $start_page; $i <= $end_page; $i++) {
                        echo '<li class="page-item ' . (($i == $page) ? 'active' : '') . '"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?completed_page=' . $i . '">' . $i . '</a></li>';
                    }

                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="'.BASE_URL.'webmaster/index.php?completed_page=' . $total_pages . '">' . $total_pages . '</a></li>';
                    }
                    ?>

                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?completed_page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>

    <?php if ($total_projects > $items_per_page * 2): ?>
    <div class="card-footer bg-white text-center p-2">
        <a href="<?php echo BASE_URL; ?>/webmaster/projects.php?filter=completed" class="text-decoration-none">
            View All Completed Projects
        </a>
    </div>
    <?php endif; ?>
</div>