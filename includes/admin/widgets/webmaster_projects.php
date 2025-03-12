<?php
// Get ALL projects in active webmaster stages (wp_conversion, page_creation, golive) without limit
$webmaster_projects_query = "SELECT p.*,
                          u.username as webmaster_name,
                          u.id as webmaster_id,
                          CASE
                              WHEN p.current_status LIKE '%wp_conversion%' AND p.current_status NOT LIKE '%qa%' THEN 'wp_conversion'
                              WHEN p.current_status LIKE '%page_creation%' AND p.current_status NOT LIKE '%qa%' THEN 'page_creation'
                              WHEN p.current_status LIKE '%golive%' AND p.current_status NOT LIKE '%qa%' THEN 'golive'
                              ELSE 'other'
                          END as stage,
                          DATEDIFF(p.project_deadline, CURDATE()) as days_remaining
                          FROM projects p
                          LEFT JOIN users u ON p.webmaster_id = u.id
                          WHERE (p.current_status LIKE '%wp_conversion%'
                                OR p.current_status LIKE '%page_creation%'
                                OR p.current_status LIKE '%golive%')
                          AND p.current_status NOT LIKE '%qa%'
                          AND p.current_status NOT LIKE '%complete%'
                          ORDER BY
                              CASE
                                  WHEN DATEDIFF(p.project_deadline, CURDATE()) < 0 THEN 0
                                  ELSE 1
                              END,
                              DATEDIFF(p.project_deadline, CURDATE()),
                              p.created_at DESC";

$webmaster_projects = $conn->query($webmaster_projects_query)->fetch_all(MYSQLI_ASSOC);

// Count projects by stage
$wp_conversion_count = 0;
$page_creation_count = 0;
$golive_count = 0;

foreach ($webmaster_projects as $project) {
    if ($project['stage'] === 'wp_conversion') {
        $wp_conversion_count++;
    } elseif ($project['stage'] === 'page_creation') {
        $page_creation_count++;
    } elseif ($project['stage'] === 'golive') {
        $golive_count++;
    }
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-kanban me-2 text-primary"></i>
            Webmaster Projects
        </h5>
        <span class="d-flex">
            <span class="badge bg-primary me-2" title="WP Conversion">WP: <?php echo $wp_conversion_count; ?></span>
            <span class="badge bg-warning text-dark me-2" title="Page Creation">Pages: <?php echo $page_creation_count; ?></span>
            <span class="badge bg-success" title="Go-Live">Golive: <?php echo $golive_count; ?></span>
        </span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($webmaster_projects)): ?>
        <div class="text-center py-4">
            <p class="text-muted">No active webmaster projects found</p>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Project</th>
                        <th>Webmaster</th>
                        <th>Stage</th>
                        <th>Deadline</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webmaster_projects as $project): ?>
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
                            <?php if (!empty($project['webmaster_name'])): ?>
                            <div class="d-flex align-items-center">
                                <div class="avatar avatar-sm bg-primary text-white me-2">
                                    <?php echo substr($project['webmaster_name'], 0, 1); ?>
                                </div>
                                <?php echo htmlspecialchars($project['webmaster_name']); ?>
                            </div>
                            <?php else: ?>
                            <span class="badge bg-danger">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $badge_class = 'secondary';
                            $icon = 'arrow-right';
                            $stage_name = 'Unknown';

                            if ($project['stage'] === 'wp_conversion') {
                                $badge_class = 'primary';
                                $icon = 'wordpress';
                                $stage_name = 'WP Conversion';
                            } elseif ($project['stage'] === 'page_creation') {
                                $badge_class = 'warning text-dark';
                                $icon = 'file-earmark-plus';
                                $stage_name = 'Page Creation';
                            } elseif ($project['stage'] === 'golive') {
                                $badge_class = 'success';
                                $icon = 'rocket';
                                $stage_name = 'Go-Live';
                            }
                            ?>
                            <span class="badge bg-<?php echo $badge_class; ?>">
                                <i class="bi bi-<?php echo $icon; ?> me-1"></i>
                                <?php echo $stage_name; ?>
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
                        <td class="text-center">
                            <a href="<?php echo BASE_URL; ?>project_details.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>