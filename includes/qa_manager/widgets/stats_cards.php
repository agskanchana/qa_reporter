<?php
// Get projects needing QA (based on auto-assign settings)
$qa_needed_query = "SELECT
                    SUM(CASE WHEN p.current_status LIKE '%wp_conversion_qa%' THEN 1 ELSE 0 END) as wp_conversion_count,
                    SUM(CASE WHEN p.current_status LIKE '%page_creation_qa%' THEN 1 ELSE 0 END) as page_creation_count,
                    SUM(CASE WHEN p.current_status LIKE '%golive_qa%' THEN 1 ELSE 0 END) as golive_count,
                    COUNT(*) as total_projects
                    FROM projects p
                    LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                    WHERE p.current_status LIKE '%_qa%'
                    AND (qa.project_id IS NULL OR qa.qa_user_id != 1)"; // Not assigned to admin (ID 1)

$qa_needed_result = $conn->query($qa_needed_query);
$qa_needed_stats = $qa_needed_result->fetch_assoc();

// Get unassigned projects count
$unassigned_query = "SELECT COUNT(*) as unassigned_count
                     FROM projects p
                     LEFT JOIN qa_assignments qa ON p.id = qa.project_id
                     WHERE p.current_status LIKE '%_qa%'
                     AND qa.project_id IS NULL";

$unassigned_result = $conn->query($unassigned_query);
$unassigned_count = $unassigned_result->fetch_assoc()['unassigned_count'];

// Get projects completed in the last 30 days
$completed_query = "SELECT COUNT(*) as count,
                   AVG(DATEDIFF(updated_at, created_at)) as avg_days
                   FROM projects
                   WHERE current_status = 'completed'
                   AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

$completed_result = $conn->query($completed_query);
$completed_stats = $completed_result->fetch_assoc();

// Get QA reporters count
$reporters_query = "SELECT COUNT(*) as count FROM users WHERE role = 'qa_reporter'";
$reporters_result = $conn->query($reporters_query);
$reporters_count = $reporters_result->fetch_assoc()['count'];
?>

<div class="row">
    <!-- Projects Needing QA -->
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar bg-primary-subtle text-primary">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-1"><?php echo $qa_needed_stats['total_projects'] ?? 0; ?></h4>
                        <div class="text-muted">Projects Needing QA</div>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between small">
                    <?php if (!$auto_assign_wp): ?>
                    <div>
                        <span class="d-block fw-medium"><?php echo $qa_needed_stats['wp_conversion_count'] ?? 0; ?></span>
                        <span class="text-muted">WP Conversion</span>
                    </div>
                    <?php endif; ?>

                    <div>
                        <span class="d-block fw-medium"><?php echo $qa_needed_stats['page_creation_count'] ?? 0; ?></span>
                        <span class="text-muted">Page Creation</span>
                    </div>

                    <?php if (!$auto_assign_golive): ?>
                    <div>
                        <span class="d-block fw-medium"><?php echo $qa_needed_stats['golive_count'] ?? 0; ?></span>
                        <span class="text-muted">Go-Live</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Unassigned Projects -->
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar bg-warning-subtle text-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-1"><?php echo $unassigned_count; ?></h4>
                        <div class="text-muted">Unassigned Projects</div>
                    </div>
                </div>
                <hr>
                <div class="text-center small">
                    <a href="#unassigned-projects" class="text-decoration-none">
                        <i class="bi bi-arrow-down me-1"></i>
                        View unassigned projects
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Completed Projects -->
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar bg-success-subtle text-success">
                            <i class="bi bi-check2-circle"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-1"><?php echo $completed_stats['count'] ?? 0; ?></h4>
                        <div class="text-muted">Completed (30 days)</div>
                    </div>
                </div>
                <hr>
                <div class="text-center small">
                    <span class="text-muted">
                        Avg. completion: <span class="fw-medium"><?php echo round($completed_stats['avg_days'] ?? 0); ?> days</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- QA Team Members -->
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0 me-3">
                        <div class="avatar bg-info-subtle text-info">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-1"><?php echo $reporters_count; ?></h4>
                        <div class="text-muted">QA Reporters</div>
                    </div>
                </div>
                <hr>
                <div class="text-center small">
                    <a href="#qa-reporter-workload" class="text-decoration-none">
                        <i class="bi bi-arrow-down me-1"></i>
                        View workload distribution
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>