<?php
// filepath: c:\wamp64\www\qa_reporter\includes\admin\widgets\unassigned_projects.php

// Get unassigned projects
$unassigned_projects_query = "SELECT p.id, p.name, p.created_at, p.current_status,
                            u.username as webmaster_name
                            FROM projects p
                            LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                            JOIN users u ON p.webmaster_id = u.id
                            WHERE qa.id IS NULL
                            AND (p.current_status LIKE '%wp_conversion_qa%'
                                OR p.current_status LIKE '%page_creation_qa%'
                                OR p.current_status LIKE '%golive_qa%')
                            ORDER BY p.created_at ASC";

$unassigned_projects = $conn->query($unassigned_projects_query)->fetch_all(MYSQLI_ASSOC);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-exclamation-triangle me-2 text-danger"></i>
            Unassigned Projects
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($unassigned_projects)): ?>
            <div class="py-4 text-center">
                <i class="bi bi-check-circle text-success fs-4"></i>
                <p class="text-muted mb-0">All projects are assigned</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($unassigned_projects as $project): ?>
                    <div class="list-group-item p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <a href="../view_project.php?id=<?php echo $project['id']; ?>" class="text-decoration-none fw-medium">
                                <?php echo htmlspecialchars($project['name']); ?>
                            </a>
                            <?php
                            $status_badges = [];
                            $statuses = !empty($project['current_status']) ? explode(',', $project['current_status']) : [];

                            foreach ($statuses as $status) {
                                if (strpos($status, 'wp_conversion_qa') !== false) {
                                    $status_badges[] = '<span class="badge bg-info me-1">WP QA</span>';
                                }
                                if (strpos($status, 'page_creation_qa') !== false) {
                                    $status_badges[] = '<span class="badge bg-warning text-dark me-1">Page QA</span>';
                                }
                                if (strpos($status, 'golive_qa') !== false) {
                                    $status_badges[] = '<span class="badge bg-primary me-1">Golive QA</span>';
                                }
                            }

                            echo implode(' ', $status_badges);
                            ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small">
                            <div class="text-muted">
                                <i class="bi bi-person me-1"></i>
                                <?php echo htmlspecialchars($project['webmaster_name']); ?>
                            </div>
                            <div class="text-muted">
                                <i class="bi bi-calendar me-1"></i>
                                <?php echo date('M j, Y', strtotime($project['created_at'])); ?>
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-primary btn-sm w-100"
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
    <?php if (count($unassigned_projects) > 5): ?>
        <div class="card-footer bg-white text-center p-2">
            <a href="unassigned_projects.php" class="text-decoration-none">View all unassigned projects</a>
        </div>
    <?php endif; ?>
</div>