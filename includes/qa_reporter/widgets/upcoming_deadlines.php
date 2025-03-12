<?php
// Get upcoming deadlines for projects assigned to this QA reporter
$upcoming_deadlines_query = "SELECT p.id, p.name, p.project_deadline,
                           u.username as webmaster_name,
                           DATEDIFF(p.project_deadline, CURDATE()) as days_remaining
                           FROM projects p
                           JOIN qa_assignments qa ON p.id = qa.project_id
                           LEFT JOIN users u ON p.webmaster_id = u.id
                           WHERE qa.qa_user_id = ?
                           AND p.current_status NOT LIKE '%completed%'
                           AND p.project_deadline >= CURDATE()
                           ORDER BY p.project_deadline ASC
                           LIMIT 5";
$stmt = $conn->prepare($upcoming_deadlines_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_deadlines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get overdue projects assigned to this QA reporter
$overdue_query = "SELECT p.id, p.name, p.project_deadline,
                 u.username as webmaster_name,
                 DATEDIFF(CURDATE(), p.project_deadline) as days_overdue
                 FROM projects p
                 JOIN qa_assignments qa ON p.id = qa.project_id
                 LEFT JOIN users u ON p.webmaster_id = u.id
                 WHERE qa.qa_user_id = ?
                 AND p.current_status NOT LIKE '%completed%'
                 AND p.project_deadline < CURDATE()
                 ORDER BY p.project_deadline ASC
                 LIMIT 5";
$stmt = $conn->prepare($overdue_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overdue_projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-calendar-event me-2 text-primary"></i>
            Upcoming Deadlines
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($upcoming_deadlines) && empty($overdue_projects)): ?>
        <div class="text-center py-5">
            <div class="mb-3">
                <i class="bi bi-calendar-check text-success" style="font-size: 2.5rem;"></i>
            </div>
            <h6 class="fw-normal text-muted">No upcoming deadlines</h6>
        </div>
        <?php else: ?>
            <?php if (!empty($overdue_projects)): ?>
            <div class="px-4 pt-3">
                <h6 class="text-danger mb-2">
                    <i class="bi bi-exclamation-circle me-1"></i> Overdue
                </h6>
            </div>
            <div class="list-group list-group-flush mb-3">
                <?php foreach ($overdue_projects as $project): ?>
                <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>"
                   class="list-group-item border-0 py-3 px-4 list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0"><?php echo htmlspecialchars($project['name']); ?></h6>
                        <span class="badge bg-danger">
                            <?php echo $project['days_overdue']; ?> day<?php echo $project['days_overdue'] > 1 ? 's' : ''; ?> overdue
                        </span>
                    </div>
                    <div class="small">
                        <span class="text-muted me-2">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('M j, Y', strtotime($project['project_deadline'])); ?>
                        </span>
                        <span class="text-muted">
                            <i class="bi bi-person me-1"></i>
                            <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($upcoming_deadlines)): ?>
            <div class="px-4 pt-3">
                <h6 class="text-primary mb-2">
                    <i class="bi bi-calendar-event me-1"></i> Upcoming
                </h6>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($upcoming_deadlines as $project): ?>
                <a href="<?php echo BASE_URL; ?>/project_details.php?id=<?php echo $project['id']; ?>"
                   class="list-group-item border-0 py-3 px-4 list-group-item-action">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <h6 class="mb-0"><?php echo htmlspecialchars($project['name']); ?></h6>
                        <?php
                        $badge_class = 'bg-success';
                        if ($project['days_remaining'] <= 2) {
                            $badge_class = 'bg-danger';
                        } else if ($project['days_remaining'] <= 5) {
                            $badge_class = 'bg-warning text-dark';
                        }
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo $project['days_remaining']; ?> day<?php echo $project['days_remaining'] > 1 ? 's' : ''; ?> left
                        </span>
                    </div>
                    <div class="small">
                        <span class="text-muted me-2">
                            <i class="bi bi-calendar me-1"></i>
                            <?php echo date('M j, Y', strtotime($project['project_deadline'])); ?>
                        </span>
                        <span class="text-muted">
                            <i class="bi bi-person me-1"></i>
                            <?php echo htmlspecialchars($project['webmaster_name'] ?? 'Unknown'); ?>
                        </span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!empty($upcoming_deadlines) || !empty($overdue_projects)): ?>
    <div class="card-footer bg-white py-2">
        <!-- <a href="<?php echo BASE_URL; ?>/qa_reporter/all_deadlines.php" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-calendar-week me-1"></i> View All Deadlines
        </a> -->
    </div>
    <?php endif; ?>
</div>