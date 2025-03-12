<?php
// Get QA reporters workload
$qa_reporters_query = "SELECT
                        u.id,
                        u.username,
                        COUNT(qa.project_id) as assigned_projects,
                        SUM(CASE WHEN DATEDIFF(p.project_deadline, CURDATE()) < 0 THEN 1 ELSE 0 END) as overdue_projects
                      FROM users u
                      LEFT JOIN qa_assignments qa ON u.id = qa.qa_user_id
                      LEFT JOIN projects p ON qa.project_id = p.id AND p.current_status NOT LIKE '%completed%'
                      WHERE u.role IN ('qa_reporter', 'qa_manager')
                      GROUP BY u.id, u.username
                      ORDER BY assigned_projects DESC";

$qa_reporters_workload = $conn->query($qa_reporters_query)->fetch_all(MYSQLI_ASSOC);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-people me-2 text-primary"></i>
            QA Reporter Workload
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($qa_reporters_workload)): ?>
            <div class="text-center py-5">
                <div class="mb-3">
                    <i class="bi bi-people text-muted" style="font-size: 2.5rem;"></i>
                </div>
                <h6 class="fw-normal text-muted">No QA reporters found</h6>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($qa_reporters_workload as $reporter): ?>
                <div class="list-group-item border-0 py-3 px-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-sm bg-info text-white me-2">
                                <?php echo strtoupper(substr($reporter['username'], 0, 1)); ?>
                            </div>
                            <h6 class="mb-0 fw-semibold">
                                <?php echo htmlspecialchars($reporter['username']); ?>
                            </h6>
                        </div>
                        <div>
                            <?php if ((int)$reporter['assigned_projects'] > 0): ?>
                                <span class="badge bg-primary"><?php echo $reporter['assigned_projects']; ?> projects</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">No projects</span>
                            <?php endif; ?>

                            <?php if ((int)$reporter['overdue_projects'] > 0): ?>
                                <span class="badge bg-danger"><?php echo $reporter['overdue_projects']; ?> overdue</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <?php
                        $workload_percentage = 0;
                        if ((int)$reporter['assigned_projects'] > 0) {
                            // Calculate workload percentage (assuming 5 projects is 100%)
                            $workload_percentage = min(100, ((int)$reporter['assigned_projects'] / 5) * 100);

                            // Determine color based on workload
                            $progress_class = 'bg-success';
                            if ($workload_percentage >= 80) {
                                $progress_class = 'bg-danger';
                            } elseif ($workload_percentage >= 50) {
                                $progress_class = 'bg-warning';
                            }
                        }
                        ?>
                        <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar"
                             style="width: <?php echo $workload_percentage; ?>%"
                             aria-valuenow="<?php echo $workload_percentage; ?>"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($qa_reporters_workload)): ?>
    <div class="card-footer bg-white py-2">
        <!-- <a href="<?php echo BASE_URL; ?>/qa_manager/manage_qa_reporters.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-people me-1"></i> Manage QA Reporters
        </a> -->
    </div>
    <?php endif; ?>
</div>