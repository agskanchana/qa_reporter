<?php
// Get unassigned QA projects
$unassigned_projects_query = "SELECT p.id, p.name, p.webmaster_id, p.current_status, p.created_at,
                            u.username as webmaster_name
                            FROM projects p
                            LEFT JOIN users u ON p.webmaster_id = u.id
                            LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                            WHERE qa.id IS NULL
                            AND (p.current_status LIKE '%_qa%')
                            ORDER BY p.created_at DESC
                            LIMIT 5";
$unassigned_projects = $conn->query($unassigned_projects_query)->fetch_all(MYSQLI_ASSOC);

// Count all unassigned QA projects
$unassigned_count_query = "SELECT COUNT(*) as count
                         FROM projects p
                         LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                         WHERE qa.id IS NULL
                         AND (p.current_status LIKE '%_qa%')";
$unassigned_count = $conn->query($unassigned_count_query)->fetch_assoc()['count'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-clipboard-x me-2 text-danger"></i>
            Unassigned Projects
            <?php if ($unassigned_count > 0): ?>
            <span class="badge bg-danger ms-2"><?php echo $unassigned_count; ?></span>
            <?php endif; ?>
        </h5>
        <?php if ($unassigned_count > 0): ?>
        <a href="<?php echo BASE_URL; ?>/projects.php?filter=unassigned" class="btn btn-sm btn-outline-danger">
            View All
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (count($unassigned_projects) === 0): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-check2-circle text-success" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="fw-normal text-muted">All projects have been assigned</h6>
        </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($unassigned_projects as $project): ?>
            <div class="list-group-item border-0 py-3 px-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0">
                        <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>" class="text-decoration-none">
                            <?php echo htmlspecialchars($project['name']); ?>
                        </a>
                    </h6>
                    <div>
                        <?php
                        $status_badge = '';
                        if (strpos($project['current_status'], 'wp_conversion_qa') !== false) {
                            $status_badge = '<span class="badge bg-info">WP Conversion QA</span>';
                        } else if (strpos($project['current_status'], 'page_creation_qa') !== false) {
                            $status_badge = '<span class="badge bg-warning text-dark">Page Creation QA</span>';
                        } else if (strpos($project['current_status'], 'golive_qa') !== false) {
                            $status_badge = '<span class="badge bg-success">Go-Live QA</span>';
                        }
                        echo $status_badge;
                        ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-muted">
                        <i class="bi bi-person me-1"></i>
                        <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unknown'); ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#assignQAModal"
                            data-project-id="<?php echo $project['id']; ?>"
                            data-project-name="<?php echo htmlspecialchars($project['name']); ?>">
                        <i class="bi bi-person-plus me-1"></i> Assign QA
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>